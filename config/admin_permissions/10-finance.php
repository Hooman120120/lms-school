<?php
/* امور مالی: invoices, installments, statements, wallet, cards, staff payments. */

$page = static fn(AdminLegacyAccount $a, string $code, string $flag): bool =>
    $a->perm($flag) && ($a->tree ? $a->oldSee($code) : true);
$own = static fn(AdminLegacyAccount $a, string $code, bool $legacyAll): bool => $a->scope($code, $legacyAll) !== null;
$all = static fn(AdminLegacyAccount $a, string $code, bool $legacyAll): bool => $a->scope($code, $legacyAll) === 'all';
$leaf = static fn(AdminLegacyAccount $a, string $code, string $parent): bool => !$a->tree || $a->old($code) || $a->old($parent);
$inst = static fn(AdminLegacyAccount $a): bool => $a->col('installment_permission');
$anyFinance = static fn(AdminLegacyAccount $a): bool => $a->perm('permision_viewbill') || $a->perm('permision_bill')
    || $a->perm('permision_actfactor') || $a->perm('permision_notpay') || $a->col('installment_permission');
$W = 'finance.wallet-centre';

return [
'nodes' => [
    ['code'=>'finance','label'=>'امور مالی','children'=>[
        ['code'=>'finance.pending','label'=>'فاکتورهای منتظر تأیید','path'=>'admin/Bill/active-factor.php','children'=>[
            ['code'=>'finance.pending.own','label'=>'فقط فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.pending.all','label'=>'فاکتورهای ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.pending.verify','label'=>'تأیید یا رد فاکتور و ثبت واریزی متناقض','paths'=>['admin/Bill/ActivationBill.php', 'admin/Bill/Contradictory-deposit.php']],
            ['code'=>'finance.pending.receipt','label'=>'مشاهده فیش واریزی'],
            ['code'=>'finance.pending.unverify','label'=>'بازگرداندن تأیید'],
        ]],
        ['code'=>'finance.unpaid','label'=>'فاکتورهای پرداخت‌نشده','path'=>'admin/Bill/factor-notpay.php','children'=>[
            ['code'=>'finance.unpaid.own','label'=>'فقط فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.unpaid.all','label'=>'فاکتورهای ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.unpaid.installment-create','label'=>'ایجاد پروندهٔ قسط برای فاکتور پرداخت‌نشده'],
            ['code'=>'finance.unpaid.deposit','label'=>'ثبت و تأیید واریزی','path'=>'admin/Bill/include-deposit.php'],
            ['code'=>'finance.unpaid.unverify','label'=>'بازگرداندن تأیید'],
        ]],
        ['code'=>'finance.returned','label'=>'فاکتورهای مستردشده','path'=>'admin/Bill/returned.php','children'=>[
            ['code'=>'finance.returned.own','label'=>'فقط فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.returned.all','label'=>'فاکتورهای ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.returned.manage','label'=>'ثبت و تأیید واریزی یا حذف فاکتور'],
        ]],
        ['code'=>'finance.wallet','label'=>'شارژهای کیف پول منتظر تأیید','modes'=>['institute'],'path'=>'admin/Bill/waitingWallet.php','children'=>[
            ['code'=>'finance.wallet.verify','label'=>'تأیید یا رد شارژ و ثبت واریزی متناقض','paths'=>['admin/Bill/ActivationOrder.php', 'admin/Bill/Contradictory-wallet.php']],
            ['code'=>'finance.wallet.unverify','label'=>'بازگرداندن تأیید'],
        ]],
        ['code'=>$W,'label'=>'کیف پول','modes'=>['institute'],'path'=>'admin/wallet/dashboard.php','children'=>[
            ['code'=>"$W.search",'label'=>'جست‌وجوی تراکنش‌ها','path'=>'admin/wallet/searchwalletview.php','children'=>[
                ['code'=>"$W.search.verify",'label'=>'تغییر وضعیت یا لغو تراکنش'],
            ]],
            ['code'=>"$W.exchange",'label'=>'تبادل اعتبار','path'=>'admin/wallet/exchange.php'],
            ['code'=>"$W.transactions",'label'=>'فهرست تراکنش‌های یک کاربر','path'=>'admin/wallet/viewlisttransaction.php','paths'=>['admin/Bill/listTransaction.php']],
            ['code'=>"$W.withdraw",'label'=>'برداشت از کیف پول','path'=>'admin/wallet/withdraw.php'],
        ]],
        ['code'=>'finance.referrer-commission','label'=>'کارمزد معرف','path'=>'admin/finance/referrer-commission.php','children'=>[
            ['code'=>'finance.referrer-commission.save','label'=>'ثبت درصد کارمزد'],
        ]],
        ['code'=>'finance.cards','label'=>'مدیریت شماره کارت‌های بانکی','path'=>'admin/setting/credit-card.php','children'=>[
            ['code'=>'finance.cards.create','label'=>'افزودن شماره کارت'],
            ['code'=>'finance.cards.edit','label'=>'ویرایش شماره کارت'],
            ['code'=>'finance.cards.delete','label'=>'حذف شماره کارت'],
        ]],
        ['code'=>'finance.statement','label'=>'صورتحساب','path'=>'admin/Bill/SearchBillView.php','children'=>[
            ['code'=>'finance.statement.own','label'=>'فقط فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.statement.all','label'=>'فاکتورهای ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.statement.search','label'=>'جست‌وجوی فاکتور'],
            ['code'=>'finance.statement.manage','label'=>'حذف، لغو و بازگرداندن تأیید فاکتور'],
            ['code'=>'finance.statement.transfer','label'=>'انتقال فاکتور','path'=>'admin/Bill/transfer.php'],
            ['code'=>'finance.statement.wallet-column','label'=>'نمایش ستون کیف پول'],
        ]],
        ['code'=>'finance.advanced-report','label'=>'گزارش مالی پیشرفته','path'=>'admin/Bill/advanced-report.php','children'=>[
            ['code'=>'finance.advanced-report.own','label'=>'فقط گزارش فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.advanced-report.all','label'=>'گزارش فاکتورهای همهٔ ایجادکنندگان'],
            ['code'=>'finance.advanced-report.manage','label'=>'حذف، لغو و بازگرداندن تأیید فاکتور'],
            ['code'=>'finance.advanced-report.save-search','label'=>'ذخیرهٔ جست‌وجو'],
        ]],
        ['code'=>'finance.installments','label'=>'موارد اقساطی','path'=>'admin/Bill/payment.php','children'=>[
            ['code'=>'finance.installments.create','label'=>'افزودن پروندهٔ قسط'],
            ['code'=>'finance.installments.own','label'=>'فقط پرونده‌های قسط ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.installments.all','label'=>'پرونده‌های قسط ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.installments.edit','label'=>'ویرایش یا حذف پروندهٔ قسط'],
            ['code'=>'finance.installments.details','label'=>'مشاهدهٔ جزئیات و اقساط هر پرونده','path'=>'admin/Bill/paymentinstallments.php','children'=>[
                ['code'=>'finance.installments.details.manage','label'=>'ثبت پرداخت، افزودن، ویرایش و حذف قسط'],
            ]],
            ['code'=>'finance.installments.history','label'=>'تاریخچهٔ پرداخت‌ها','path'=>'admin/Bill/payment-history.php','children'=>[
                ['code'=>'finance.installments.history.manage','label'=>'ثبت، ویرایش و حذف پرداخت'],
            ]],
            ['code'=>'finance.installments.archive','label'=>'بایگانی پرداخت‌ها','path'=>'admin/Bill/payment-archive.php'],
            ['code'=>'finance.installments.export','label'=>'خروجی اکسل اقساط','path'=>'admin/Bill/payment_excel.php'],
        ]],
        ['code'=>'finance.invoice','label'=>'مشاهده و ویرایش فاکتور','children'=>[
            ['code'=>'finance.invoice.view','label'=>'مشاهدهٔ فاکتور','path'=>'admin/Bill/viewfactor.php'],
            ['code'=>'finance.invoice.edit','label'=>'ویرایش فاکتور','path'=>'admin/Bill/editfactor.php'],
        ]],
        ['code'=>'finance.exports','label'=>'مرکز خروجی‌های اکسل مالی و دانش‌آموزان','path'=>'admin/Bill/execel.php'],
        ['code'=>'finance.monthly-payments','label'=>'واریز ماهانه همکاران','path'=>'admin/finance/monthly-payments.php','children'=>[
            ['code'=>'finance.monthly-payments.manage','label'=>'ثبت و حذف واریز'],
        ]],
        ['code'=>'finance.staff-documents','label'=>'مستندات همکاران','path'=>'admin/employ/staff-documents.php','paths'=>['admin/employ/download-document.php']],
        ['code'=>'finance.insurance','label'=>'بیمه افراد','path'=>'admin/employ/insurance.php','children'=>[
            ['code'=>'finance.insurance.manage','label'=>'ثبت و حذف'],
        ]],
    ]],
],
'convert' => [
    'finance.pending@open' => fn($a) => $page($a, 'finance.pending', 'permision_actfactor'),
    'finance.pending.own' => fn($a) => $page($a, 'finance.pending', 'permision_actfactor') && $own($a, 'finance.pending', false),
    'finance.pending.all' => fn($a) => $page($a, 'finance.pending', 'permision_actfactor') && $all($a, 'finance.pending', false),
    'finance.pending.verify' => fn($a) => $a->perm('permision_actfactor') && $a->can('finance.pending.verify', ['permision_actfactor']),
    'finance.pending.receipt' => fn($a) => $page($a, 'finance.pending', 'permision_actfactor') && $leaf($a, 'finance.pending.receipt', 'finance.pending'),
    'finance.pending.unverify' => fn($a) => $page($a, 'finance.pending', 'permision_actfactor'),
    'finance.unpaid@open' => fn($a) => $page($a, 'finance.unpaid', 'permision_notpay'),
    'finance.unpaid.own' => fn($a) => $page($a, 'finance.unpaid', 'permision_notpay') && $own($a, 'finance.unpaid', $inst($a)),
    'finance.unpaid.all' => fn($a) => $page($a, 'finance.unpaid', 'permision_notpay') && $all($a, 'finance.unpaid', $inst($a)),
    'finance.unpaid.installment-create' => fn($a) => $page($a, 'finance.unpaid', 'permision_notpay') && $leaf($a, 'finance.unpaid.installment-create', 'finance.unpaid'),
    'finance.unpaid.deposit' => fn($a) => $a->perm('permision_notpay'),
    'finance.unpaid.unverify' => fn($a) => $page($a, 'finance.unpaid', 'permision_notpay'),
    'finance.returned@open' => fn($a) => $page($a, 'finance.returned', 'permision_notpay'),
    'finance.returned.own' => fn($a) => $page($a, 'finance.returned', 'permision_notpay') && $own($a, 'finance.returned', false),
    'finance.returned.all' => fn($a) => $page($a, 'finance.returned', 'permision_notpay') && $all($a, 'finance.returned', false),
    'finance.returned.manage' => fn($a) => $page($a, 'finance.returned', 'permision_notpay') && $leaf($a, 'finance.returned.manage', 'finance.returned'),
    'finance.wallet' => fn($a) => $a->perm('permision_actwallet'),
    $W => fn($a) => $a->col('permision_viewwallet'),
    'finance.referrer-commission' => fn($a) => $a->can('finance.referrer-commission', ['permision_viewbill']),
    'finance.cards@open' => fn($a) => $a->can('finance.cards', ['permision_viewbill']),
    'finance.cards.create' => fn($a) => $a->can('finance.cards', ['permision_viewbill']) && $leaf($a, 'finance.cards.create', 'finance.cards'),
    'finance.cards.edit' => fn($a) => $a->can('finance.cards', ['permision_viewbill']) && $leaf($a, 'finance.cards.edit', 'finance.cards'),
    'finance.cards.delete' => fn($a) => $a->can('finance.cards', ['permision_viewbill']) && $leaf($a, 'finance.cards.delete', 'finance.cards'),
    'finance.statement@open' => fn($a) => $page($a, 'finance.statement', 'permision_bill'),
    'finance.statement.own' => fn($a) => $page($a, 'finance.statement', 'permision_bill') && $own($a, 'finance.statement', false),
    'finance.statement.all' => fn($a) => $page($a, 'finance.statement', 'permision_bill') && $all($a, 'finance.statement', false),
    'finance.statement.transfer' => fn($a) => $a->perm('permision_bill'),
    'finance.statement.wallet-column' => fn($a) => $a->col('permision_viewwallet'),
    'finance.advanced-report@open' => fn($a) => $page($a, 'finance.advanced-report', 'permision_bill'),
    'finance.advanced-report.own' => fn($a) => $page($a, 'finance.advanced-report', 'permision_bill') && $own($a, 'finance.advanced-report', false),
    'finance.advanced-report.all' => fn($a) => $page($a, 'finance.advanced-report', 'permision_bill') && $all($a, 'finance.advanced-report', false),
    'finance.installments@open' => fn($a) => $a->tree ? $a->oldSee('finance.installments') : $a->perm('permision_viewbill'),
    'finance.installments.create' => fn($a) => $a->can('finance.installments.create', ['permision_viewbill']),
    'finance.installments.own' => fn($a) => $own($a, 'finance.installments', $inst($a)) || $a->can('finance.installments.create', ['permision_viewbill']),
    'finance.installments.all' => fn($a) => $all($a, 'finance.installments', $inst($a)),
    'finance.installments.edit' => fn($a) => ($a->tree ? $a->oldSee('finance.installments') : $a->perm('permision_viewbill')) && $leaf($a, 'finance.installments.edit', 'finance.installments'),
    'finance.installments.details@open' => fn($a) => $a->tree ? $a->oldSee('finance.installments') : $a->perm('permision_viewbill'),
    'finance.installments.details.manage' => fn($a) => $a->tree ? ($a->oldSee('finance.installments.details') || $a->old('finance.installments.edit'))
        : $a->perm('permision_viewbill'),
    'finance.installments.history' => fn($a) => $a->perm('permision_viewbill'),
    'finance.installments.archive' => fn($a) => $a->perm('permision_viewbill'),
    'finance.installments.export' => fn($a) => $inst($a),
    'finance.invoice.view' => $anyFinance,
    'finance.invoice.edit' => fn($a) => $a->perm('permision_actfactor') || $a->perm('permision_notpay') || $a->perm('permision_bill'),
    'finance.exports' => fn($a) => $a->perm('permision_bill'),
    'finance.monthly-payments' => fn($a) => $a->perm('permission_monthly_payment'),
    'finance.staff-documents' => fn($a) => $a->perm('permission_staff_documents') || $a->perm('permision_viewemploy'),
    'finance.insurance' => fn($a) => $a->perm('permission_insurance'),
],
];
