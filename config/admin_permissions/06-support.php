<?php
/* پشتیبانی: the student support desk (suporting.php) and the supporter-manager reports. */

$ms = static fn(AdminLegacyAccount $a): bool => $a->perm('permision_manage_suporter');
$xl = static fn(AdminLegacyAccount $a): bool => $a->col('excel_permission');
$S = 'support.desk';
$R = 'support.reports';

return [
'nodes' => [
    ['code'=>'support','label'=>'پشتیبانی دانش‌آموزان','labels'=>['institute'=>'پشتیبانی فراگیران'],'children'=>[
        ['code'=>$S,'label'=>'میز پشتیبانی دانش‌آموز','path'=>'admin/panel/suporting.php','children'=>[
            ['code'=>"$S.calls",'label'=>'تماس با دانش‌آموز و والدین'],
            ['code'=>"$S.online-class",'label'=>'ورود به کلاس آنلاین فوق برنامه'],
            ['code'=>"$S.flags",'label'=>'تغییر وضعیت عمومی و ویژه'],
            ['code'=>"$S.log",'label'=>'ثبت، زمان‌بندی و حذف گزارش پشتیبانی'],
            ['code'=>"$S.messages",'label'=>'ارسال و حذف پیام در گفت‌وگو'],
            ['code'=>"$S.messages-manage",'label'=>'ویرایش و حذف پیام‌های دیگران و پیام‌های قدیمی'],
            ['code'=>"$S.sms",'label'=>'ارسال پیامک به دانش‌آموز و والدین'],
            ['code'=>"$S.suspend-request",'label'=>'درخواست تعلیق'],
            ['code'=>"$S.cancel-request",'label'=>'درخواست لغو پشتیبانی'],
            ['code'=>"$S.statuses",'label'=>'ثبت و حذف وضعیت‌های پایه، دانش‌آموز و آزمون'],
            ['code'=>"$S.support-type",'label'=>'تغییر نوع پشتیبانی'],
            ['code'=>"$S.events",'label'=>'پاسخ به رویدادهای فعال'],
            ['code'=>"$S.installments-all",'label'=>'مشاهدهٔ اقساط ثبت‌شده توسط همه'],
            ['code'=>"$S.change-supporter",'label'=>'درخواست تغییر پشتیبان','path'=>'admin/panel/requestchange.php'],
            ['code'=>"$S.quiz-results",'label'=>'نتایج آزمون‌های سامانهٔ آزمون','path'=>'admin/panel/api-viewquiz.php'],
            ['code'=>"$S.report-card",'label'=>'کارنامهٔ دانش‌آموز','path'=>'admin/panel/student_test_results.php'],
            ['code'=>"$S.payments",'label'=>'پرداخت‌های دانش‌آموز','path'=>'admin/panel/payments-popup.php'],
        ]],
        ['code'=>$R,'label'=>'گزارش‌های مدیریت پشتیبانی','children'=>[
            ['code'=>"$R.manual-colors",'label'=>'تغییر رنگ دستی دانش‌آموزان','path'=>'admin/panel/actmanualcolor.php','children'=>[
                ['code'=>"$R.manual-colors.excel",'label'=>'خروجی اکسل'],
            ]],
            ['code'=>"$R.color-list",'label'=>'فهرست رنگ‌بندی دانش‌آموزان','path'=>'admin/panel/viewlistcolor.php','children'=>[
                ['code'=>"$R.color-list.excel",'label'=>'خروجی اکسل'],
            ]],
            ['code'=>"$R.special-users",'label'=>'دانش‌آموزان ویژه','path'=>'admin/panel/special-user.php','children'=>[
                ['code'=>"$R.special-users.excel",'label'=>'خروجی اکسل'],
            ]],
            ['code'=>"$R.supporter-students",'label'=>'دانش‌آموزان هر پشتیبان و تخصیص پشتیبان','path'=>'admin/panel/suporterstudents.php','children'=>[
                ['code'=>"$R.supporter-students.assign",'label'=>'تخصیص پشتیبان'],
                ['code'=>"$R.supporter-students.excel",'label'=>'خروجی اکسل'],
            ]],
            ['code'=>"$R.deciles",'label'=>'فهرست دهک‌های ۱ تا ۴','path'=>'admin/panel/dahak_report.php'],
            ['code'=>"$R.missed-calls",'label'=>'فهرست تماس‌های بی‌پاسخ','path'=>'admin/panel/misscall_report.php'],
            ['code'=>"$R.teachers",'label'=>'فهرست اساتید و کلاس‌ها','path'=>'admin/panel/colleages.php'],
            ['code'=>"$R.other-desks",'label'=>'مشاهدهٔ میزکار کارشناسان دیگر','path'=>'admin/panel/desktop-undercover.php'],
        ]],
    ]],
],
'convert' => [
    "$S@open" => fn($a) => !$a->isTeacher(),
    "$S.messages-manage" => fn($a) => $a->perm('permision_manage_chat'),
    "$S.installments-all" => fn($a) => $a->col('installment_permission'),
    "$S.quiz-results" => fn($a) => $a->perm('permision_addcourse_student'),
    "$R.manual-colors@open" => $ms,
    "$R.manual-colors.excel" => fn($a) => $ms($a) && $xl($a),
    "$R.color-list@open" => $ms,
    "$R.color-list.excel" => fn($a) => $ms($a) && $xl($a),
    "$R.special-users@open" => $ms,
    "$R.special-users.excel" => fn($a) => $ms($a) && $xl($a),
    "$R.supporter-students@open" => fn($a) => $ms($a) || ($a->row['username'] ?? '') === 'op213',
    "$R.supporter-students.assign" => fn($a) => $ms($a) || ($a->row['username'] ?? '') === 'op213',
    "$R.supporter-students.excel" => fn($a) => ($ms($a) || ($a->row['username'] ?? '') === 'op213') && $xl($a),
    "$R.deciles" => $ms,
    "$R.missed-calls" => $ms,
    "$R.teachers" => fn($a) => false, // manager only
    "$R.other-desks" => fn($a) => $ms($a) || $a->col('can_impersonate'),
],
];
