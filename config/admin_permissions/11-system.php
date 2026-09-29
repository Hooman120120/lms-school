<?php
/* سامانه: viewing the panel as another account, and technical logs. */

return [
'nodes' => [
    ['code'=>'system','label'=>'سامانه','children'=>[
        ['code'=>'system.impersonate','label'=>'مشاهدهٔ پنل به‌جای همکار دیگر (View as)'],
        ['code'=>'system.api-logs','label'=>'گزارش درخواست‌های سامانهٔ آزمون','path'=>'admin/course/view-api-logs.php','paths'=>['admin/course/ajax_log_details.php']],
    ]],
],
'convert' => [
    'system.impersonate' => fn($a) => $a->col('can_impersonate'),
    'system.api-logs' => fn($a) => false, // no link; managers ('*') keep it
],
];
