<?php
/* امور اجرایی: the executive and academic calendars, daily notices and news. */

$cal = static fn(AdminLegacyAccount $a): bool => $a->perm('permission_calendar');
$news = static fn(AdminLegacyAccount $a): bool => $a->perm('permission_news_notif');

return [
'nodes' => [
    ['code'=>'executive','label'=>'امور اجرایی','children'=>[
        ['code'=>'executive.calendar','label'=>'تقویم اجرایی','path'=>'admin/panel/calendar_panel.php','children'=>[
            ['code'=>'executive.calendar.create','label'=>'ایجاد رویداد'],
            ['code'=>'executive.calendar.edit','label'=>'ویرایش یا حذف رویداد'],
            ['code'=>'executive.calendar.groups','label'=>'مدیریت پایه‌های تقویم','path'=>'admin/panel/calendar_groups.php'],
            ['code'=>'executive.calendar.export','label'=>'خروجی اکسل و تاریخچهٔ رویدادها','path'=>'admin/Bill/calendar-excel.php'],
        ]],
        ['code'=>'executive.academic-calendar','label'=>'تقویم آموزشی','path'=>'admin/panel/academic-calendar.php'],
        ['code'=>'executive.day-info','label'=>'جزئیات روز و خروجی نمای تقویم','paths'=>['admin/panel/day_info.php', 'admin/panel/calendar-view-excel.php']],
        ['code'=>'executive.daily-notices','label'=>'تابلو اعلان روزانه','path'=>'admin/notifications/daily_notifications.php','children'=>[
            ['code'=>'executive.daily-notices.create','label'=>'ثبت اعلان روزانه'],
            ['code'=>'executive.daily-notices.edit','label'=>'ویرایش یا حذف اعلان روزانه'],
        ]],
        ['code'=>'executive.news','label'=>'تابلو اخبار','path'=>'admin/notifications/news_panel.php','children'=>[
            ['code'=>'executive.news.create','label'=>'ثبت خبر'],
            ['code'=>'executive.news.edit','label'=>'ویرایش یا حذف خبر'],
            ['code'=>'executive.news.status','label'=>'فعال یا غیرفعال کردن خبر'],
        ]],
    ]],
],
'convert' => [
    'executive.calendar@open' => fn($a) => $cal($a),
    'executive.calendar.create' => fn($a) => $cal($a) && $a->can('executive.calendar.create', ['permission_calendar']),
    'executive.calendar.edit' => fn($a) => $cal($a) && $a->can('executive.calendar.edit', ['permission_calendar']),
    'executive.calendar.groups' => fn($a) => $cal($a) && ($a->can('executive.calendar.create', ['permission_calendar']) || $a->can('executive.calendar.edit', ['permission_calendar'])),
    'executive.calendar.export' => fn($a) => $cal($a),
    'executive.academic-calendar' => fn($a) => $a->menu('executive.academic-calendar', true),
    'executive.day-info' => fn($a) => true, // opened from every calendar view, no check before
    'executive.daily-notices@open' => fn($a) => $news($a),
    'executive.daily-notices.create' => fn($a) => $news($a) && ($a->tree ? $a->old('executive.daily-notices.create') || $a->old('executive.daily-notices') : true),
    'executive.daily-notices.edit' => fn($a) => $news($a) && ($a->tree ? $a->old('executive.daily-notices.edit') || $a->old('executive.daily-notices') : true),
    'executive.news@open' => fn($a) => $news($a),
    'executive.news.create' => fn($a) => $news($a) && ($a->tree ? $a->old('executive.news.create') || $a->old('executive.news') : true),
    'executive.news.edit' => fn($a) => $news($a) && ($a->tree ? $a->old('executive.news.edit') || $a->old('executive.news') : true),
],
];
