<?php
/*
 * اعلان‌ها: the school/institute notification centre (one tree, its codes kept
 * from before), and the work queues (operator, manager, deputy, sales,
 * teacher tickets, referrals) that used the dbadmin «administrator» column.
 */

$post = static fn(AdminLegacyAccount $a, string ...$keys): bool => $a->post(...$keys);
$va = static fn(AdminLegacyAccount $a): bool => $a->perm('permision_viewalert');

$centre = static function (string $p, array $modes, string $label, string $home): array {
    return ['code'=>$p,'label'=>$label,'modes'=>$modes,'path'=>$home,'children'=>[
        ['code'=>"$p.categories",'label'=>'دسته‌های اعلان','children'=>[
            ['code'=>"$p.categories.new_student",'label'=>'دانش‌آموز جدید'],
            ['code'=>"$p.categories.attendance",'label'=>'حضور و غیاب جدید'],
            ['code'=>"$p.categories.exam",'label'=>'آزمون و نمره جدید'],
            ['code'=>"$p.categories.discipline",'label'=>'مورد انضباطی جدید'],
            ['code'=>"$p.categories.executive_calendar",'label'=>'تغییر تقویم اجرایی'],
            ['code'=>"$p.categories.academic_calendar",'label'=>'تغییر تقویم آموزشی'],
            ['code'=>"$p.categories.announcement",'label'=>'اطلاعیه‌های دریافتی'],
            ['code'=>"$p.categories.circular",'label'=>'بخشنامه‌های دریافتی'],
            ['code'=>"$p.categories.special_letter",'label'=>'نامه‌های اختصاصی دریافتی'],
        ]],
        ['code'=>"$p.current",'label'=>'دریافت‌های جدید دسته‌های مجاز'],
        ['code'=>"$p.history",'label'=>'آرشیو خوانده‌شده'],
        ['code'=>"$p.open",'label'=>'مشاهدهٔ جزئیات اعلان'],
        ['code'=>"$p.mark-read",'label'=>'علامت‌گذاری اعلان به‌عنوان خوانده‌شده'],
        ['code'=>"$p.mark-all",'label'=>'خواندن همهٔ اعلان‌ها'],
        ['code'=>"$p.information",'label'=>'اعلان‌های اطلاع‌رسانی','children'=>[
            ['code'=>"$p.information.rules",'label'=>'کنترل قوانین اعلان تماس','path'=>'admin/notifications/notif_rule_management.php','children'=>[
                ['code'=>"$p.information.rules.manage",'label'=>'ثبت، فعال‌سازی و حذف قانون'],
            ]],
            ['code'=>"$p.information.tickets",'label'=>'تیکت‌های اعلان تماس','path'=>'admin/notifications/thread_list.php','paths'=>['admin/notifications/thread_view.php'],'children'=>[
                ['code'=>"$p.information.tickets.own",'label'=>'تیکت‌های دانش‌آموزان تحت پشتیبانی خود'],
                ['code'=>"$p.information.tickets.manage",'label'=>'تغییر وضعیت تیکت‌ها'],
                ['code'=>"$p.information.tickets.all",'label'=>'تیکت‌های همهٔ کارشناسان'],
            ]],
            ['code'=>"$p.information.operator",'label'=>'اعلان تماس مشاوران','labels'=>['institute'=>'اعلان تماس پشتیبان‌ها'],'path'=>'admin/notifications/notification_view.php'],
            ['code'=>"$p.information.my-calls",'label'=>'اعلان تماس دانش‌آموزان من','path'=>'admin/notifications/notifications.php'],
            ['code'=>"$p.information.homework-approval",'label'=>'تأیید تکالیف','path'=>'admin/course/homework_check.php','children'=>[
                ['code'=>"$p.information.homework-approval.review",'label'=>'بررسی و تأیید تکلیف'],
                ['code'=>"$p.information.homework-approval.all",'label'=>'تکالیف همهٔ پایه‌ها'],
                ['code'=>"$p.information.homework-approval.history",'label'=>'تاریخچهٔ بررسی تکالیف','path'=>'admin/course/homework_check_history.php','children'=>[
                    ['code'=>"$p.information.homework-approval.history.delete",'label'=>'حذف بررسی'],
                ]],
            ]],
            ['code'=>"$p.information.email",'label'=>'نمایش اعلان ایمیل‌های جدید (به‌زودی)'],
            ['code'=>"$p.information.sms",'label'=>'پیامک‌های جدید','path'=>'admin/notifications/unread_sms.php','children'=>[
                ['code'=>"$p.information.sms.manage",'label'=>'خواندن و حذف پیامک'],
                ['code'=>"$p.information.sms.export",'label'=>'خروجی اکسل'],
            ]],
        ]],
        ['code'=>"$p.financial",'label'=>'اعلان‌های مالی','children'=>[
            ['code'=>"$p.financial.invoices",'label'=>'فاکتورهای منتظر تأیید','path'=>'admin/notifications/verify_by_manager.php','children'=>[
                ['code'=>"$p.financial.invoices.verify",'label'=>'تأیید یا رد فاکتور'],
                ['code'=>"$p.financial.invoices.pick-operator",'label'=>'انتخاب کارشناس دیگر برای مشاهده'],
            ]],
            ['code'=>"$p.financial.wallet",'label'=>'کارت تأیید شارژ کیف پول'],
        ]],
    ]];
};
$school = $centre('school-notifications', ['school'], 'اعلان‌های مدرسه', 'admin/notifications/school_notifications.php');
$school['paths'] = ['admin/notifications/school_notification_list.php'];
$institute = $centre('institute-notifications', ['institute'], 'اعلان‌های آموزشگاه', 'admin/notifications/institute_notifications.php');
$institute['paths'] = ['admin/notifications/institute_notification_list.php', 'admin/notifications/school_notifications.php'];

// Page conditions of the information and financial pages (before: page checks, plus the tree node on tree accounts).
$pageRule = [
    'information.rules' => fn($a) => $va($a) || $post($a, 'manager', 'general_deputy'),
    'information.operator' => fn($a) => $va($a) || $post($a, 'manager', 'general_deputy'),
    'information.my-calls' => fn($a) => $va($a) || $a->perm('permision_suporter') || $post($a, 'manager', 'general_deputy', 'counselor'),
    'information.sms' => fn($a) => $va($a) || $post($a, 'manager', 'general_deputy', 'executive_deputy'),
    'information.tickets' => fn($a) => true,
    'financial.invoices' => fn($a) => $va($a) || $a->perm('permision_actfactor') || $post($a, 'manager', 'general_deputy'),
    'financial.wallet' => fn($a) => $a->perm('permision_actwallet'),
];
$rulesFor = static function (array $centreNode) use ($pageRule, $va, $post): array {
    $p = $centreNode['code'];
    $rules = [];
    $walk = static function (array $nodes) use (&$walk, &$rules, $p, $pageRule): void {
        foreach ($nodes as $n) {
            $code = $n['code'];
            $suffix = substr($code, strlen($p) + 1);
            $page = null;
            foreach ($pageRule as $key => $fn) if ($suffix === $key || str_starts_with($suffix, $key . '.')) $page = $fn;
            if (!empty($n['children'])) {
                $rules["$code@open"] = fn($a) => ($a->tree ? $a->oldSee($code) : true) && ($page === null || $page($a));
                $walk($n['children']);
            } else {
                $rules[$code] = fn($a) => ($a->tree ? $a->old($code) : true) && ($page === null || $page($a));
            }
        }
    };
    $walk($centreNode['children']);
    $rules["$p@open"] = fn($a) => $a->tree ? $a->oldSee($p) : true;
    // Leaves that are new here: the old page decided them.
    $rules["$p.information.rules.manage"] = fn($a) => ($a->tree ? $a->oldSee("$p.information.rules") : true) && $pageRule['information.rules']($a);
    $rules["$p.information.tickets.own"] = fn($a) => $a->perm('permision_suporter') || $post($a, 'counselor');
    $rules["$p.information.tickets.manage"] = fn($a) => $va($a) || $post($a, 'manager', 'general_deputy') || ($a->tree && $a->oldSee("$p.information.tickets"));
    $rules["$p.information.tickets.all"] = fn($a) => $post($a, 'manager', 'general_deputy')
        || (($va($a) || ($a->tree && $a->oldSee("$p.information.tickets"))) && $a->perm('permision_viewemploy'));
    $rules["$p.information.sms.manage"] = fn($a) => ($a->tree ? $a->oldSee("$p.information.sms") : true) && $pageRule['information.sms']($a);
    $rules["$p.information.sms.export"] = $rules["$p.information.sms.manage"];
    $rules["$p.financial.invoices.verify"] = fn($a) => ($a->tree ? $a->oldSee("$p.financial.invoices") : true) && $pageRule['financial.invoices']($a);
    $rules["$p.financial.invoices.pick-operator"] = fn($a) => $a->col('can_impersonate') && $pageRule['financial.invoices']($a);
    $rules["$p.information.homework-approval@open"] = fn($a) => ($a->tree ? $a->oldSee('homework-approval') : ($a->role(2) || $a->perm('administrator')))
        && !($a->perm('permision_suporter') && !$a->perm('permision_viewcourse'));
    $rules["$p.information.homework-approval.review"] = fn($a) => ($a->tree ? $a->old('homework-approval.review') || $a->old('homework-approval') : ($a->role(2) || $a->perm('administrator')))
        && !($a->perm('permision_suporter') && !$a->perm('permision_viewcourse'));
    $rules["$p.information.homework-approval.all"] = fn($a) => $a->role(0, 51, 52);
    $rules["$p.information.homework-approval.history"] = fn($a) => $a->role(0, 51, 52)
        && !($a->perm('permision_suporter') && !$a->perm('permision_viewcourse'));
    // These operations were unrestricted once their old page gate passed.
    $rules["$p.information.homework-approval.history.delete"] = $rules["$p.information.homework-approval.history"];
    return $rules;
};

$Q = 'notifications';
return [
'nodes' => [
    $school,
    $institute,
    ['code'=>$Q,'label'=>'اعلان‌ها و درخواست‌های کاری','children'=>[
        ['code'=>"$Q.all-records",'label'=>'مشاهدهٔ اعلان‌ها و درخواست‌های همهٔ کارشناسان'],
        ['code'=>"$Q.operator",'label'=>'اعلان‌های کارشناس','path'=>'admin/notifications/operator_notif.php','children'=>[
            ['code'=>"$Q.operator.new-student-tickets",'label'=>'درخواست‌های مربوط به ثبت‌نام جدید','path'=>'admin/notifications/new_student_tickets.php','children'=>[
                ['code'=>"$Q.operator.new-student-tickets.own",'label'=>'درخواست‌های ثبت‌شده یا ارجاع‌شده به خود کاربر'],
                ['code'=>"$Q.operator.new-student-tickets.all",'label'=>'درخواست‌های همهٔ کارشناسان'],
                ['code'=>"$Q.operator.new-student-tickets.status",'label'=>'تغییر وضعیت درخواست'],
            ]],
            ['code'=>"$Q.operator.operator-changes",'label'=>'اعلان‌های تغییر پشتیبان','path'=>'admin/notifications/studentoperator_changes_notif.php'],
            ['code'=>"$Q.operator.new-students",'label'=>'اعلان‌های ثبت‌نام جدید','path'=>'admin/notifications/new_students.php','children'=>[
                ['code'=>"$Q.operator.new-students.act",'label'=>'انجام شد و ثبت درخواست بررسی'],
            ]],
            ['code'=>"$Q.operator.suspension-requests",'label'=>'درخواست‌های تعلیق ثبت‌نام','path'=>'admin/notifications/suspended_student_op_ticket.php'],
            ['code'=>"$Q.operator.no-operator",'label'=>'درخواست‌های ثبت‌نام بدون پشتیبان','path'=>'admin/notifications/NoOperator_student_op_ticket.php'],
            ['code'=>"$Q.operator.high-priority",'label'=>'ثبت‌نام‌های موقت با اولویت بالا','path'=>'admin/notifications/high-priority-temp-students.php'],
            ['code'=>"$Q.operator.teachers",'label'=>'تیکت‌های استادان','path'=>'admin/notifications/teachers_notif.php','paths'=>['admin/notifications/thread_view.php'],'children'=>[
                ['code'=>"$Q.operator.teachers.create",'label'=>'ثبت تیکت استاد'],
                ['code'=>"$Q.operator.teachers.status",'label'=>'تغییر وضعیت تیکت'],
            ]],
        ]],
        ['code'=>"$Q.manager",'label'=>'اعلان‌های مدیریت','path'=>'admin/notifications/manager_notif.php'],
        ['code'=>"$Q.deputy",'label'=>'اعلان‌های معاونت','path'=>'admin/notifications/administrator_notif.php','children'=>[
            ['code'=>"$Q.deputy.executive",'label'=>'معاونت اجرایی'],
            ['code'=>"$Q.deputy.education",'label'=>'معاونت آموزشی'],
            ['code'=>"$Q.deputy.sales",'label'=>'معاونت فروش'],
            ['code'=>"$Q.deputy.technical",'label'=>'معاونت فنی'],
            ['code'=>"$Q.deputy.block-support",'label'=>'بخش پشتیبانی (تیکت‌ها، قوانین و فاکتورها)'],
            ['code'=>"$Q.deputy.block-sales",'label'=>'بخش فروش (تغییر کارشناس و برگشتی‌های CRM)'],
            ['code'=>"$Q.deputy.block-other",'label'=>'بخش سایر درخواست‌ها'],
        ]],
        ['code'=>"$Q.sales",'label'=>'اعلان‌های واحد فروش','path'=>'admin/notifications/sales_notif.php','paths'=>['admin/notifications/notif_sales.php']],
        ['code'=>"$Q.teacher-tickets",'label'=>'مدیریت تیکت‌های استادان','path'=>'admin/notifications/admin_teacher_notif.php','children'=>[
            ['code'=>"$Q.teacher-tickets.status",'label'=>'تغییر وضعیت تیکت'],
        ]],
        ['code'=>"$Q.referred",'label'=>'تیکت‌های ارجاع‌شده به من','path'=>'admin/notifications/referred_tickets.php','children'=>[
            ['code'=>"$Q.referred.act",'label'=>'پاسخ و اقدام روی ارجاع'],
        ]],
        ['code'=>"$Q.referral-history",'label'=>'تاریخچهٔ ارجاع‌ها','path'=>'admin/notifications/referral_history.php'],
        ['code'=>"$Q.admin-chat",'label'=>'گفت‌وگوی کارشناسان','path'=>'admin/notifications/admin_chat.php','children'=>[
            ['code'=>"$Q.admin-chat.send",'label'=>'ارسال پیام'],
        ]],
        ['code'=>"$Q.change-expert",'label'=>'درخواست‌های تغییر کارشناس','path'=>'admin/notifications/change_expert_reqs.php','children'=>[
            ['code'=>"$Q.change-expert.decide",'label'=>'تأیید یا رد درخواست'],
            ['code'=>"$Q.change-expert.pick-operator",'label'=>'انتخاب کارشناس دیگر برای مشاهده'],
        ]],
    ]],
],
'convert' => array_merge($rulesFor($school), $rulesFor($institute), [
    "$Q.all-records" => fn($a) => $a->col('administrator'),
    "$Q.operator@open" => fn($a) => !$a->isTeacher(),
    "$Q.operator.new-student-tickets.own" => fn($a) => $a->scope('dashboard.summary.overview.desktop.page-1938484300.page-86bedc87c2', $a->col('administrator')) !== null,
    "$Q.operator.new-student-tickets.all" => fn($a) => $a->scope('dashboard.summary.overview.desktop.page-1938484300.page-86bedc87c2', $a->col('administrator')) === 'all',
    "$Q.operator.teachers@open" => fn($a) => $a->teacher('teacher_perm_messages'),
    "$Q.manager" => fn($a) => !$a->isTeacher(),
    "$Q.deputy@open" => fn($a) => !$a->isTeacher(),
    "$Q.deputy.executive" => fn($a) => !$a->isTeacher() && (!$a->tree || $a->old('institute-notifications.deputy.executive')),
    "$Q.deputy.education" => fn($a) => !$a->isTeacher() && (!$a->tree || $a->old('institute-notifications.deputy.education')),
    "$Q.deputy.sales" => fn($a) => !$a->isTeacher() && (!$a->tree || $a->old('institute-notifications.deputy.sales')),
    "$Q.deputy.technical" => fn($a) => !$a->isTeacher() && (!$a->tree || $a->old('institute-notifications.deputy.technical')),
    "$Q.deputy.block-support" => fn($a) => $a->role(0, 51, 52),
    "$Q.deputy.block-sales" => fn($a) => $a->role(0, 51, 52, 53),
    "$Q.deputy.block-other" => fn($a) => $a->role(0, 51, 52, 53, 54),
    "$Q.sales" => fn($a) => !$a->isTeacher(),
    "$Q.teacher-tickets" => fn($a) => true,
    "$Q.referred" => fn($a) => !$a->isTeacher(),
    "$Q.referral-history" => fn($a) => !$a->isTeacher(),
    "$Q.admin-chat" => fn($a) => !$a->isTeacher(),
    "$Q.change-expert@open" => fn($a) => !$a->isTeacher(),
    "$Q.change-expert.pick-operator" => fn($a) => $a->col('can_impersonate'),
]),
];
