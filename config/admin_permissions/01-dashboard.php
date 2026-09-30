<?php
/* نمایه: the dashboard, the manager and «میزکار من» desks, and the sales/support reports they open. */

// Old dashboard: tree accounts saw a part when they held it; other accounts saw everything.
$old = static fn(AdminLegacyAccount $a, string $code, bool $legacy = true): bool => $a->tree ? $a->oldSee($code) : $legacy;
// Old manager desk: its node on the tree plus the supporter-manager flag (read from dbadmin for everyone).
$manager = static fn(AdminLegacyAccount $a): bool => $old($a, 'dashboard.summary.overview.manager') && $a->perm('permision_manage_suporter');
$desk = static fn(AdminLegacyAccount $a): bool => $old($a, 'dashboard.summary.overview.desktop');
$salesLead = static fn(AdminLegacyAccount $a): bool => $a->role(0, 51, 52, 53);
// Old record scope of a sales report: own or all, from its tree node or (off the tree) the sales-lead roles.
$scope = static fn(AdminLegacyAccount $a, string $code, string $want): bool =>
    ($s = $a->scope($code, $salesLead($a))) !== null && ($want === 'own' || $s === 'all');
$b98 = 'dashboard.summary.overview.manager.page-b9857fa208';
$b25 = 'dashboard.summary.overview.manager.page-25d387505a';
$b68 = 'dashboard.summary.overview.manager.page-68e9102f5d';
$b84 = 'dashboard.summary.overview.desktop.page-8477ff94be';
$reportPage = static fn(AdminLegacyAccount $a, string $oldCode): bool => $old($a, $oldCode);

return [
'nodes' => [
    ['code'=>'dashboard','label'=>'نمایه','path'=>'admin/index.php','children'=>[
        ['code'=>'dashboard.calendar','label'=>'تقویم نمایه','children'=>[
            ['code'=>'dashboard.calendar.executive','label'=>'رویدادهای تقویم اجرایی'],
            ['code'=>'dashboard.calendar.academic','label'=>'رویدادهای تقویم آموزشی'],
        ]],
        ['code'=>'dashboard.desks','label'=>'میزهای کار','children'=>[
            ['code'=>'dashboard.desks.manager','label'=>'داشبورد مدیریتی','path'=>'admin/panel/manager.php','children'=>[
                ['code'=>'dashboard.desks.manager.overview','label'=>'بخش کل','children'=>[
                    ['code'=>'dashboard.desks.manager.overview.courses','label'=>'پایه‌های در حال اجرا','labels'=>['institute'=>'دوره‌های در حال اجرا']],
                    ['code'=>'dashboard.desks.manager.overview.staff','label'=>'همکاران'],
                    ['code'=>'dashboard.desks.manager.overview.students','label'=>'دانش‌آموزان','labels'=>['institute'=>'فراگیران']],
                    ['code'=>'dashboard.desks.manager.overview.notifications','label'=>'اعلان‌ها'],
                ]],
                ['code'=>'dashboard.desks.manager.support','label'=>'بخش پشتیبان','children'=>[
                    ['code'=>'dashboard.desks.manager.support.waiting','label'=>'دانش‌آموزان منتظر پاسخ پیام'],
                    ['code'=>'dashboard.desks.manager.support.unsupported','label'=>'پشتیبانی نشده'],
                    ['code'=>'dashboard.desks.manager.support.no-supporter','label'=>'فاقد پشتیبان'],
                    ['code'=>'dashboard.desks.manager.support.late','label'=>'تأخیر در پشتیبانی'],
                    ['code'=>'dashboard.desks.manager.support.today','label'=>'پشتیبانی‌های امروز'],
                    ['code'=>'dashboard.desks.manager.support.tomorrow','label'=>'پشتیبانی فردا'],
                    ['code'=>'dashboard.desks.manager.support.count','label'=>'تعداد پشتیبانی'],
                    ['code'=>'dashboard.desks.manager.support.colors','label'=>'تغییر رنگ دانش‌آموزان'],
                ]],
                ['code'=>'dashboard.desks.manager.sales','label'=>'بخش کارشناس فروش'],
                ['code'=>'dashboard.desks.manager.move-supporters','label'=>'جابجایی گروهی پشتیبان‌ها با فایل CSV'],
                ['code'=>'dashboard.desks.manager.activity','label'=>'فعالیت امروز','children'=>[
                    ['code'=>'dashboard.desks.manager.activity.call-report','label'=>'دکمهٔ گزارش پیشرفته تماس'],
                    ['code'=>'dashboard.desks.manager.activity.compare','label'=>'دکمهٔ مقایسهٔ گزارشات'],
                ]],
            ]],
            ['code'=>'dashboard.desks.mine','label'=>'میزکار من','path'=>'admin/panel/desktop.php','children'=>[
                ['code'=>'dashboard.desks.mine.all-students','label'=>'کل دانش‌آموزان','labels'=>['institute'=>'کل فراگیران']],
                ['code'=>'dashboard.desks.mine.my-students','label'=>'دانش‌آموزان من','labels'=>['institute'=>'فراگیران من']],
                ['code'=>'dashboard.desks.mine.sales','label'=>'کارت‌های فروش خود کارشناس (ثبت موقت، ثبت قطعی، فروش مجدد)'],
                ['code'=>'dashboard.desks.mine.notifications','label'=>'اعلان‌ها'],
                ['code'=>'dashboard.desks.mine.tomorrow','label'=>'پشتیبانی فردا'],
                ['code'=>'dashboard.desks.mine.status','label'=>'خلاصه وضعیت دانش‌آموزان من'],
                ['code'=>'dashboard.desks.mine.rejected','label'=>'اکانت‌های رد شده'],
                ['code'=>'dashboard.desks.mine.late','label'=>'تأخیر در پشتیبانی'],
                ['code'=>'dashboard.desks.mine.today','label'=>'پشتیبانی‌های امروز'],
                ['code'=>'dashboard.desks.mine.handhug','label'=>'بغل‌دستی'],
                ['code'=>'dashboard.desks.mine.send-message','label'=>'ارسال پیام یکسان به دانش‌آموزان'],
                ['code'=>'dashboard.desks.mine.activity','label'=>'فعالیت امروز'],
            ]],
        ]],
        ['code'=>'dashboard.reports','label'=>'گزارش‌های فروش و پشتیبانی','children'=>[
            ['code'=>'dashboard.reports.temp-sales','label'=>'گزارش ثبت‌نام‌های موقت فروش','path'=>'admin/panel/temp-users-sales.php','children'=>[
                ['code'=>'dashboard.reports.temp-sales.today','label'=>'ثبت‌های امروز','children'=>[
                    ['code'=>'dashboard.reports.temp-sales.today.own','label'=>'فقط ثبت‌های خود کاربر'],
                    ['code'=>'dashboard.reports.temp-sales.today.all','label'=>'ثبت‌های همهٔ کارشناسان'],
                ]],
                ['code'=>'dashboard.reports.temp-sales.period','label'=>'ثبت‌های بازهٔ سال','children'=>[
                    ['code'=>'dashboard.reports.temp-sales.period.own','label'=>'فقط ثبت‌های خود کاربر'],
                    ['code'=>'dashboard.reports.temp-sales.period.all','label'=>'ثبت‌های همهٔ کارشناسان'],
                ]],
                ['code'=>'dashboard.reports.temp-sales.pick-operator','label'=>'انتخاب کارشناس دیگر برای مشاهده'],
                ['code'=>'dashboard.reports.temp-sales.reject','label'=>'رد کردن ثبت‌نام موقت'],
                ['code'=>'dashboard.reports.temp-sales.change-expert','label'=>'درخواست تغییر کارشناس'],
            ]],
            ['code'=>'dashboard.reports.permanent-sales','label'=>'گزارش ثبت‌نام‌های قطعی فروش','path'=>'admin/panel/permanet-users-sales.php','children'=>[
                ['code'=>'dashboard.reports.permanent-sales.today','label'=>'ثبت‌های امروز','children'=>[
                    ['code'=>'dashboard.reports.permanent-sales.today.own','label'=>'فقط ثبت‌های خود کاربر'],
                    ['code'=>'dashboard.reports.permanent-sales.today.all','label'=>'ثبت‌های همهٔ کارشناسان'],
                ]],
                ['code'=>'dashboard.reports.permanent-sales.period','label'=>'ثبت‌های بازهٔ سال','children'=>[
                    ['code'=>'dashboard.reports.permanent-sales.period.own','label'=>'فقط ثبت‌های خود کاربر'],
                    ['code'=>'dashboard.reports.permanent-sales.period.all','label'=>'ثبت‌های همهٔ کارشناسان'],
                ]],
                ['code'=>'dashboard.reports.permanent-sales.pick-operator','label'=>'انتخاب کارشناس دیگر برای مشاهده'],
            ]],
            ['code'=>'dashboard.reports.reactivate','label'=>'اکانت‌های رد شده و درخواست بازگردانی','path'=>'admin/panel/reactivate_experimental.php','children'=>[
                ['code'=>'dashboard.reports.reactivate.own','label'=>'فقط اکانت‌های خود کاربر'],
                ['code'=>'dashboard.reports.reactivate.all','label'=>'اکانت‌های همهٔ کارشناسان'],
                ['code'=>'dashboard.reports.reactivate.pick-operator','label'=>'انتخاب کارشناس دیگر برای مشاهده'],
                ['code'=>'dashboard.reports.reactivate.request','label'=>'ثبت درخواست بازگردانی'],
            ]],
            ['code'=>'dashboard.reports.seller-users','label'=>'فهرست ثبت‌نام‌های فروش به تفکیک کارشناس','path'=>'admin/panel/myusers-seller.php','children'=>[
                ['code'=>'dashboard.reports.seller-users.own','label'=>'فقط ثبت‌نام‌های خود کاربر'],
                ['code'=>'dashboard.reports.seller-users.others','label'=>'ثبت‌نام‌های کارشناسان دیگر'],
            ]],
            ['code'=>'dashboard.reports.resell','label'=>'پیگیری فروش مجدد','path'=>'admin/panel/resell-students.php','children'=>[
                ['code'=>'dashboard.reports.resell.own','label'=>'فقط پیگیری‌های خود کاربر'],
                ['code'=>'dashboard.reports.resell.all','label'=>'پیگیری‌های همهٔ کارشناسان'],
                ['code'=>'dashboard.reports.resell.verify','label'=>'ثبت فروش موفق'],
                ['code'=>'dashboard.reports.resell.reject','label'=>'رد پیگیری'],
            ]],
            ['code'=>'dashboard.reports.all-users','label'=>'مدیریت همهٔ کاربران','path'=>'admin/panel/all_users_management.php'],
            ['code'=>'dashboard.reports.status-types','label'=>'گزارش کاربران بر اساس وضعیت','path'=>'admin/panel/alltypeusers.php'],
            ['code'=>'dashboard.reports.my-status','label'=>'وضعیت دانش‌آموزان من','path'=>'admin/panel/mystudent_status.php','children'=>[
                ['code'=>'dashboard.reports.my-status.colors','label'=>'رنگ‌بندی و تغییر رنگ دانش‌آموزان من','paths'=>['admin/panel/actmanualcolor_op.php', 'admin/panel/viewlistcolor_admin.php']],
            ]],
            ['code'=>'dashboard.reports.late','label'=>'پیگیری‌های عقب‌افتاده','path'=>'admin/panel/late_supports.php'],
            ['code'=>'dashboard.reports.today','label'=>'پیگیری‌های امروز','path'=>'admin/panel/today_supports.php'],
            ['code'=>'dashboard.reports.tomorrow','label'=>'پیگیری‌های فردا','path'=>'admin/panel/tomorrow_supports.php'],
            ['code'=>'dashboard.reports.lacking-support','label'=>'پایه‌های فاقد پشتیبانی','path'=>'admin/panel/lacking_support.php'],
            ['code'=>'dashboard.reports.operators-compare','label'=>'مقایسهٔ گزارشات پشتیبان‌ها','path'=>'admin/panel/operatorsreport.php'],
            ['code'=>'dashboard.reports.operator-students','label'=>'دانش‌آموزان هر پشتیبان','path'=>'admin/panel/operatorstudents.php'],
            ['code'=>'dashboard.reports.student-summary','label'=>'خلاصهٔ دانش‌آموزان هر پایه','path'=>'admin/panel/studentsummary.php'],
            ['code'=>'dashboard.reports.call-report','label'=>'گزارش پیشرفتهٔ پشتیبانی','path'=>'admin/panel/advanced-report.php'],
            ['code'=>'dashboard.reports.course-supporters','label'=>'پشتیبان‌های یک پایه','path'=>'admin/panel/suportercourses.php','children'=>[
                ['code'=>'dashboard.reports.course-supporters.excel','label'=>'خروجی اکسل'],
            ]],
        ]],
        ['code'=>'dashboard.ready-messages','label'=>'مدیریت پیام‌های آماده','path'=>'admin/setting/ready-message.php','children'=>[
            ['code'=>'dashboard.ready-messages.create','label'=>'ثبت پیام آماده'],
            ['code'=>'dashboard.ready-messages.delete','label'=>'حذف پیام آماده'],
            ['code'=>'dashboard.ready-messages.edit','label'=>'ویرایش پیام آماده','path'=>'admin/setting/edit-ready-message.php'],
        ]],
        ['code'=>'dashboard.summary','label'=>'خلاصه وضعیت','children'=>[
            ['code'=>'dashboard.summary.notifications','label'=>'دکمهٔ اعلانات (هشدار و چشمک‌زن)'],
            ['code'=>'dashboard.summary.academic-archive','label'=>'دکمهٔ بایگانی سال‌های تحصیلی','path'=>'admin/select-academic-year.php'],
            ['code'=>'dashboard.summary.cards','label'=>'کارت‌های خلاصه وضعیت','children'=>[
                ['code'=>'dashboard.summary.cards.courses','label'=>'پایه‌های در حال اجرا','labels'=>['institute'=>'دوره‌های برگزارشده'],'path'=>'admin/panel/course_detail.php'],
                ['code'=>'dashboard.summary.cards.staff','label'=>'همکاران','path'=>'admin/panel/staff_management.php'],
                ['code'=>'dashboard.summary.cards.students','label'=>'دانش‌آموزان','labels'=>['institute'=>'فراگیران کل'],'path'=>'admin/panel/academic_year_students.php'],
                ['code'=>'dashboard.summary.cards.achievements','label'=>'افتخارآفرینان کل','modes'=>['institute']],
                ['code'=>'dashboard.summary.cards.classes','label'=>'کلاس‌های برگزارشده'],
                ['code'=>'dashboard.summary.cards.quizzes','label'=>'آزمون‌های برگزارشده'],
                ['code'=>'dashboard.summary.cards.messages','label'=>'پیامک‌های ارسالی امسال','modes'=>['school']],
                ['code'=>'dashboard.summary.cards.learners-current','label'=>'فراگیران','modes'=>['institute']],
                ['code'=>'dashboard.summary.cards.announcements','label'=>'اطلاع‌رسانی‌ها','modes'=>['institute']],
            ]],
        ]],
        ['code'=>'dashboard.exam-system','label'=>'ورود به سامانه آزمون'],
        ['code'=>'dashboard.online-events','label'=>'ورود به سامانه برگزاری رویدادهای آنلاین'],
        ['code'=>'dashboard.notice-board','label'=>'تابلو اعلانات'],
        ['code'=>'dashboard.news-board','label'=>'تابلو اخبار'],
    ]],
],
'convert' => [
    'dashboard.view' => fn($a) => true, // the landing page after sign-in
    'dashboard.calendar.executive' => fn($a) => $old($a, 'dashboard.calendar.executive', !$a->perm('permission_calendar')),
    'dashboard.calendar.academic' => fn($a) => $old($a, 'dashboard.calendar.academic', !$a->perm('permission_calendar')),

    'dashboard.desks.manager@open' => $manager,
    'dashboard.desks.manager.overview' => fn($a) => $manager($a) && !$a->role(2, 3),
    'dashboard.desks.manager.support' => fn($a) => $manager($a) && !$a->role(2, 3),
    'dashboard.desks.manager.move-supporters' => fn($a) => $manager($a) && !$a->role(2, 3),
    'dashboard.desks.manager.sales' => fn($a) => $manager($a) && !$a->role(2, 3) && ($a->scope("$b98.today", $salesLead($a)) !== null
        || $a->scope("$b98.period", $salesLead($a)) !== null || $a->scope("$b25.today", $salesLead($a)) !== null
        || $a->scope("$b25.period", $salesLead($a)) !== null || $a->scope($b68, $salesLead($a)) !== null),
    'dashboard.desks.manager.activity.call-report' => fn($a) => $manager($a) && !$a->role(2, 3),

    'dashboard.desks.mine@open' => $desk,
    'dashboard.desks.mine.all-students' => fn($a) => $desk($a) && $a->role(0, 51, 52, 53),
    'dashboard.desks.mine.sales' => fn($a) => $desk($a) && $a->role(6),
    'dashboard.desks.mine.notifications' => fn($a) => $desk($a) && $a->role(0, 1, 2, 51, 52, 54),
    'dashboard.desks.mine.tomorrow' => fn($a) => $desk($a) && !$a->role(6),
    'dashboard.desks.mine.rejected' => fn($a) => $desk($a) && !$a->role(1),

    // Report pages: whoever could open them before, through either desk.
    'dashboard.reports.temp-sales.today.own' => fn($a) => $scope($a, "$b98.today", 'own') || ($a->tree && $a->old('dashboard.summary.overview.desktop.sales-temporary')),
    'dashboard.reports.temp-sales.today.all' => fn($a) => $scope($a, "$b98.today", 'all'),
    'dashboard.reports.temp-sales.period.own' => fn($a) => $scope($a, "$b98.period", 'own') || ($a->tree && $a->old('dashboard.summary.overview.desktop.sales-temporary')),
    'dashboard.reports.temp-sales.period.all' => fn($a) => $scope($a, "$b98.period", 'all'),
    'dashboard.reports.temp-sales.pick-operator' => fn($a) => $a->col('can_impersonate') && ($reportPage($a, $b98) || $reportPage($a, 'dashboard.summary.overview.desktop.sales-temporary')),
    'dashboard.reports.temp-sales.reject' => fn($a) => $reportPage($a, $b98) || $reportPage($a, 'dashboard.summary.overview.desktop.sales-temporary'),
    'dashboard.reports.temp-sales.change-expert' => fn($a) => $reportPage($a, $b98) || $reportPage($a, 'dashboard.summary.overview.desktop.sales-temporary'),
    'dashboard.reports.permanent-sales.today.own' => fn($a) => $scope($a, "$b25.today", 'own') || ($a->tree && $a->old('dashboard.summary.overview.desktop.sales-permanent')),
    'dashboard.reports.permanent-sales.today.all' => fn($a) => $scope($a, "$b25.today", 'all'),
    'dashboard.reports.permanent-sales.period.own' => fn($a) => $scope($a, "$b25.period", 'own') || ($a->tree && $a->old('dashboard.summary.overview.desktop.sales-permanent')),
    'dashboard.reports.permanent-sales.period.all' => fn($a) => $scope($a, "$b25.period", 'all'),
    'dashboard.reports.permanent-sales.pick-operator' => fn($a) => $a->col('can_impersonate') && ($reportPage($a, $b25) || $reportPage($a, 'dashboard.summary.overview.desktop.sales-permanent')),
    'dashboard.reports.reactivate.own' => fn($a) => $scope($a, $b68, 'own'),
    'dashboard.reports.reactivate.all' => fn($a) => $scope($a, $b68, 'all'),
    'dashboard.reports.reactivate.pick-operator' => fn($a) => $a->col('can_impersonate') && $scope($a, $b68, 'own'),
    'dashboard.reports.reactivate.request' => fn($a) => $scope($a, $b68, 'own'),
    'dashboard.reports.seller-users.own' => fn($a) => $a->tree ? ($a->old('dashboard.summary.overview.desktop.seller-users.own') || $a->old('dashboard.summary.overview.desktop.seller-users.others')) : true,
    'dashboard.reports.seller-users.others' => fn($a) => $a->tree ? $a->old('dashboard.summary.overview.desktop.seller-users.others') : $a->col('can_impersonate'),
    'dashboard.reports.resell.own' => fn($a) => $scope($a, $b84, 'own'),
    'dashboard.reports.resell.all' => fn($a) => $scope($a, $b84, 'all'),
    'dashboard.reports.resell.verify' => fn($a) => $scope($a, $b84, 'own'),
    'dashboard.reports.resell.reject' => fn($a) => $scope($a, $b84, 'own'),
    'dashboard.reports.all-users' => fn($a) => false, // only the manager ('*') opened it
    'dashboard.reports.status-types' => fn($a) => $reportPage($a, 'dashboard.summary.overview.desktop.page-1a5467cd1e'),
    'dashboard.reports.my-status' => fn($a) => $reportPage($a, 'dashboard.summary.overview.desktop.page-433bcbe86e'),
    'dashboard.reports.late' => fn($a) => $reportPage($a, 'dashboard.summary.overview.desktop.page-2e7b3fc155'),
    'dashboard.reports.today' => fn($a) => $reportPage($a, 'dashboard.summary.overview.desktop.page-22ec7ae990'),
    'dashboard.reports.tomorrow' => fn($a) => $reportPage($a, 'dashboard.summary.overview.desktop.page-c3f5236e5f'),
    'dashboard.reports.lacking-support' => fn($a) => $a->perm('permision_manage_suporter'),
    'dashboard.reports.operators-compare' => fn($a) => $a->perm('permision_manage_suporter'),
    'dashboard.reports.operator-students' => fn($a) => $a->perm('permision_manage_suporter'),
    'dashboard.reports.student-summary' => fn($a) => $a->perm('permision_manage_suporter'),
    'dashboard.reports.call-report' => fn($a) => $a->perm('permision_manage_suporter'),
    'dashboard.reports.course-supporters@open' => fn($a) => $a->perm('permision_manage_suporter'),
    'dashboard.reports.course-supporters.excel' => fn($a) => $a->perm('permision_manage_suporter') && $a->col('excel_permission'),

    'dashboard.ready-messages.create' => fn($a) => $a->can('dashboard.summary.overview.manager.ready-messages', ['permision_manage_chat']),
    'dashboard.ready-messages.delete' => fn($a) => $a->can('dashboard.summary.overview.manager.ready-messages', ['permision_manage_chat']),
    'dashboard.ready-messages.edit' => fn($a) => $a->can('dashboard.summary.overview.manager.ready-messages.edit', ['permision_manage_chat']),
    'dashboard.ready-messages@open' => fn($a) => $a->can('dashboard.summary.overview.manager.ready-messages', ['permision_manage_chat']),

    'dashboard.summary.notifications' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.notifications'),
    'dashboard.summary.academic-archive' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.academic-archive'),
    'dashboard.summary.cards.courses' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.cards.courses') && $a->perm('permision_manage_suporter'),
    'dashboard.summary.cards.staff' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.cards.staff') && $a->perm('permision_manage_suporter'),
    'dashboard.summary.cards.students' => fn($a) => false, // its page was manager-only
    'dashboard.summary.cards.achievements' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.cards.achievements'),
    'dashboard.summary.cards.classes' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.cards.classes'),
    'dashboard.summary.cards.quizzes' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.cards.quizzes'),
    'dashboard.summary.cards.messages' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.cards.messages'),
    'dashboard.summary.cards.learners-current' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.cards.learners-current'),
    'dashboard.summary.cards.announcements' => fn($a) => $a->tree && $a->oldSee('dashboard.summary.cards.announcements'),
    'dashboard.exam-system' => fn($a) => $a->tree && $a->oldSee('dashboard.exam-system'),
    'dashboard.online-events' => fn($a) => $a->tree && $a->oldSee('dashboard.online-events'),
    'dashboard.notice-board' => fn($a) => $old($a, 'dashboard.notice-board'),
    'dashboard.news-board' => fn($a) => $old($a, 'dashboard.news-board'),
],
];
