<?php
/* تیکت‌ها: ticket boxes, referrals and reactivation requests. */

$box = static fn(AdminLegacyAccount $a, string $code, string $flag): bool =>
    $a->perm($flag) && ($a->tree ? $a->oldSee($code) : true);
$vs = 'permision_viewsupport';

return [
'nodes' => [
    ['code'=>'tickets','label'=>'تیکت‌ها','paths'=>['admin/Tickets/response.php'],'children'=>[
        ['code'=>'tickets.reactivate-requests','label'=>'درخواست‌های بازگردانی ثبت‌نام موقت','path'=>'admin/notifications/reactivate_tickets.php','children'=>[
            ['code'=>'tickets.reactivate-requests.own','label'=>'درخواست‌های ثبت‌شده یا ارجاع‌شده به خود کاربر'],
            ['code'=>'tickets.reactivate-requests.all','label'=>'درخواست‌های همهٔ کارشناسان'],
            ['code'=>'tickets.reactivate-requests.status','label'=>'تأیید یا رد درخواست'],
        ]],
        ['code'=>'tickets.referred','label'=>'منتظر ارجاع','path'=>'admin/Tickets/Refer.php','children'=>[
            ['code'=>'tickets.referred.forward','label'=>'ارجاع تیکت','path'=>'admin/Tickets/Refer-forward.php'],
            ['code'=>'tickets.referred.close','label'=>'بستن تیکت'],
        ]],
        ['code'=>'tickets.inbox','label'=>'صندوق ورودی','path'=>'admin/Tickets/inbox.php','children'=>[
            ['code'=>'tickets.inbox.reply','label'=>'پاسخ به تیکت'],
            ['code'=>'tickets.inbox.status','label'=>'تغییر وضعیت تیکت'],
        ]],
        ['code'=>'tickets.emergency','label'=>'تیکت‌های اورژانسی','path'=>'admin/Tickets/emergency.php','children'=>[
            ['code'=>'tickets.emergency.close','label'=>'بستن تیکت'],
        ]],
        ['code'=>'tickets.open','label'=>'کارتابل تیکت‌های باز','path'=>'admin/Tickets/openTicket.php','children'=>[
            ['code'=>'tickets.open.close','label'=>'بستن تیکت'],
        ]],
        ['code'=>'tickets.all','label'=>'کارتابل همه تیکت‌ها','path'=>'admin/Tickets/allticket.php','children'=>[
            ['code'=>'tickets.all.close','label'=>'بستن تیکت'],
            ['code'=>'tickets.all.search','label'=>'جست‌وجوی تیکت'],
        ]],
    ]],
],
'convert' => [
    'tickets.reactivate-requests.own' => fn($a) => $a->scope('tickets.reactivate-requests', $a->col('administrator')) !== null,
    'tickets.reactivate-requests.all' => fn($a) => $a->scope('tickets.reactivate-requests', $a->col('administrator')) === 'all',
    'tickets.reactivate-requests.status' => fn($a) => $a->scope('tickets.reactivate-requests', $a->col('administrator')) !== null,
    'tickets.referred' => fn($a) => $box($a, 'tickets.referred', 'permision_refer'),
    'tickets.inbox@open' => fn($a) => $box($a, 'tickets.inbox', $vs),
    'tickets.inbox.reply' => fn($a) => $box($a, 'tickets.inbox', $vs) && (!$a->tree || $a->old('tickets.inbox.reply') || $a->old('tickets.inbox')),
    'tickets.inbox.status' => fn($a) => $box($a, 'tickets.inbox', $vs) && (!$a->tree || $a->old('tickets.inbox.status') || $a->old('tickets.inbox')),
    'tickets.emergency' => fn($a) => $box($a, 'tickets.emergency', $vs),
    'tickets.open' => fn($a) => $box($a, 'tickets.open', $vs),
    'tickets.all' => fn($a) => $box($a, 'tickets.all', 'permision_allticket'),
],
];
