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
    header('location: ' . url('admin/login.php'));
    die();
}
//Protecting against SESSION HIJACKING
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_viewemploy')) {
    header('location:' . url("admin/index.php"));
    die();
}

$schoolRoles = $conn->query(
    "SELECT id, role_key, title FROM school_admin_roles WHERE is_active = 1 AND role_key <> 'teacher' ORDER BY sort_order ASC, id ASC"
)->fetchAll(PDO::FETCH_OBJ);
$validSchoolRoleIds = array_map('intval', array_column(array_map(function ($role) {
    return (array)$role;
}, $schoolRoles), 'id'));
$courseScopedRoleKeys = ['grade_deputy', 'counselor'];
$assignableCourses = $conn->query('
    SELECT codecourse, coursename
    FROM codtbl_course
    WHERE activecourse = 1
    ORDER BY idcourse DESC
')->fetchAll(PDO::FETCH_OBJ);
$editAssignedCourseCodes = [];
if (!empty($_GET['edit'])) {
    $assignedCourseStmt = $conn->prepare('SELECT codecourse FROM school_staff_courses WHERE admin_username = ?');
    $assignedCourseStmt->execute([(string)$_GET['edit']]);
    $editAssignedCourseCodes = array_map('intval', $assignedCourseStmt->fetchAll(PDO::FETCH_COLUMN));
}
$documentUploadErrors = $_SESSION['admin_document_flash_errors'] ?? [];
unset($_SESSION['admin_document_flash_errors']);

function normalizeStaffCourseCodes(PDO $conn, $rawCourseCodes): array
{
    if (!is_array($rawCourseCodes)) {
        return [];
    }
    $courseCodes = array_values(array_unique(array_filter(array_map('intval', $rawCourseCodes), function ($code) {
        return $code > 0;
    })));
    if (!$courseCodes) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($courseCodes), '?'));
    $stmt = $conn->prepare("SELECT codecourse FROM codtbl_course WHERE activecourse = 1 AND codecourse IN ($placeholders)");
    $stmt->execute($courseCodes);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function syncStaffCourseAssignments(PDO $conn, string $username, ?int $schoolRoleId, array $courseCodes, array $schoolRoles, array $scopedRoleKeys): void
{
    $roleKey = null;
    foreach ($schoolRoles as $role) {
        if ((int)$role->id === (int)$schoolRoleId) {
            $roleKey = (string)$role->role_key;
            break;
        }
    }

    $delete = $conn->prepare('DELETE FROM school_staff_courses WHERE admin_username = ?');
    $delete->execute([$username]);
    if (!in_array($roleKey, $scopedRoleKeys, true)) {
        return;
    }

    $insert = $conn->prepare('INSERT INTO school_staff_courses (admin_username, codecourse) VALUES (?, ?)');
    foreach ($courseCodes as $codecourse) {
        $insert->execute([$username, $codecourse]);
    }
}

function schoolRoleKeyById(?int $schoolRoleId, array $schoolRoles): ?string
{
    foreach ($schoolRoles as $role) {
        if ((int)$role->id === (int)$schoolRoleId) {
            return (string)$role->role_key;
        }
    }
    return null;
}

function syncExtendedStaffPermissions(PDO $conn, string $username, bool $isEdit, ?string $schoolRoleKey = null): void
{
    $prefix = $isEdit ? 'e' : '';
    $permissionNames = [
        'permission_staff_view' => $prefix . 'permission_staff_view',
        'permission_staff_documents' => $prefix . 'permission_staff_documents',
        'permission_insurance' => $prefix . 'permission_insurance',
        'permission_monthly_payment' => $prefix . 'permission_monthly_payment',
        'permission_assigned_tasks' => $prefix . 'permission_assigned_tasks',
    ];
    $values = [];
    foreach ($permissionNames as $postName) {
        $values[] = isset($_POST[$postName]) ? 1 : 0;
    }
    if ($schoolRoleKey === 'financial_deputy') {
        $values[0] = $values[1] = $values[2] = $values[3] = 1;
    } elseif ($schoolRoleKey === 'technical_deputy') {
        $values[4] = 1;
    } elseif ($schoolRoleKey === 'counselor') {
        // مشاور حتی با ارسال دستی فرم نباید به عملیات واریز ماهانه دسترسی بگیرد.
        $values[3] = 0;
    }
    $values[] = $username;
    $stmt = $conn->prepare('UPDATE dbadmin SET
        permission_staff_view=?, permission_staff_documents=?, permission_insurance=?,
        permission_monthly_payment=?, permission_assigned_tasks=? WHERE username=?');
    $stmt->execute($values);
}

/*
 * Permission labels and explanations are kept in one place so the create and
 * edit forms always describe the exact same capability.
 */
$employeePermissionColumns = [
    [
        [
            'title' => 'عمومی و ارتباطات',
            'items' => [
                ['menu-call', 'emenu-call', 'permision_viewcall', 'دسترسی به تماس و پیامک مدرسه', 'منو و صفحات تماس امن، پیامک امن، وضعیت تماس‌ها و گزارش‌های ارتباطی را در اختیار همکار می‌گذارد.', true],
                ['add-call', 'eadd-call', 'permision_addcall', 'ثبت مخاطب و گروه تماس', 'امکان ورود مخاطبان، ایمپورت لیست تماس و ایجاد گروه‌های تماس را فعال می‌کند.'],
                ['manage-call', 'emanage-call', 'permision_managecall', 'مدیریت و تخصیص تماس‌ها', 'امکان تخصیص شماره، ویرایش رکوردهای تماس، مدیریت مرکز تماس و خروجی اطلاعات را می‌دهد.'],
                ['manage-excel', 'manage-excel', 'excel_permission', 'گزارش‌گیری و ورود/خروجی Excel', 'منوی آمار و گزارشات و دکمه‌های ورود یا خروجی اکسل دانش‌آموزان و گزارش‌ها را فعال می‌کند.'],
                ['manage-skill', 'manage-skill', 'skill_permission', 'دسترسی به پایه‌های مهارتی و فوق‌برنامه', 'تب و فهرست پایه‌های مهارتی و فوق‌برنامه را در بخش پایه‌ها نمایش می‌دهد.'],
                ['manage-reportcall', 'manage-reportcall', 'reportcall_permission', 'مشاهده گزارش تماس‌ها و فایل‌های صوتی', 'امکان ورود به گزارش تماس‌ها و شنیدن فایل‌های صوتی ضبط‌شده را می‌دهد. دسترسی عمومی تماس نیز باید فعال باشد.'],
                ['manage-olympiad', 'manage-olympiad', 'olympiad_permission', 'دسترسی به پایه‌های المپیاد', 'تب و فهرست پایه‌های المپیاد را در مدیریت پایه‌ها نمایش می‌دهد.'],
                ['administrator', 'administrator', 'administrator', 'سرپرست اجرایی سامانه', 'دسترسی مدیریتی گسترده به تأیید تکالیف، پیگیری‌ها، اعلان‌ها و برخی سوابق حساس می‌دهد. این گزینه فقط برای مسئول مجاز فعال شود.'],
                ['manage-history', 'manage-history', 'history_permission', 'مشاهده و ویرایش پرونده و سوابق دانش‌آموز', 'امکان مشاهده سوابق کامل، رویدادها، پاسخ‌ها و ویرایش بخش‌های حساس پرونده دانش‌آموز را می‌دهد.'],
                ['manage-autodialer', 'manage-autodialer', 'autodialer', 'مدیریت تماس‌های خودکار', 'امکان مدیریت مخاطبان، لیست سیاه، گروه‌ها، فایل‌های صوتی و کمپین‌های تماس خودکار را می‌دهد.'],
                ['permission_calendar', 'permission_calendar', 'permission_calendar', 'مدیریت کامل تقویم و رویدادها', 'اجازه ورود به پنل تقویم و ایجاد، ویرایش و حذف رویدادهای مدرسه را می‌دهد.'],
                ['register-class', 'eregister-class', 'register_class_permission', 'مدیریت کامل دفتر کلاسی', 'اجازه ویرایش و حذف ستون‌ها، نمرات، حضور و غیاب و موارد انضباطی دفتر کلاسی را می‌دهد. دامنه پایه برای معاون پایه و مشاور همچنان رعایت می‌شود.'],
                ['permission_news_notif', 'permission_news_notif', 'permission_news_notif', 'مدیریت اخبار و اعلان‌های مدرسه', 'اجازه ایجاد، ویرایش و مدیریت اخبار و اعلان‌های روزانه سامانه را می‌دهد.'],
                ['permission_assigned_tasks', 'epermission_assigned_tasks', 'permission_assigned_tasks', 'مدیریت کارهای محول‌شده', 'اجازه مشاهده، تعریف، تخصیص و تغییر وضعیت کارهای محول‌شده را می‌دهد. این مجوز برای معاون فنی به‌صورت پیش‌فرض پیشنهاد می‌شود.'],
            ],
        ],
        [
            'title' => 'دانش‌آموزان',
            'items' => [
                ['menu-student', 'emenu-student', 'permision_viewstudent', 'دسترسی به بخش مدیریت دانش‌آموزان', 'منوی دانش‌آموزان و صفحات ثبت‌نام، ویرایش، ثبت پایه و گزارش‌ها را نمایش می‌دهد. برای انجام عملیات، مجوز جزئی مربوط نیز لازم است.'],
                ['add-student', 'eadd-student', 'permision_addstudent', 'ثبت دانش‌آموز جدید', 'اجازه ذخیره ثبت‌نام قطعی، موقت و گروهی دانش‌آموزان را می‌دهد.'],
                ['edit-student', 'eedit-student', 'permision_editstudent', 'ویرایش اطلاعات و وضعیت دانش‌آموز', 'امکان ویرایش پروفایل، اطلاعات قطعی و موقت و بررسی دانش‌آموزان غیرفعال یا انصرافی را می‌دهد.'],
                ['course-student', 'ecourse-student', 'permision_addcourse_student', 'ثبت دانش‌آموز در پایه و مشاهده ریزفعالیت‌ها', 'امکان ثبت دانش‌آموز در پایه/کلاس و مشاهده لاگ حضور، آزمون، تکلیف، فیلم و مستندات آموزشی را می‌دهد.'],
                ['report-student', 'ereport-student', 'permision_reportstudent', 'گزارش‌گیری و خروجی دانش‌آموزان', 'گزارش‌های تفکیکی، فهرست‌های وضعیتی و خروجی دانش‌آموزان را فعال می‌کند. برخی گزارش‌ها به مجوز ثبت پایه نیز نیاز دارند.'],
            ],
        ],
        [
            'title' => 'معرفان برون‌سازمانی',
            'items' => [
                ['menu-collague', 'emenu-collague', 'permision_viewcollague', 'مدیریت معرفان برون‌سازمانی', 'اجازه ثبت، ویرایش، غیرفعال‌سازی و مشاهده زیرمجموعه‌های معرفان برون‌سازمانی را می‌دهد.'],
                ['report-collague', 'ereport-collague', 'permision_reportcollague', 'گزارش‌گیری و خروجی معرفان', 'اجازه مشاهده گزارش عملکرد و دریافت خروجی اطلاعات معرفان را می‌دهد.'],
            ],
        ],
        [
            'title' => 'اعلان‌ها، مستندات و تنظیمات',
            'items' => [
                ['menu-allert', 'emenu-allert', 'permision_viewalert', 'مدیریت اعلان‌ها و گفت‌وگوهای داخلی', 'امکان مشاهده و مدیریت اعلان‌ها، قواعد اعلان و گفت‌وگوهای داخلی سامانه را می‌دهد.'],
                ['menu-doc', 'emenu-doc', 'permision_viewdocument', 'مدیریت مستندات مدرسه', 'اجازه جست‌وجو، ثبت و مدیریت مستندات داخلی مدرسه را می‌دهد.'],
                ['menu-employ', 'emenu-employ', 'permision_viewemploy', m('perm.employ_title'), m('perm.employ_desc')],
                ['permission_staff_view', 'epermission_staff_view', 'permission_staff_view', 'مشاهده فهرست همکاران', 'فقط فهرست و اطلاعات عمومی همکاران را نمایش می‌دهد و اجازه تعریف یا ویرایش حساب‌ها را نمی‌دهد.'],
                ['permission_staff_documents', 'epermission_staff_documents', 'permission_staff_documents', 'مشاهده مستندات پرسنلی همکاران', 'اجازه مشاهده و دریافت مدارک، سوابق و فایل‌های شناسایی هویت همکاران و معلمان را می‌دهد.'],
                ['permission_insurance', 'epermission_insurance', 'permission_insurance', 'مدیریت بیمه افراد', 'اجازه ثبت و ویرایش شرکت بیمه، شماره بیمه‌نامه، بازه اعتبار و وضعیت بیمه همکاران را می‌دهد.'],
            ],
        ],
    ],
    [
        [
            'title' => 'پایه‌ها، کلاس‌ها و آزمون',
            'items' => [
                ['menu-course', 'emenu-course', 'permision_viewcourse', 'مدیریت پایه‌ها، کلاس‌ها و محتوای آموزشی', m('perm.course_desc')],
                ['del-course', 'edel-course', 'permision_deletcourse', 'حذف و تغییر وضعیت پایه‌ها و کلاس‌ها', 'اجازه حذف، فعال/غیرفعال‌کردن پایه‌ها و در برخی صفحات حذف کلاس یا محتوا را می‌دهد.'],
                ['discount', 'ediscount', 'permision_discount', 'مدیریت کدهای تخفیف', 'اجازه ایجاد و مدیریت کدهای تخفیف ثبت‌نام را می‌دهد.'],
                ['menu-quiz', 'emenu-quiz', 'permision_viewtest', 'مدیریت آزمون‌ها و نتایج', 'اجازه مشاهده، ایجاد و مدیریت آزمون، پاسخ‌نامه، غایبان و نتایج را می‌دهد. مجوز حذف آزمون جداست.'],
            ],
        ],
        [
            'title' => 'پیگیری دانش‌آموز و درخواست‌ها',
            'items' => [
                ['suporter', 'esuporter', 'permision_suporter', 'مسئول پیگیری دانش‌آموزان', 'کاربر را در فهرست مسئولان قابل تخصیص به دانش‌آموز قرار می‌دهد و در اعلان‌ها به‌عنوان اپراتور پیگیری شناسایی می‌شود.'],
                ['manage_suporter', 'manage_suporter', 'permision_manage_suporter', 'مدیریت مسئولان پیگیری', 'دسترسی به تخصیص دانش‌آموز، گزارش عملکرد مسئولان، دانش‌آموزان بدون مسئول و گزارش‌های مدیریتی را می‌دهد.'],
                ['menu-support', 'emenu-support', 'permision_viewsupport', 'مشاهده و پاسخ به درخواست‌ها', 'اجازه مشاهده صندوق درخواست‌ها، موارد باز و اضطراری و پاسخ‌دادن به آن‌ها را می‌دهد.'],
                ['support-refer', 'esupport-refer', 'permision_refer', 'ارجاع درخواست به همکار', 'امکان ارجاع یک درخواست یا تیکت به همکار دیگر را فعال می‌کند.'],
                ['support-admin', 'esupport-admin', 'permision_allticket', 'مشاهده و مدیریت همه درخواست‌ها', 'اجازه مشاهده و مدیریت تمام تیکت‌ها، نه فقط موارد مربوط به خود کاربر، را می‌دهد.'],
            ],
        ],
        [
            'title' => 'مالی، شهریه و کیف پول',
            'items' => [
                ['menu-bill', 'emenu-bill', 'permision_viewbill', 'دسترسی به امور مالی و شهریه', 'منوی امور مالی، فاکتورها، پرداخت‌ها، صورتحساب‌ها و اقساط را نمایش می‌دهد. برای عملیات حساس، مجوز جزئی مربوط نیز لازم است.'],
                ['actbill', 'eactbill', 'permision_actfactor', 'بررسی و تأیید پرداخت‌های کارت‌به‌کارت', 'امکان بررسی فاکتورهای منتظر تأیید، تصویر رسید و مغایرت‌های واریز را می‌دهد.'],
                ['notpay', 'enotpay', 'permision_notpay', 'مدیریت بدهی‌ها و پرداخت‌های ناموفق', 'اجازه مشاهده و رسیدگی به فاکتورهای پرداخت‌نشده، مستردشده و ثبت واریز مربوط را می‌دهد.'],
                ['actwallet', 'eactwallet', 'permision_actwallet', 'بررسی و تأیید شارژ کیف پول', 'اجازه بررسی درخواست‌های شارژ کیف پول، تأیید آن‌ها و رسیدگی به مغایرت‌ها را می‌دهد.'],
                ['bill', 'ebill', 'permision_bill', 'مشاهده صورتحساب‌ها و گزارش‌های مالی', 'اجازه مشاهده صورتحساب‌ها، انتقال‌ها، گزارش مالی پیشرفته و برخی خروجی‌های مالی را می‌دهد.'],
                ['reportbill', 'ereportbill', 'permision_financial_export', 'خروجی گزارش‌های مالی (فعلاً غیرفعال)', 'این مجوز در نسخه فعلی سامانه هنوز به عملیات مستقلی متصل نیست و فعال‌کردن آن اثری در دسترسی فعلی ندارد.'],
                ['manage-installment', 'manage-installment', 'installment_permission', 'مدیریت همه پرونده‌های اقساطی', 'به‌جای محدودبودن به اقساط ثبت‌شده توسط خود کاربر، مشاهده همه پرونده‌های اقساطی، معوقات و خروجی آن‌ها را فعال می‌کند.'],
                ['menu-wallet', 'emenu-wallet', 'permision_viewwallet', 'مدیریت موجودی و تراکنش‌های کیف پول', 'دسترسی به داشبورد کیف پول، افزایش یا کاهش اعتبار، برداشت و گزارش تراکنش‌ها را می‌دهد.'],
                ['permission_monthly_payment', 'epermission_monthly_payment', 'permission_monthly_payment', 'مدیریت واریز ماهانه همکاران', 'اجازه ثبت مبلغ، ماه، تاریخ پرداخت و شماره پیگیری واریزهای ماهانه پرسنل را می‌دهد.'],
            ],
        ],
    ],
];

function renderEmployeePermissionColumns(array $columns, $admin = null): void
{
    $isEdit = is_object($admin);
    foreach ($columns as $groups) { ?>
        <div class="col-md-6">
            <?php foreach ($groups as $groupIndex => $group) { ?>
                <?php $parent = $group['items'][0] ?? null; ?>
                <section class="permission-tree <?= $groupIndex > 0 ? 'mt-3' : '' ?>" data-permission-tree>
                    <div class="permission-tree-heading">
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($group['title']) ?></h6>
                            <small>فعال‌کردن دسترسی اصلی، زیرمجموعه‌ها را هم فعال می‌کند. انتخاب جزئی، وضعیت «بخشی» را نشان می‌دهد.</small>
                        </div>
                        <?php if ($parent): [$parentCreate, $parentEdit, $parentColumn, $parentLabel, $parentHelp] = $parent; $parentChecked = $isEdit ? (int)($admin->{$parentColumn} ?? 0) === 1 : !empty($parent[5]); ?>
                            <label class="permission-master mb-0">
                                <input class="permission-parent" name="<?= htmlspecialchars($isEdit ? $parentEdit : $parentCreate) ?>" type="checkbox" <?= $parentChecked ? 'checked' : '' ?>>
                                دسترسی کامل این بخش
                            </label>
                        <?php endif; ?>
                    </div>
                    <div class="permission-tree-children">
                    <?php foreach ($group['items'] as $permissionIndex => $permission) {
                        if ($permissionIndex === 0) continue;
                        [$createName, $editName, $column, $label, $help] = $permission;
                        if (!inst_feature('wallet') && in_array($column, ['permision_actwallet', 'permision_viewwallet'], true)) continue;
                        $defaultChecked = !empty($permission[5]);
                        $checked = $isEdit
                            ? (int)($admin->{$column} ?? 0) === 1
                            : $defaultChecked;
                        ?>
                        <div class="permission-option">
                            <label class="mb-0">
                                <input name="<?= htmlspecialchars($isEdit ? $editName : $createName) ?>"
                                       type="checkbox" <?= $checked ? 'checked' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </label>
                            <span class="menu-info-trigger permission-info"
                                  data-tooltip="<?= htmlspecialchars($help) ?>"
                                  aria-label="راهنمای <?= htmlspecialchars($label) ?>"
                                  onclick="event.preventDefault(); event.stopPropagation();">
                                <i class="fe fe-info"></i>
                            </span>
                        </div>
                    <?php } ?>
                    </div>
                </section>
            <?php } ?>
        </div>
    <?php }
}

function renderMenuPermissionNode(array $node, array $selected, int $depth = 0, bool $grantedByParent = false): void
{
    static $instance = 0;
    if (!AdminPermissionNodeAvailable($node)) return;
    /* A checked parent grants its complete branch. Rendering descendants as
       checked keeps that broad grant visible when the form is edited. */
    $checked = $grantedByParent || in_array($node['code'], $selected, true);
    $children = array_values(array_filter($node['children'] ?? [], 'AdminPermissionNodeAvailable'));
    $childrenId = 'permission-children-' . (++$instance) . '-' . substr(sha1($node['code']), 0, 8);
    $startsCollapsed = $children !== [];
    ?>
    <div class="menu-permission-node depth-<?= $depth ?><?= $startsCollapsed ? ' is-collapsed' : '' ?>" data-menu-node data-depth="<?= $depth ?>">
        <div class="menu-permission-row">
            <input class="menu-permission-check" type="checkbox" name="access_permissions[]"
                   value="<?= htmlspecialchars($node['code'], ENT_QUOTES, 'UTF-8') ?>"
                   aria-label="اعطای دسترسی <?= htmlspecialchars(AdminPermissionDisplayLabel($node), ENT_QUOTES, 'UTF-8') ?>"
                   <?= $checked ? 'checked' : '' ?>>
            <?php if ($children): ?>
                <button type="button" class="menu-permission-name"
                        aria-expanded="<?= $startsCollapsed ? 'false' : 'true' ?>"
                        aria-controls="<?= $childrenId ?>"><?= htmlspecialchars(AdminPermissionDisplayLabel($node), ENT_QUOTES, 'UTF-8') ?></button>
                <span class="menu-permission-count"><?= count($children) ?> زیرمجموعه</span>
                <button type="button" class="menu-permission-toggle"
                        aria-expanded="<?= $startsCollapsed ? 'false' : 'true' ?>"
                        aria-controls="<?= $childrenId ?>"
                        title="جمع یا باز کردن زیرمجموعه‌ها">
                    <i class="fe fe-chevron-down" aria-hidden="true"></i>
                    <span class="sr-only">جمع یا باز کردن زیرمجموعه‌های <?= htmlspecialchars(AdminPermissionDisplayLabel($node), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php else: ?>
                <span class="menu-permission-name-static"><?= htmlspecialchars(AdminPermissionDisplayLabel($node), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
        <?php if ($children): ?>
            <div class="menu-permission-children" id="<?= $childrenId ?>" <?= $startsCollapsed ? 'hidden' : '' ?>>
                <?php foreach ($children as $child) renderMenuPermissionNode($child, $selected, $depth + 1, $checked); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function renderMenuPermissionTree(?object $admin = null): void
{
    global $conn;
    $selected = [];
    if ($admin && !empty($admin->username)) {
        try {
            $stmt = $conn->prepare('SELECT permission_code FROM admin_user_permissions WHERE admin_username = ?');
            $stmt->execute([$admin->username]);
            $selected = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) { $selected = []; }
    }
    echo '<div class="col-12 menu-permission-tree">';
    foreach (AdminPermissionRegistry() as $node) renderMenuPermissionNode($node, $selected);
    echo '</div>';
}

/*
 * The old dbadmin flags still protect a number of legacy endpoints.  They are
 * not shown to the administrator any more, but an edit must not accidentally
 * clear existing flags while those endpoints are progressively moved to the
 * new tree. New grants are stored only in admin_user_permissions.
 */
function renderLegacyPermissionShadowFields(array $columns, ?object $admin = null): void
{
    if (!$admin) return;
    foreach ($columns as $groups) foreach ($groups as $group) foreach ($group['items'] as $permission) {
        [$createName, $editName, $column] = $permission;
        if (!empty($admin->{$column})) {
            echo '<input type="hidden" name="' . htmlspecialchars($editName) . '" value="1">';
        }
    }
}

if (isset($_POST['esave'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = trim($_POST['ename']);
        $famili = trim($_POST['efamili']);
        $gender = $_POST['egender'];
        $password = $_POST['epassword'];
        if (isset($_POST['emob'])) {
            $mob = $_POST['emob'];
        } else {
            $mob = NULL;
        }
        if (isset($_POST['epic'])) {
            $pic = $_POST['epic'];
        } else {
            $pic = NULL;
        }
        if (isset($_POST['eactivate'])) {
            $activate = 1;
        } else {
            $activate = 0;
        }
        if (isset($_POST['emenu-call'])) {
            $menucall = 1;
        } else {
            $menucall = 0;
        }
        if (isset($_POST['eadd-call'])) {
            $addcall = 1;
        } else {
            $addcall = 0;
        }
        if (isset($_POST['emanage-call'])) {
            $managecall = 1;
        } else {
            $managecall = 0;
        }
        if (isset($_POST['emenu-student'])) {
            $menustudent = 1;
        } else {
            $menustudent = 0;
        }
        if (isset($_POST['eadd-student'])) {
            $addstudent = 1;
        } else {
            $addstudent = 0;
        }
        if (isset($_POST['eedit-student'])) {
            $editstudent = 1;
        } else {
            $editstudent = 0;
        }
        if (isset($_POST['ecourse-student'])) {
            $coursestudent = 1;
        } else {
            $coursestudent = 0;
        }
        if (isset($_POST['ereport-student'])) {
            $reportstudent = 1;
        } else {
            $reportstudent = 0;
        }
        if (isset($_POST['emenu-collague'])) {
            $menucollague = 1;
        } else {
            $menucollague = 0;
        }
        if (isset($_POST['ereport-collague'])) {
            $reportcollague = 1;
        } else {
            $reportcollague = 0;
        }
        if (isset($_POST['emenu-course'])) {
            $menucourse = 1;
        } else {
            $menucourse = 0;
        }
        if (isset($_POST['edel-course'])) {
            $delcourse = 1;
        } else {
            $delcourse = 0;
        }
        if (isset($_POST['ediscount'])) {
            $discount = 1;
        } else {
            $discount = 0;
        }
        if (isset($_POST['emenu-bill'])) {
            $menubill = 1;
        } else {
            $menubill = 0;
        }
        if (isset($_POST['eactbill'])) {
            $actbill = 1;
        } else {
            $actbill = 0;
        }
        if (isset($_POST['enotpay'])) {
            $notpay = 1;
        } else {
            $notpay = 0;
        }
        if (isset($_POST['esuporter'])) {
            $suporter = 1;
        } else {
            $suporter = 0;
        }
        if (isset($_POST['manage_suporter'])) {
            $manage_suporter = 1;
        } else {
            $manage_suporter = 0;
        }
        if (isset($_POST['eactwallet'])) {
            $actwallet = 1;
        } else {
            $actwallet = 0;
        }
        if (isset($_POST['ebill'])) {
            $bill = 1;
        } else {
            $bill = 0;
        }
        if (isset($_POST['ereportbill'])) {
            $reportbill = 1;
        } else {
            $reportbill = 0;
        }
        if (isset($_POST['emenu-wallet'])) {
            $menuwallet = 1;
        } else {
            $menuwallet = 0;
        }
        if (isset($_POST['emenu-allert'])) {
            $menuallert = 1;
        } else {
            $menuallert = 0;
        }
        if (isset($_POST['emenu-support'])) {
            $menusupport = 1;
        } else {
            $menusupport = 0;
        }
        if (isset($_POST['esupport-refer'])) {
            $supportrefer = 1;
        } else {
            $supportrefer = 0;
        }
        if (isset($_POST['esupport-admin'])) {
            $supportadmin = 1;
        } else {
            $supportadmin = 0;
        }
        if (isset($_POST['emenu-doc'])) {
            $menudoc = 1;
        } else {
            $menudoc = 0;
        }
        if (isset($_POST['emenu-employ'])) {
            $menuemploy = 1;
        } else {
            $menuemploy = 0;
        }
        $menusetting = 0; // مجوز تنظیمات بازنشسته شده است.
        if (isset($_POST['emenu-quiz'])) {
            $menuquiz = 1;
        } else {
            $menuquiz = 0;
        }
        if (isset($_POST['manage-excel'])) {
            $manageexcel = 1;
        } else {
            $manageexcel = 0;
        }
        if (isset($_POST['manage-skill'])) {
            $manage_skill = 1;
        } else {
            $manage_skill = 0;
        }
        if (isset($_POST['manage-reportcall'])) {
            $manage_reportcall = 1;
        } else {
            $manage_reportcall = 0;
        }
        if (isset($_POST['manage-installment'])) {
            $manage_installment = 1;
        } else {
            $manage_installment = 0;
        }
        if (isset($_POST['manage-olympiad'])) {
            $manage_olympiad = 1;
        } else {
            $manage_olympiad = 0;
        }
        if (isset($_POST['administrator'])) {
            $administrator = 1;
        } else {
            $administrator = 0;
        }
        if (isset($_POST['manage-history'])) {
            $manage_history = 1;
        } else {
            $manage_history = 0;
        }
        if (isset($_POST['manage-autodialer'])) {
            $manage_autodialer = 1;
        } else {
            $manage_autodialer = 0;
        }
        if (isset($_POST['permission_calendar'])) {
            $manage_calendar = 1;
        } else {
            $manage_calendar = 0;
        }
        if (isset($_POST['permission_news_notif'])) {
            $manage_news_notif = 1;
        } else {
            $manage_news_notif = 0;
        }
        $registerClassPermission = isset($_POST['eregister-class']) ? 1 : 0;

        $skyroom = intval($_POST['skyroom']);

        $adminrole = (isset($_POST['eadminrole']) && in_array((int)$_POST['eadminrole'], [0,51,52,53,54,55,2,6,1,3], true))
            ? (int)$_POST['eadminrole'] : NULL;
        $schoolRoleId = isset($_POST['eschool_role_id']) && in_array((int)$_POST['eschool_role_id'], $validSchoolRoleIds, true)
            ? (int)$_POST['eschool_role_id'] : NULL;
        $selectedSchoolRoleKey = schoolRoleKeyById($schoolRoleId, $schoolRoles);
        if (inst_is('school') && $selectedSchoolRoleKey === 'counselor') {
            // مشاور از نظر قالب پنل و رفتار قدیمی سامانه همان adminrole پشتیبان است.
            $adminrole = 1;
            $menubill = $actbill = $notpay = $actwallet = $bill = $reportbill = $menuwallet = 0;
            $manage_installment = 0;
        } elseif ($selectedSchoolRoleKey === 'financial_deputy') {
            $adminrole = 55;
            $menubill = 1;
        } elseif ($selectedSchoolRoleKey === 'technical_deputy') {
            $adminrole = 54;
        }
        $staffCourseCodes = normalizeStaffCourseCodes($conn, $_POST['ecourse_codes'] ?? []);

        $uptv = 'UPDATE dbadmin SET password=?,fname=?,lname=?,adminactive=?,mobile=?,adminpic=?,gender=?,per_skyroom=?,permision_actfactor=?,permision_notpay=?,permision_actwallet=?,permision_bill=?,permision_financial_export=?,permision_viewcall=?,permision_viewstudent=?,permision_viewcollague=?,permision_reportcollague=?,permision_viewcourse=?,permision_viewbill=?,permision_viewwallet=?,permision_viewalert=?,permision_viewsupport=?,permision_viewdocument=?,permision_viewemploy=?,permision_viewsetting=?,permision_addstudent=?,permision_editstudent=?,permision_addcourse_student=?,permision_reportstudent=?,permision_refer=?,permision_allticket=?,permision_deletcourse=?,permision_discount=?,permision_addcall=?,permision_managecall=?,permision_viewtest=?,permision_suporter=?,permision_manage_suporter=?,excel_permission=?,skill_permission=?,reportcall_permission=?,installment_permission=?,olympiad_permission=?, adminrole=? ,administrator=?,history_permission=?,autodialer=?,permission_calendar=?,permission_news_notif=? WHERE username=?';
        $exup = $conn->prepare($uptv);
        $exup->execute([$password, $name, $famili, $activate, $mob, $pic, $gender, $skyroom, $actbill, $notpay, $actwallet, $bill, $reportbill, $menucall, $menustudent, $menucollague, $reportcollague, $menucourse, $menubill, $menuwallet, $menuallert, $menusupport, $menudoc, $menuemploy, $menusetting, $addstudent, $editstudent, $coursestudent, $reportstudent, $supportrefer, $supportadmin, $delcourse, $discount, $addcall, $managecall, $menuquiz, $suporter,$manage_suporter,$manageexcel,$manage_skill,$manage_reportcall,$manage_installment,$manage_olympiad,$adminrole,$administrator,$manage_history,$manage_autodialer,$manage_calendar, $manage_news_notif,  $_GET['edit']]);
        $schoolRoleUpdate = $conn->prepare('UPDATE dbadmin SET school_role_id = ? WHERE username = ?');
        $schoolRoleUpdate->execute([$schoolRoleId, $_GET['edit']]);
        syncExtendedStaffPermissions($conn, (string)$_GET['edit'], true, $selectedSchoolRoleKey);
        SyncAdminPermissionGrants((string)$_GET['edit'], $_POST['access_permissions'] ?? []);
        $registerClassUpdate = $conn->prepare('UPDATE dbadmin SET register_class_permission = ? WHERE username = ?');
        $registerClassUpdate->execute([$registerClassPermission, $_GET['edit']]);
        syncStaffCourseAssignments($conn, (string)$_GET['edit'], $schoolRoleId, $staffCourseCodes, $schoolRoles, $courseScopedRoleKeys);
        $documentUploadErrors = SaveAdminDocumentUploads((string)$_GET['edit']);
        $accept = 'بروز رسانی با موفقیت انجام شد';
    }
}
if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (GetAdminUsername($_POST['username'])) {
            $eror = 'نام کاربری از قبل وجود دارد';
        } else {
            if (checkDakheli($_POST['dakheli'])) {
                $name = trim($_POST['name']);
                $famili = trim($_POST['famili']);
                $dakheli = $_POST['dakheli'];
                $gender = $_POST['gender'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                if (isset($_POST['mob'])) {
                    $mob = $_POST['mob'];
                } else {
                    $mob = NULL;
                }
                if (isset($_POST['pic'])) {
                    $pic = $_POST['pic'];
                } else {
                    $pic = NULL;
                }
                if (isset($_POST['activate'])) {
                    $activate = 1;
                } else {
                    $activate = 0;
                }
                if (isset($_POST['menu-call'])) {
                    $menucall = 1;
                } else {
                    $menucall = 0;
                }
                if (isset($_POST['add-call'])) {
                    $addcall = 1;
                } else {
                    $addcall = 0;
                }
                if (isset($_POST['manage-call'])) {
                    $managecall = 1;
                } else {
                    $managecall = 0;
                }
                if (isset($_POST['menu-student'])) {
                    $menustudent = 1;
                } else {
                    $menustudent = 0;
                }
                if (isset($_POST['add-student'])) {
                    $addstudent = 1;
                } else {
                    $addstudent = 0;
                }
                if (isset($_POST['edit-student'])) {
                    $editstudent = 1;
                } else {
                    $editstudent = 0;
                }
                if (isset($_POST['course-student'])) {
                    $coursestudent = 1;
                } else {
                    $coursestudent = 0;
                }
                if (isset($_POST['report-student'])) {
                    $reportstudent = 1;
                } else {
                    $reportstudent = 0;
                }
                if (isset($_POST['menu-collague'])) {
                    $menucollague = 1;
                } else {
                    $menucollague = 0;
                }
                if (isset($_POST['report-collague'])) {
                    $reportcollague = 1;
                } else {
                    $reportcollague = 0;
                }
                if (isset($_POST['menu-course'])) {
                    $menucourse = 1;
                } else {
                    $menucourse = 0;
                }
                if (isset($_POST['del-course'])) {
                    $delcourse = 1;
                } else {
                    $delcourse = 0;
                }
                if (isset($_POST['discount'])) {
                    $discount = 1;
                } else {
                    $discount = 0;
                }
                if (isset($_POST['menu-bill'])) {
                    $menubill = 1;
                } else {
                    $menubill = 0;
                }
                if (isset($_POST['actbill'])) {
                    $actbill = 1;
                } else {
                    $actbill = 0;
                }
                if (isset($_POST['notpay'])) {
                    $notpay = 1;
                } else {
                    $notpay = 0;
                }
                if (isset($_POST['actwallet'])) {
                    $actwallet = 1;
                } else {
                    $actwallet = 0;
                }
                if (isset($_POST['bill'])) {
                    $bill = 1;
                } else {
                    $bill = 0;
                }
                if (isset($_POST['reportbill'])) {
                    $reportbill = 1;
                } else {
                    $reportbill = 0;
                }
                if (isset($_POST['suporter'])) {
                    $suporter = 1;
                } else {
                    $suporter = 0;
                }
                if (isset($_POST['manage_suporter'])) {
                    $manage_suporter = 1;
                } else {
                    $manage_suporter = 0;
                }
                if (isset($_POST['menu-wallet'])) {
                    $menuwallet = 1;
                } else {
                    $menuwallet = 0;
                }
                if (isset($_POST['menu-allert'])) {
                    $menuallert = 1;
                } else {
                    $menuallert = 0;
                }
                if (isset($_POST['menu-support'])) {
                    $menusupport = 1;
                } else {
                    $menusupport = 0;
                }
                if (isset($_POST['support-refer'])) {
                    $supportrefer = 1;
                } else {
                    $supportrefer = 0;
                }
                if (isset($_POST['support-admin'])) {
                    $supportadmin = 1;
                } else {
                    $supportadmin = 0;
                }
                if (isset($_POST['menu-doc'])) {
                    $menudoc = 1;
                } else {
                    $menudoc = 0;
                }
                if (isset($_POST['menu-employ'])) {
                    $menuemploy = 1;
                } else {
                    $menuemploy = 0;
                }
                $menusetting = 0; // مجوز تنظیمات بازنشسته شده است.
                if (isset($_POST['menu-quiz'])) {
                    $menuquiz = 1;
                } else {
                    $menuquiz = 0;
                }
                if (isset($_POST['manage-excel'])) {
                    $manageexcel = 1;
                } else {
                    $manageexcel = 0;
                }
                if (isset($_POST['manage-skill'])) {
                    $manage_skill = 1;
                } else {
                    $manage_skill = 0;
                }
                if (isset($_POST['manage-reportcall'])) {
                    $manage_reportcall = 1;
                } else {
                    $manage_reportcall = 0;
                }
                if (isset($_POST['manage-installment'])) {
                    $manage_installment = 1;
                } else {
                    $manage_installment = 0;
                }
                if (isset($_POST['manage-olympiad'])) {
                    $manage_olympiad = 1;
                } else {
                    $manage_olympiad = 0;
                }
                if (isset($_POST['administrator'])) {
                    $administrator = 1;
                } else {
                    $administrator = 0;
                }
                if (isset($_POST['manage-history'])) {
                    $manage_history = 1;
                } else {
                    $manage_history = 0;
                }
                if (isset($_POST['manage-autodialer'])) {
                    $manage_autodialer = 1;
                } else {
                    $manage_autodialer = 0;
                }
                if (isset($_POST['permission_calendar'])) {
                    $manage_calendar = 1;
                } else {
                    $manage_calendar = 0;
                }
                if (isset($_POST['permission_news_notif'])) {
                    $manage_news_notif = 1;
                } else {
                    $manage_news_notif = 0;
                }
                $registerClassPermission = isset($_POST['register-class']) ? 1 : 0;


                $skyroom = intval($_POST['skyroom']);
                // each number represents a role , for more info about the roles check menu.php in admin directory or the comment of adminrole column in dbadmin.
                $adminrole = (isset($_POST['adminrole']) && in_array((int)$_POST['adminrole'], [0,51,52,53,54,55,2,6,1,3], true))
                    ? (int)$_POST['adminrole'] : NULL;
                $schoolRoleId = isset($_POST['school_role_id']) && in_array((int)$_POST['school_role_id'], $validSchoolRoleIds, true)
                    ? (int)$_POST['school_role_id'] : NULL;
                $selectedSchoolRoleKey = schoolRoleKeyById($schoolRoleId, $schoolRoles);
                if (inst_is('school') && $selectedSchoolRoleKey === 'counselor') {
                    // مشاور با داشبورد و منطق پشتیبان قدیمی ایجاد می‌شود.
                    $adminrole = 1;
                    $menubill = $actbill = $notpay = $actwallet = $bill = $reportbill = $menuwallet = 0;
                    $manage_installment = 0;
                } elseif ($selectedSchoolRoleKey === 'financial_deputy') {
                    $adminrole = 55;
                    $menubill = 1;
                } elseif ($selectedSchoolRoleKey === 'technical_deputy') {
                    $adminrole = 54;
                }
                $staffCourseCodes = normalizeStaffCourseCodes($conn, $_POST['course_codes'] ?? []);

                $hfStaffType = $adminrole === 3 ? 'teacher' : ($adminrole === 0 ? 'admin' : 'staff');
                hf_control_require_seat($conn, hf_control_admin_id($username), $hfStaffType);
                $addmin = 'INSERT INTO dbadmin (username,password,fname,lname,adminactive,mobile,dakheli,adminpic,gender,per_skyroom,permision_actfactor,permision_notpay,permision_actwallet,permision_bill,permision_financial_export,permision_viewcall,permision_viewstudent,permision_viewcollague,permision_reportcollague,permision_viewcourse,permision_viewbill,permision_viewwallet,permision_viewalert,permision_viewsupport,permision_viewdocument,permision_viewemploy,permision_viewsetting,permision_addstudent,permision_editstudent,permision_addcourse_student,permision_reportstudent,permision_refer,permision_allticket,permision_deletcourse,permision_discount,permision_addcall,permision_managecall,permision_viewtest,permision_suporter,permision_manage_suporter,excel_permission,skill_permission,reportcall_permission,installment_permission,olympiad_permission,adminrole,administrator,history_permission,autodialer,permission_calendar,permission_news_notif)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $ins = $conn->prepare($addmin);
                $ins->execute([$username, $password, $name, $famili, $activate, $mob, $dakheli, $pic, $gender, $skyroom, $actbill, $notpay, $actwallet, $bill, $reportbill, $menucall, $menustudent, $menucollague, $reportcollague, $menucourse, $menubill, $menuwallet, $menuallert, $menusupport, $menudoc, $menuemploy, $menusetting, $addstudent, $editstudent, $coursestudent, $reportstudent, $supportrefer, $supportadmin, $delcourse, $discount, $addcall, $managecall, $menuquiz, $suporter,$manage_suporter,$manageexcel,$manage_skill,$manage_reportcall,$manage_installment,$manage_olympiad,$adminrole,$administrator,$manage_history,$manage_autodialer,$manage_calendar,$manage_news_notif]);
                $schoolRoleUpdate = $conn->prepare('UPDATE dbadmin SET school_role_id = ? WHERE username = ?');
                $schoolRoleUpdate->execute([$schoolRoleId, $username]);
                syncExtendedStaffPermissions($conn, $username, false, $selectedSchoolRoleKey);
                SyncAdminPermissionGrants($username, $_POST['access_permissions'] ?? []);
                $registerClassUpdate = $conn->prepare('UPDATE dbadmin SET register_class_permission = ? WHERE username = ?');
                $registerClassUpdate->execute([$registerClassPermission, $username]);
                syncStaffCourseAssignments($conn, $username, $schoolRoleId, $staffCourseCodes, $schoolRoles, $courseScopedRoleKeys);
                $oesPermissions = [
                    'dashboard.view' => 1,
                    'calendar.view' => $manage_calendar,
                    'calendar.manage' => $manage_calendar,
                    'students.section' => $menustudent,
                    'students.register' => $addstudent,
                    'groups.registration' => $coursestudent,
                    'groups.manage' => $menucourse,
                    'groups.assign' => $menucourse,
                    'students.report' => $reportstudent,
                    'packages.create' => $menuquiz,
                    'packages.schedule_manage' => $menuquiz,
                    'exams.section' => $menuquiz,
                    'exams.manage' => $menuquiz,
                    'exams.registration' => $menuquiz,
                    'exams.list' => $menuquiz,
                    'packages.list' => $menuquiz,
                    'results.list' => $menuquiz,
                    'results.detailed' => $menuquiz,
                    'exams.descriptive_grade' => $menuquiz,
                    'colleagues.section' => $menuemploy,
                    'colleagues.create' => $menuemploy,
                    'colleagues.list' => $menuemploy,
                    'colleagues.edit' => $menuemploy,
                    'colleagues.reset_password' => $menuemploy,
                    'colleagues.delete' => $menuemploy,
                    'content.section' => $manage_news_notif,
                    'exams.approve' => $menuquiz,
                    'notifications.manage' => $manage_news_notif,
                    'news.manage' => $manage_news_notif,
                    'events.manage' => $manage_news_notif,
                    'requests.section' => $menusupport,
                    'signup_requests.manage' => $menusupport,
                    'teacher_tickets.manage' => $menusupport,
                ];
                $oesSync = cross_panel_sync_oes_admin([
                    'username' => $username,
                    'first_name' => $name,
                    'last_name' => $famili,
                    'email' => '',
                    'mobile' => $mob,
                    'gender' => (int) $gender,
                    'active' => (int) $activate,
                    'role' => cross_panel_oes_role($adminrole, $selectedSchoolRoleKey),
                    'permissions' => $oesPermissions,
                ]);
                if (empty($oesSync['ok'])) {
                    $_SESSION['cross_panel_warning'] = (string) ($oesSync['message'] ?? 'ساخت حساب در پنل آزمون انجام نشد');
                }
                $documentUploadErrors = SaveAdminDocumentUploads($username);
                if ($documentUploadErrors) {
                    $_SESSION['admin_document_flash_errors'] = $documentUploadErrors;
                    header('location: addemploy.php?edit=' . rawurlencode($username));
                    exit;
                }
                header('location: listadmin.php');

            } else {
                $eror = 'داخلی وارد شده از قبل وجود دارد';
            }
        }


    }
}
include_once '../../temp.html';
displayError(1);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> افزودن کارمند جدید | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .permission-option {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            padding: 7px 9px;
            margin-bottom: 5px;
            border: 1px solid #edf0f5;
            border-radius: 7px;
            background: #fafbfc;
        }

        .permission-option label {
            flex: 1;
            line-height: 1.7;
            cursor: pointer;
        }

        .permission-option input {
            margin-left: 6px;
        }

        .permission-info {
            flex: 0 0 auto;
            color: #5965db;
            cursor: help;
            font-size: 17px;
            margin-top: 1px;
        }
        .permission-tree { border: 1px solid #dce4ef; border-radius: 10px; overflow: hidden; background: #fff; }
        .permission-tree-heading { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:11px 12px; background:#f0f5fb; border-bottom:1px solid #dce4ef; }
        .permission-tree-heading h6 { color:#214f87; font-weight:800; }
        .permission-tree-heading small { color:#667085; line-height:1.6; display:block; }
        .permission-master { white-space:nowrap; font-weight:700; color:#214f87; cursor:pointer; }
        .permission-tree-children { padding:8px; }
        .permission-tree-children .permission-option { margin-bottom:6px; }
        .menu-permission-tree { width:100%; }
        .menu-permission-node { border:1px solid #e1e8f0; border-radius:8px; margin:7px 0; background:#fff; overflow:hidden; }
        .menu-permission-row { display:flex; align-items:center; min-height:44px; padding:0 8px 0 6px; }
        .menu-permission-check { flex:0 0 auto; margin:0 4px 0 9px; cursor:pointer; }
        .menu-permission-name, .menu-permission-name-static { flex:1; text-align:right; padding:10px 4px; font-weight:700; }
        .menu-permission-name { border:0; background:transparent; color:inherit; cursor:pointer; }
        .menu-permission-name:hover, .menu-permission-name:focus { color:#1769aa; text-decoration:underline; outline:none; }
        .menu-permission-count { color:#768396; font-size:12px; white-space:nowrap; margin:0 8px; }
        .menu-permission-toggle { width:34px; height:34px; flex:0 0 34px; display:inline-flex; align-items:center; justify-content:center; padding:0; border:0; border-radius:7px; color:#315d8b; background:#e5eef8; cursor:pointer; }
        .menu-permission-toggle:hover, .menu-permission-toggle:focus { color:#fff; background:#315d8b; outline:none; }
        .menu-permission-toggle i { transition:transform .18s ease; }
        .menu-permission-node.is-collapsed > .menu-permission-row .menu-permission-toggle i { transform:rotate(90deg); }
        .menu-permission-children { margin:0 18px 10px 0; padding-right:12px; border-right:2px solid #d8e4f2; }
        .menu-permission-children[hidden] { display:none !important; }
        .menu-permission-node.depth-0 > .menu-permission-row { background:#eef5fc; color:#174a7c; font-size:16px; }
        .permission-tree-actions { display:flex; align-items:center; gap:5px; }
        .permission-tree-action { border:1px solid rgba(255,255,255,.7); border-radius:6px; padding:3px 7px; color:#fff; background:transparent; font-size:12px; cursor:pointer; }
        .permission-tree-action:hover, .permission-tree-action:focus { color:#e91e63; background:#fff; outline:none; }
        .permission-card > .card-header { flex-wrap:wrap; gap:8px; }
        @media (max-width: 576px) {
            .permission-tree-heading { align-items:flex-start; flex-direction:column; }
            .permission-card > .card-header > div { width:100%; justify-content:space-between; gap:6px !important; }
            .permission-tree-action { padding:3px 5px; }
            .menu-permission-count { display:none; }
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
    <?php if (!isset($_GET['edit'])) { ?>
        <div class="main-content side-content pt-0" dir="rtl">
            <div class="container-fluid">
                <div class="inner-body">

                    <!-- Error Alert Block -->
                    <?php if (isset($eror)) { ?>
                        <div class="alert alert-danger" role="alert">
                            <span class="alert-inner--icon"><i class="mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?php echo $eror ?>
                        </div>
                    <?php } ?>

                    <!-- CSRF Token Generation -->
                    <?php
                    $hash = bin2hex(random_bytes(32));
                    $_SESSION['token'] = $hash;
                    ?>

                    <form method="post" enctype="multipart/form-data" data-parsley-validate="">
                        <!-- Hidden CSRF Token Input -->
                        <input class="form-control" name="token" type="hidden" value="<?php echo $hash ?>">

                        <div class="row row-sm">

                            <!-- RIGHT COLUMN: General Information (اطلاعات عمومی) -->
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-content-center justify-content-between" style="background-color: #5c6bc0;">
                                        اطلاعات عمومی
                                        <span class="menu-info-trigger" style="color: white;"
                                              data-tooltip="در این بخش می‌توانید با ثبت اطلاعات عمومی، تعیین نقش، سطوح دسترسی و سایر تنظیمات، حساب کاربری کارمند جدید را در سامانه ایجاد کنید تا امکان ورود و استفاده از بخش‌های مجاز برای او فراهم شود. همچنین در همین قسمت، اطلاعات ورود شامل نام کاربری و رمز عبور مشخص می‌گردد؛ لطفاً توجه داشته باشید که این اطلاعات به‌درستی ثبت شده و به صورت امن تنها در اختیار شخص مجاز قرار گیرد."
                                              aria-label="راهنمای کامل ایجاد کارمند و اطلاعات ورود"
                                              onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fe fe-info fs-4"></i>
                                    </span>

                                    </div>
                                    <div class="card-body">

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="name" aria-describedby="basic-addon2" type="text" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نام خانوادگی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="famili" aria-describedby="basic-addon2" type="text" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام کاربری <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="username" aria-describedby="basic-addon2" type="text" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">رمزعبور <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="password" aria-describedby="basic-addon2" type="password" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">داخلی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="dakheli" aria-describedby="basic-addon2" type="number" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">شماره موبایل</label>
                                                <input class="form-control" name="mob" aria-describedby="basic-addon2" type="text">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">جنسیت <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="gender" required>
                                                    <option value="خانم">خانم</option>
                                                    <option value="آقای">آقا</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نوع دسترسی اتاق مجازی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="skyroom" required>
                                                    <option value="1">شرکت کننده</option>
                                                    <option value="2">ارائه دهنده</option>
                                                    <option value="3">اپراتور</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">سطح دسترسی سامانه</label>
                                                <select class="form-control select2" name="adminrole">
                                                    <option value="">— بدون نقش —</option>
                                                    <?php if ($_SESSION['admin']['adminrole'] == 0) { ?>
                                                        <option value="0">مدیر</option>
                                                    <?php } ?>
                                                    <?php if ($_SESSION['admin']['adminrole'] == 0) { ?>
                                                        <option value="51">معاونت اجرایی</option>
                                                    <?php } ?>
                                                    <option value="52">معاونت آموزشی</option>
                                                    <option value="53">معاونت فروش</option>
                                                    <option value="54">معاونت فنی</option>
                                                    <option value="55">معاونت مالی</option>
                                                    <option value="6">کارشناس فروش</option>
                                                    <option value="1">پشتیبان</option>
                                                    <option value="2">معاونت میانی</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">سمت سازمانی مدرسه <span class="tx-danger">*</span></label>
                                                <select class="form-control" name="school_role_id" required>
                                                    <option value="">— انتخاب سمت —</option>
                                                    <?php foreach ($schoolRoles as $schoolRole) { ?>
                                                        <option value="<?= (int)$schoolRole->id ?>"
                                                                data-role-key="<?= htmlspecialchars($schoolRole->role_key) ?>"
                                                                data-course-scope="<?= in_array((string)$schoolRole->role_key, $courseScopedRoleKeys, true) ? '1' : '0' ?>">
                                                            <?= htmlspecialchars($schoolRole->title) ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-3 staff-course-scope d-none" data-mode="create">
                                            <div class="col-12">
                                                <label class="form-label">پایه‌های قابل دسترسی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2 staff-course-select" name="course_codes[]" multiple>
                                                    <?php foreach ($assignableCourses as $course) { ?>
                                                        <option value="<?= (int)$course->codecourse ?>"><?= htmlspecialchars($course->coursename) ?></option>
                                                    <?php } ?>
                                                </select>
                                                <small class="text-muted">برای معاون پایه یا مشاور، فقط پایه‌های انتخاب‌شده در بخش پایه‌ها نمایش داده می‌شوند.</small>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6 d-flex align-items-center pt-3">
                                                <label class="ckbox">
                                                    <input name="activate" type="checkbox" checked>
                                                    <span>فعالسازی حساب</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">آدرس عکس</label>
                                            <input class="form-control" name="pic" aria-describedby="basic-addon2" type="text" placeholder="http://yourdomain.ir/wp-content/uploads/3.jpg">
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- LEFT COLUMN: Permissions (دسترسى‌ها و نقش‌ها) -->
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm permission-card">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-content-center justify-content-between" style="background-color: #e91e63;">
                                        دسترسی‌ها و نقش‌ها
                                        <div class="d-flex align-items-center" style="gap:12px;">
                                            <div class="permission-tree-actions">
                                                <button type="button" class="permission-tree-action permission-tree-expand-all">باز کردن همه</button>
                                                <button type="button" class="permission-tree-action permission-tree-collapse-all">بستن همه</button>
                                            </div>
                                            <label class="mb-0 d-flex align-items-center" style="gap:6px;cursor:pointer;">
                                                <input type="checkbox" class="permission-select-all">
                                                انتخاب همه
                                            </label>
                                            <span class="menu-info-trigger" style="color: white;"
                                              data-tooltip="در این بخش می‌توانید مجوزها و سطح دسترسی کارمند را تعیین کنید.با فعال کردن و یا غیرفعال کردن هر بخش،مجوز دسترسی و یا عدم دسترسی کارمندان به آن بخش داده می شود."
                                              aria-label="راهنمای مجوزات"
                                              onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-right">
                                            <?php renderMenuPermissionTree(); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card custom-card border-0 shadow-sm" id="personnel-documents">
                                    <div class="card-header text-white font-weight-bold" style="background-color:#00897b;">مدارک و سوابق همکار</div>
                                    <div class="card-body">
                                        <div class="alert alert-light border">فرمت‌های مجاز: PDF، تصویر، Word — حداکثر حجم هر فایل ۱۰ مگابایت</div>
                                        <?php RenderAdminDocumentUploadFields(); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12 d-flex justify-content-start gap-2">
                                <button type="submit" class="btn btn-success px-4" name="save">
                                    <i class="fa fa-save"></i> ذخیره تنظیمات و ایجاد کارمند
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    <?php } else {
        $user = $_GET['edit'];
        $retadmin = retAdmin($user);
        if ($retadmin == NULL) {
            echo '<script>window.location.href="listadmin.php" </script>';
        }
        ?>

        <div class="main-content side-content pt-0" dir="rtl">
            <div class="container-fluid">
                <div class="inner-body">

                    <!-- Success Alert Block -->
                    <?php if (isset($accept)) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class="mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?php echo $accept ?>
                        </div>
                    <?php } ?>

                    <!-- CSRF Token Generation -->
                    <?php
                    $hash = bin2hex(random_bytes(32));
                    $_SESSION['token'] = $hash;
                    ?>

                    <form method="post" enctype="multipart/form-data" data-parsley-validate="">
                        <!-- Hidden CSRF Token Input -->
                        <input class="form-control" name="token" type="hidden" value="<?php echo $hash ?>">

                        <div class="row row-sm">

                            <!-- RIGHT COLUMN: General Information (اطلاعات عمومی) -->
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex justify-content-between align-items-center" style="background-color: #5c6bc0;">
                                        <span class="d-flex justify-content-between align-items-center">اطلاعات عمومی
                                           <span class="menu-info-trigger " style="color: white;"
                                                 data-tooltip="در این بخش می‌توانید با ویرایش اطلاعات عمومی، تعیین نقش، سطوح دسترسی و سایر تنظیمات، حساب کاربری کارمند جدید را در سامانه ایجاد کنید تا امکان ورود و استفاده از بخش‌های مجاز برای او فراهم شود. همچنین در همین قسمت، اطلاعات ورود شامل نام کاربری و رمز عبور مشخص می‌گردد؛ لطفاً توجه داشته باشید که این اطلاعات به‌درستی ثبت شده و به صورت امن تنها در اختیار شخص مجاز قرار گیرد."
                                                 aria-label="راهنمای کامل ایجاد کارمند و اطلاعات ورود"
                                                 onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fe fe-info fs-4"></i>
                                    </span>

                                        </span>
                                        <span class="badge badge-warning p-2" style="font-size: 0.9rem;">
                                    <?php echo $retadmin->fname . ' ' . $retadmin->lname ?>
                                </span>
                                    </div>
                                    <div class="card-body">

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="ename" aria-describedby="basic-addon2" type="text" required value="<?php echo $retadmin->fname ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نام خانوادگی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="efamili" aria-describedby="basic-addon2" type="text" required value="<?php echo $retadmin->lname ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام کاربری <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="eusername" aria-describedby="basic-addon2" type="text" required disabled value="<?php echo $retadmin->username ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">رمزعبور <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="epassword" aria-describedby="basic-addon2" type="password" required value="<?php echo $retadmin->password ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">داخلی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="edakheli" aria-describedby="basic-addon2" type="number" required disabled value="<?php echo $retadmin->dakheli ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">شماره موبایل</label>
                                                <input class="form-control" name="emob" aria-describedby="basic-addon2" type="text" value="<?php echo $retadmin->mobile ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">جنسیت <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="egender" required>
                                                    <option <?php if ($retadmin->gender == 'خانم') { echo 'selected'; } ?> value="خانم">خانم</option>
                                                    <option <?php if ($retadmin->gender == 'آقای') { echo 'selected'; } ?> value="آقای">آقا</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نوع دسترسی اتاق مجازی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="skyroom" required>
                                                    <option value="1" <?php if ($retadmin->per_skyroom == '1') { echo 'selected'; } ?>>شرکت کننده</option>
                                                    <option value="2" <?php if ($retadmin->per_skyroom == '2') { echo 'selected'; } ?>>ارائه دهنده</option>
                                                    <option value="3" <?php if ($retadmin->per_skyroom == '3') { echo 'selected'; } ?>>اپراتور</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">سطح دسترسی سامانه</label>
                                                <select class="form-control select2" name="eadminrole">
                                                    <option value="" <?php if (empty($retadmin->adminrole)) { echo 'selected'; } ?>>— بدون نقش —</option>
                                                    <?php if ($_SESSION['admin']['adminrole'] == 0) { ?>
                                                        <option value="0" <?php if ($retadmin->adminrole == '0') { echo 'selected'; } ?>>مدیر</option>
                                                        <option value="51" <?php if ($retadmin->adminrole == '51') { echo 'selected'; } ?>>معاونت اجرایی</option>
                                                    <?php } ?>
                                                    <option value="52" <?php if ($retadmin->adminrole == '52') { echo 'selected'; } ?>>معاونت آموزش</option>
                                                    <option value="53" <?php if ($retadmin->adminrole == '53') { echo 'selected'; } ?>>معاونت فروش</option>
                                                    <option value="54" <?php if ($retadmin->adminrole == '54') { echo 'selected'; } ?>>معاونت فنی</option>
                                                    <option value="55" <?php if ($retadmin->adminrole == '55') { echo 'selected'; } ?>>معاونت مالی</option>
                                                    <option value="2" <?php if ($retadmin->adminrole == '2') { echo 'selected'; } ?>>معاونت میانی</option>
                                                    <option value="6" <?php if ($retadmin->adminrole == '6') { echo 'selected'; } ?>>کارشناس فروش</option>
                                                    <option value="1" <?php if ($retadmin->adminrole == '1') { echo 'selected'; } ?>>پشتیبان</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">سمت سازمانی مدرسه <span class="tx-danger">*</span></label>
                                                <select class="form-control" name="eschool_role_id" required>
                                                    <option value="">— انتخاب سمت —</option>
                                                    <?php foreach ($schoolRoles as $schoolRole) { ?>
                                                        <option value="<?= (int)$schoolRole->id ?>"
                                                                data-role-key="<?= htmlspecialchars($schoolRole->role_key) ?>"
                                                                data-course-scope="<?= in_array((string)$schoolRole->role_key, $courseScopedRoleKeys, true) ? '1' : '0' ?>"
                                                            <?php if ((int)($retadmin->school_role_id ?? 0) === (int)$schoolRole->id) { echo 'selected'; } ?>>
                                                            <?= htmlspecialchars($schoolRole->title) ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-3 staff-course-scope d-none" data-mode="edit">
                                            <div class="col-12">
                                                <label class="form-label">پایه‌های قابل دسترسی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2 staff-course-select" name="ecourse_codes[]" multiple>
                                                    <?php foreach ($assignableCourses as $course) { ?>
                                                        <option value="<?= (int)$course->codecourse ?>" <?= in_array((int)$course->codecourse, $editAssignedCourseCodes, true) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($course->coursename) ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <small class="text-muted">دسترسی معاون پایه و مشاور به بخش پایه‌ها، فقط به موارد انتخاب‌شده محدود می‌شود.</small>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6 d-flex align-items-center pt-3">
                                                <label class="ckbox">
                                                    <input name="eactivate" type="checkbox" <?php if ($retadmin->adminactive == 1) { echo 'checked'; } ?>>
                                                    <span>فعالسازی حساب</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">آدرس عکس</label>
                                            <input class="form-control" name="epic" aria-describedby="basic-addon2" type="text" value="<?php echo $retadmin->adminpic ?>" placeholder="http://yourdomain.ir/wp-content/uploads/3.jpg">
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- LEFT COLUMN: Permissions (دسترسى‌ها و نقش‌ها) -->
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm permission-card">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-content-center justify-content-between" style="background-color: #e91e63;">
                                        دسترسی‌ها و نقش‌ها
                                        <div class="d-flex align-items-center" style="gap:12px;">
                                            <div class="permission-tree-actions">
                                                <button type="button" class="permission-tree-action permission-tree-expand-all">باز کردن همه</button>
                                                <button type="button" class="permission-tree-action permission-tree-collapse-all">بستن همه</button>
                                            </div>
                                            <label class="mb-0 d-flex align-items-center" style="gap:6px;cursor:pointer;">
                                                <input type="checkbox" class="permission-select-all">
                                                انتخاب همه
                                            </label>
                                            <span class="menu-info-trigger" style="color: white;"
                                              data-tooltip="در این بخش می‌توانید مجوزها و سطح دسترسی کارمند را تعیین کنید.با فعال کردن و یا غیرفعال کردن هر بخش،مجوز دسترسی و یا عدم دسترسی کارمندان به آن بخش داده می شود."
                                              aria-label="راهنمای مجوزات"
                                              onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-right">
                                            <?php renderMenuPermissionTree($retadmin); ?>
                                            <?php renderLegacyPermissionShadowFields($employeePermissionColumns, $retadmin); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card custom-card border-0 shadow-sm" id="personnel-documents">
                                    <div class="card-header text-white font-weight-bold" style="background-color:#00897b;">مدارک و سوابق همکار</div>
                                    <div class="card-body">
                                        <?php if ($documentUploadErrors): ?>
                                            <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $documentUploadErrors)) ?></div>
                                        <?php endif; ?>
                                        <div class="alert alert-light border">فایل جدید فقط همان مدرک قبلی را جایگزین می‌کند. فرمت‌های مجاز: PDF، تصویر، Word</div>
                                        <?php RenderAdminDocumentUploadFields($retadmin); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12 d-flex justify-content-start gap-2">
                                <button type="submit" class="btn btn-warning px-4" name="esave">
                                    <i class="fa fa-save"></i> ذخیره تنظیمات و ویرایش کارمند
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    <?php } ?>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.permission-card').forEach(function (card) {
                const selectAll = card.querySelector('.permission-select-all');
                const permissions = Array.from(card.querySelectorAll('.menu-permission-check'));
                if (!selectAll || permissions.length === 0) return;
                const nodes = Array.from(card.querySelectorAll('[data-menu-node]'));

                function refreshSelectAll() {
                    const checkedCount = permissions.filter(function (permission) {
                        return permission.checked;
                    }).length;
                    selectAll.checked = checkedCount === permissions.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < permissions.length;
                }

                function refreshHierarchy() {
                    nodes.slice().reverse().forEach(function (node) {
                        const own = node.querySelector(':scope > .menu-permission-row > .menu-permission-check');
                        const descendants = Array.from(node.querySelectorAll(':scope > .menu-permission-children input.menu-permission-check'));
                        if (!own || descendants.length === 0) return;
                        const checked = descendants.filter(function (item) { return item.checked; }).length;
                        own.checked = checked === descendants.length;
                        own.indeterminate = checked > 0 && checked < descendants.length;
                    });
                    refreshSelectAll();
                }

                function setExpanded(node, expanded) {
                    const children = node.querySelector(':scope > .menu-permission-children');
                    const toggle = node.querySelector(':scope > .menu-permission-row > .menu-permission-toggle');
                    const name = node.querySelector(':scope > .menu-permission-row > .menu-permission-name');
                    if (!children || !toggle) return;
                    children.hidden = !expanded;
                    node.classList.toggle('is-collapsed', !expanded);
                    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    if (name) name.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                }

                nodes.forEach(function (node) {
                    const own = node.querySelector(':scope > .menu-permission-row > .menu-permission-check');
                    const descendants = Array.from(node.querySelectorAll(':scope > .menu-permission-children input.menu-permission-check'));
                    const toggle = node.querySelector(':scope > .menu-permission-row > .menu-permission-toggle');
                    const name = node.querySelector(':scope > .menu-permission-row > .menu-permission-name');

                    if (own && descendants.length > 0) {
                        own.addEventListener('change', function () {
                            descendants.forEach(function (item) {
                                item.checked = own.checked;
                                item.indeterminate = false;
                            });
                        });
                    }
                    if (toggle) {
                        toggle.addEventListener('click', function () {
                            setExpanded(node, toggle.getAttribute('aria-expanded') !== 'true');
                        });
                    }
                    if (name) {
                        name.addEventListener('click', function () {
                            setExpanded(node, name.getAttribute('aria-expanded') !== 'true');
                        });
                    }
                });

                selectAll.addEventListener('change', function () {
                    const shouldCheck = selectAll.checked;
                    permissions.forEach(function (permission) {
                        permission.checked = shouldCheck;
                        permission.indeterminate = false;
                    });
                    refreshHierarchy();
                });

                card.addEventListener('change', function (event) {
                    if (event.target.classList.contains('menu-permission-check')) refreshHierarchy();
                });

                const expandAll = card.querySelector('.permission-tree-expand-all');
                const collapseAll = card.querySelector('.permission-tree-collapse-all');
                if (expandAll) expandAll.addEventListener('click', function () {
                    nodes.forEach(function (node) { setExpanded(node, true); });
                });
                if (collapseAll) collapseAll.addEventListener('click', function () {
                    nodes.forEach(function (node) { setExpanded(node, false); });
                });

                refreshHierarchy();
            });

            function bindCourseScope(roleSelectName, mode) {
                const roleSelect = document.querySelector('select[name="' + roleSelectName + '"]');
                const wrapper = document.querySelector('.staff-course-scope[data-mode="' + mode + '"]');
                if (!roleSelect || !wrapper) return;
                const courseSelect = wrapper.querySelector('.staff-course-select');

                function updateVisibility() {
                    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
                    const requiresCourses = selectedOption && selectedOption.dataset.courseScope === '1';
                    wrapper.classList.toggle('d-none', !requiresCourses);
                    if (courseSelect) {
                        courseSelect.required = requiresCourses;
                    }
                }

                roleSelect.addEventListener('change', updateVisibility);
                updateVisibility();
            }

            bindCourseScope('school_role_id', 'create');
            bindCourseScope('eschool_role_id', 'edit');

            document.querySelectorAll('[data-permission-tree]').forEach(function (tree) {
                const parent = tree.querySelector('.permission-parent');
                const children = Array.from(tree.querySelectorAll('.permission-tree-children input[type="checkbox"]'));
                if (!parent || children.length === 0) return;
                const refresh = function () {
                    const selected = children.filter(function (item) { return item.checked; }).length;
                    parent.checked = selected === children.length;
                    parent.indeterminate = selected > 0 && selected < children.length;
                };
                parent.addEventListener('change', function () {
                    children.forEach(function (item) { item.checked = parent.checked; });
                    parent.indeterminate = false;
                });
                children.forEach(function (item) { item.addEventListener('change', refresh); });
                refresh();
            });

            function bindSchoolRoleDefaults(roleSelectName, adminRoleName, isEdit) {
                const roleSelect = document.querySelector('select[name="' + roleSelectName + '"]');
                const adminRole = document.querySelector('select[name="' + adminRoleName + '"]');
                if (!roleSelect) return;

                roleSelect.addEventListener('change', function () {
                    const option = roleSelect.options[roleSelect.selectedIndex];
                    const roleKey = option ? option.dataset.roleKey : '';
                    const prefix = isEdit ? 'e' : '';
                    const setChecked = function (name, checked) {
                        const input = document.querySelector('input[name="' + prefix + name + '"]');
                        if (input) input.checked = checked;
                    };

                    if (roleKey === 'counselor') {
                        if (adminRole) adminRole.value = '1';
                        setChecked('menu-bill', false);
                        setChecked('permission_monthly_payment', false);
                    } else if (roleKey === 'technical_deputy') {
                        if (adminRole) adminRole.value = '54';
                        setChecked('permission_assigned_tasks', true);
                    } else if (roleKey === 'financial_deputy') {
                        if (adminRole) adminRole.value = '55';
                        setChecked('menu-bill', true);
                        setChecked('permission_staff_view', true);
                        setChecked('permission_staff_documents', true);
                        setChecked('permission_insurance', true);
                        setChecked('permission_monthly_payment', true);
                    }
                    if (adminRole && window.jQuery) {
                        jQuery(adminRole).trigger('change.select2');
                    }
                });
            }

            bindSchoolRoleDefaults('school_role_id', 'adminrole', false);
            bindSchoolRoleDefaults('eschool_role_id', 'eadminrole', true);
        });
    </script>


</body>
</html>

