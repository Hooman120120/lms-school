<?php
/* روابط عمومی: تماس امن (safe call), its management and auto dialer, and پیامک امن. */

$mc = static fn(AdminLegacyAccount $a, string $code): bool => $a->can($code, ['permision_managecall']);
$ac = static fn(AdminLegacyAccount $a, string $code): bool => $a->can($code, ['permision_addcall']);
$ad = static fn(AdminLegacyAccount $a, string $code): bool => $a->can($code, ['autodialer']);
$vc = static fn(AdminLegacyAccount $a, string $code): bool => $a->can($code, ['permision_viewcall']);
$sc = 'public-relations.safe-call';
$m = "$sc.manage";

return [
'nodes' => [
    ['code'=>'public-relations','label'=>'روابط عمومی','children'=>[
        ['code'=>'public-relations.safe-call','label'=>'تماس امن','path'=>'admin/Tel/safe_call.php','paths'=>['admin/Tickets/response.php'],'children'=>[
            ['code'=>'public-relations.safe-call.manage','label'=>'مدیریت کل تماس امن','path'=>'admin/Tel/managecall.php','children'=>[
                ['code'=>'public-relations.safe-call.manage.status-lists','label'=>'فهرست تماس‌ها بر اساس وضعیت','children'=>[
                    ['code'=>'public-relations.safe-call.manage.status-lists.uncalled','label'=>'تماس‌های گرفته‌نشده'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.unanswered','label'=>'عدم پاسخ'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.finished','label'=>'تماس‌های خاتمه‌یافته'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.duplicate','label'=>'مخاطبین تکراری'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.callback','label'=>'نیاز به تماس مجدد'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.wrong','label'=>'شماره‌های اشتباه'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.all','label'=>'همه مخاطبین من'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.all-operators','label'=>'مشاهدهٔ مخاطبان همهٔ کارشناسان در فهرست‌ها'],
                ]],
                ['code'=>'public-relations.safe-call.manage.groups','label'=>'مدیریت گروه‌های تماس','children'=>[
                    ['code'=>'public-relations.safe-call.manage.groups.create','label'=>'ایجاد گروه تماس جدید'],
                    ['code'=>'public-relations.safe-call.manage.groups.edit','label'=>'ویرایش گروه تماس'],
                    ['code'=>'public-relations.safe-call.manage.groups.archive','label'=>'مشاهده آرشیو گروه‌ها','path'=>'admin/Tel/tel-group-archive.php','children'=>[
                        ['code'=>'public-relations.safe-call.manage.groups.archive.export','label'=>'خروجی اکسل آرشیو تماس‌ها','path'=>'admin/Tel/excel.php'],
                    ]],
                    ['code'=>'public-relations.safe-call.manage.groups.delete','label'=>'حذف گروه تماس'],
                ]],
                ['code'=>'public-relations.safe-call.manage.import','label'=>'افزودن و ورود فایل مخاطبان','children'=>[
                    ['code'=>'public-relations.safe-call.manage.import.replace','label'=>'حذف مخاطبان قبلی گروه هنگام ورود فایل'],
                ]],
                ['code'=>'public-relations.safe-call.manage.total-statistics','label'=>'آمار کلی تماس‌ها','children'=>[
                    ['code'=>'public-relations.safe-call.manage.total-statistics.export','label'=>'خروجی اکسل گزارش گروه'],
                    ['code'=>'public-relations.safe-call.manage.total-statistics.delete','label'=>'حذف گروه و گزارش‌های آن'],
                ]],
                ['code'=>'public-relations.safe-call.manage.operator-statistics','label'=>'آمار انفرادی تماس‌ها','paths'=>['admin/Tel/operator-call-groups.php'],'children'=>[
                    ['code'=>'public-relations.safe-call.manage.operator-statistics.breakdown','label'=>'آمار کارشناس به تفکیک گروه'],
                    ['code'=>'public-relations.safe-call.manage.operator-statistics.export','label'=>'خروجی اکسل کارشناس'],
                ]],
                ['code'=>'public-relations.safe-call.manage.contacts','label'=>'مدیریت و گزارش مخاطبان','children'=>[
                    ['code'=>'public-relations.safe-call.manage.contacts.view','label'=>'مشاهده جزئیات مخاطب','path'=>'admin/Tel/de-view.php'],
                    ['code'=>'public-relations.safe-call.manage.contacts.report','label'=>'مشاهده گزارش مخاطب','path'=>'admin/Tel/re-view.php','paths'=>['admin/Tel/telreport.php']],
                    ['code'=>'public-relations.safe-call.manage.contacts.edit','label'=>'ویرایش جزئیات مخاطب','path'=>'admin/Tel/de-edit.php'],
                    ['code'=>'public-relations.safe-call.manage.contacts.delete','label'=>'حذف مخاطب'],
                ]],
                ['code'=>'public-relations.safe-call.manage.phone-search','label'=>'جست‌وجوی تلفن و بررسی مخاطب'],
                ['code'=>'public-relations.safe-call.manage.export','label'=>'خروجی اکسل فهرست‌های تماس','path'=>'admin/Tel/exportTel.php'],
            ]],
            ['code'=>'public-relations.safe-call.calling','label'=>'برقراری تماس و ثبت نتیجه','path'=>'admin/Tel/calling.php','children'=>[
                ['code'=>'public-relations.safe-call.calling.result','label'=>'ثبت نتیجه و توضیح تماس'],
                ['code'=>'public-relations.safe-call.calling.sms','label'=>'ارسال پیامک به مخاطب'],
            ]],
            ['code'=>'public-relations.safe-call.exchange','label'=>'مرکز تبادل تماس','path'=>'admin/Tel/hamtel.php','children'=>[
                ['code'=>'public-relations.safe-call.exchange.move','label'=>'انتقال مخاطبان انتخاب‌شده'],
                ['code'=>'public-relations.safe-call.exchange.group-to-operator','label'=>'انتقال گروه به کارشناس'],
                ['code'=>'public-relations.safe-call.exchange.group-to-group','label'=>'انتقال گروه به گروه'],
            ]],
            ['code'=>'public-relations.safe-call.assignment','label'=>'تخصیص شماره به کارشناس','path'=>'admin/Tel/assign-phone.php','children'=>[
                ['code'=>'public-relations.safe-call.assignment.assign','label'=>'ثبت تخصیص شماره‌ها'],
            ]],
            ['code'=>'public-relations.safe-call.reports','label'=>'گزارش تماس و فایل صوتی','path'=>'admin/Tel/reportcall.php','children'=>[
                ['code'=>'public-relations.safe-call.reports.search','label'=>'جست‌وجو و فیلتر گزارش تماس'],
                ['code'=>'public-relations.safe-call.reports.download','label'=>'دانلود فایل صوتی تماس'],
            ]],
            ['code'=>'public-relations.safe-call.autodialer','label'=>'تماس خودکار (Auto Dialer)','children'=>[
                ['code'=>'public-relations.safe-call.autodialer.contacts','label'=>'مدیریت مخاطب','path'=>'admin/autodialer/contact-management.php','children'=>[
                    ['code'=>'public-relations.safe-call.autodialer.contacts.create','label'=>'ثبت مخاطب'],
                    ['code'=>'public-relations.safe-call.autodialer.contacts.delete','label'=>'حذف مخاطب'],
                ]],
                ['code'=>'public-relations.safe-call.autodialer.blacklist','label'=>'لیست سیاه','path'=>'admin/autodialer/autodialer-black-list.php','children'=>[
                    ['code'=>'public-relations.safe-call.autodialer.blacklist.create','label'=>'افزودن شماره به لیست سیاه'],
                    ['code'=>'public-relations.safe-call.autodialer.blacklist.delete','label'=>'حذف شماره از لیست سیاه'],
                ]],
                ['code'=>'public-relations.safe-call.autodialer.groups','label'=>'مدیریت گروه مخاطب','path'=>'admin/autodialer/group-management.php','children'=>[
                    ['code'=>'public-relations.safe-call.autodialer.groups.create','label'=>'ساخت گروه'],
                    ['code'=>'public-relations.safe-call.autodialer.groups.delete','label'=>'حذف گروه'],
                ]],
                ['code'=>'public-relations.safe-call.autodialer.voices','label'=>'مدیریت فایل صوتی','path'=>'admin/autodialer/voice-management.php','paths'=>['admin/autodialer/voice_stream.php'],'children'=>[
                    ['code'=>'public-relations.safe-call.autodialer.voices.create','label'=>'افزودن فایل صوتی'],
                    ['code'=>'public-relations.safe-call.autodialer.voices.delete','label'=>'حذف فایل صوتی'],
                ]],
                ['code'=>'public-relations.safe-call.autodialer.campaigns','label'=>'مدیریت کمپین','path'=>'admin/autodialer/campaign-management.php','children'=>[
                    ['code'=>'public-relations.safe-call.autodialer.campaigns.create','label'=>'ثبت کمپین'],
                    ['code'=>'public-relations.safe-call.autodialer.campaigns.delete','label'=>'حذف کمپین'],
                ]],
                ['code'=>'public-relations.safe-call.autodialer.reports','label'=>'گزارش تماس خودکار','path'=>'admin/autodialer/autodialer-log-report.php'],
            ]],
        ]],
        ['code'=>'public-relations.safe-sms','label'=>'پیامک امن','path'=>'admin/Tel/safe_sms.php','children'=>[
            ['code'=>'public-relations.safe-sms.credit','label'=>'مشاهده اعتبار سامانه پیامک'],
            ['code'=>'public-relations.safe-sms.search','label'=>'جست‌وجو و گزارش پیامک‌ها'],
            ['code'=>'public-relations.safe-sms.export','label'=>'خروجی اکسل پیامک‌ها'],
            ['code'=>'public-relations.safe-sms.send','label'=>'ارسال پیامک گروهی','path'=>'admin/Tel/bulk_sms.php','paths'=>['admin/Tel/ajax/get_course_students_count.php', 'admin/Tel/ajax/get_tel_group_count.php']],
        ]],
        ['code'=>'public-relations.call-popup','label'=>'پنجرهٔ تماس ورودی (نمایش تماس‌گیرنده و ثبت نتیجه)','paths'=>['admin/call_popup.php', 'admin/call_status.php']],
    ]],
],
'convert' => [
    "$sc@open" => fn($a) => $vc($a, $sc),
    "$m@open" => fn($a) => $mc($a, $m),
    "$m.status-lists.uncalled" => fn($a) => $mc($a, "$m.status-lists.uncalled"),
    "$m.status-lists.unanswered" => fn($a) => $mc($a, "$m.status-lists.unanswered"),
    "$m.status-lists.finished" => fn($a) => $mc($a, "$m.status-lists.finished"),
    "$m.status-lists.duplicate" => fn($a) => $mc($a, "$m.status-lists.duplicate"),
    "$m.status-lists.callback" => fn($a) => $mc($a, "$m.status-lists.callback"),
    "$m.status-lists.wrong" => fn($a) => $mc($a, "$m.status-lists.wrong"),
    "$m.status-lists.all" => fn($a) => $mc($a, "$m.status-lists.all"),
    "$m.status-lists.all-operators" => fn($a) => $mc($a, "$m.status-lists") && $a->perm('administrator'),
    "$m.groups.create" => fn($a) => $ac($a, "$m.groups.create"),
    "$m.groups.edit" => fn($a) => $ac($a, "$m.groups.edit"),
    "$m.groups.delete" => fn($a) => $ac($a, "$m.groups.delete"),
    "$m.groups.archive@open" => fn($a) => $ac($a, "$m.groups.archive") || $mc($a, 'public-relations.safe-call.assignment'),
    "$m.groups.archive.export" => fn($a) => $a->perm('permision_bill'),
    "$m.import" => fn($a) => $ac($a, "$m.import"),
    "$m.total-statistics.export" => fn($a) => $mc($a, "$m.total-statistics.export"),
    "$m.total-statistics.delete" => fn($a) => $mc($a, "$m.total-statistics.delete"),
    "$m.total-statistics@open" => fn($a) => $mc($a, "$m.total-statistics"),
    "$m.operator-statistics.breakdown" => fn($a) => $mc($a, "$m.operator-statistics.breakdown") || $mc($a, "$m.operator-statistics.export") || ($a->tree && $a->old("$m.operator-statistics")),
    "$m.operator-statistics.export" => fn($a) => $mc($a, "$m.operator-statistics.export"),
    "$m.operator-statistics@open" => fn($a) => $mc($a, "$m.operator-statistics"),
    "$m.contacts.view" => fn($a) => $mc($a, "$m.contacts.view"),
    "$m.contacts.report" => fn($a) => $mc($a, "$m.contacts.report") || $a->can("$m.contacts.report", ['permision_viewcall']),
    "$m.contacts.edit" => fn($a) => $mc($a, "$m.contacts.edit"),
    "$m.contacts.delete" => fn($a) => $mc($a, "$m.contacts.delete"),
    "$m.phone-search" => fn($a) => $mc($a, "$m.phone-search"),
    "$m.export" => fn($a) => $mc($a, "$m.total-statistics.export") || $mc($a, "$m.operator-statistics.export"),
    "$sc.calling" => fn($a) => $a->perm('permision_viewcall'),
    "$sc.exchange" => fn($a) => $mc($a, "$sc.exchange"),
    "$sc.assignment" => fn($a) => $mc($a, "$sc.assignment"),
    "$sc.reports" => fn($a) => $a->can("$sc.reports", ['permision_viewcall', 'reportcall_permission']),
    "$sc.autodialer.contacts" => fn($a) => $ad($a, "$sc.autodialer.contacts"),
    "$sc.autodialer.blacklist" => fn($a) => $ad($a, "$sc.autodialer.blacklist"),
    "$sc.autodialer.groups" => fn($a) => $ad($a, "$sc.autodialer.groups"),
    "$sc.autodialer.voices" => fn($a) => $ad($a, "$sc.autodialer.voices"),
    "$sc.autodialer.campaigns" => fn($a) => $ad($a, "$sc.autodialer.campaigns"),
    "$sc.autodialer.reports" => fn($a) => false, // manager only before
    'public-relations.safe-sms@open' => fn($a) => $vc($a, 'public-relations.safe-sms'),
    'public-relations.safe-sms.credit' => fn($a) => $vc($a, 'public-relations.safe-sms.credit'),
    'public-relations.safe-sms.search' => fn($a) => $vc($a, 'public-relations.safe-sms'),
    'public-relations.safe-sms.export' => fn($a) => $vc($a, 'public-relations.safe-sms'),
    'public-relations.safe-sms.send' => fn($a) => $a->perm('permision_viewcall') && $vc($a, 'public-relations.safe-sms'),
    'public-relations.call-popup' => fn($a) => !$a->isTeacher(),
],
];
