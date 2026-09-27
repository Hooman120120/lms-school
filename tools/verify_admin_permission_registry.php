<?php
/**
 * Regression check for the access tree. It intentionally lists the live side
 * menu destinations (not dead links inside the legacy `if (false)` block).
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
$root = dirname(__DIR__);
if (!defined('BASE_PATH')) define('BASE_PATH', $root);
require_once $root . '/inc/function.php';
$tree = AdminPermissionRegistry();
$nodes = [];
$walk = static function (array $items, ?string $parent = null) use (&$walk, &$nodes): void {
    foreach ($items as $item) {
        $code = $item['code'] ?? '';
        if ($code === '' || isset($nodes[$code])) throw new RuntimeException("Invalid or duplicate permission code: $code");
        $nodes[$code] = ['parent' => $parent, 'path' => $item['path'] ?? null, 'label' => $item['label'] ?? '', 'modes' => $item['modes'] ?? ['school', 'institute']];
        $walk($item['children'] ?? [], $code);
    }
};
$walk($tree);

$menuPaths = [
    'admin/index.php','admin/Tel/safe_call.php','admin/Tel/safe_sms.php',
    'admin/Student/add.php','admin/Student/addcourse.php','admin/Student/edit-absolute.php',
    'admin/panel/mystudent.php','admin/panel/mypersonalcourse.php','admin/panel/canceled_student_list.php','admin/panel/suspension_list.php',
    'admin/Student/registration-Course-absolute.php','admin/Student/unactive-student.php','admin/Student/canceled-student.php','admin/Student/suspended-student.php','admin/Student/canceled-op-student.php',
    'admin/Student/experimental-add.php','admin/democourse/addstudent.php','admin/Student/edit-experimental.php','admin/Student/registration-Course-experimental.php',
    'admin/panel/calendar_panel.php','admin/panel/academic-calendar.php','admin/notifications/daily_notifications.php','admin/notifications/news_panel.php',
    'admin/course/add-Course.php','admin/democourse/add-democourse.php','admin/course/Active-course.php','admin/democourse/list-democourse.php','admin/course/unactive-course.php','admin/course/add-discount.php',
    'admin/handhug/add-handhug.php','admin/handhug/handhug-management.php','admin/handhug/disabled-handhugs.php','admin/notifications/school_notifications.php',
    'admin/Tickets/Refer.php','admin/Tickets/inbox.php','admin/Tickets/emergency.php','admin/Tickets/openTicket.php','admin/Tickets/allticket.php',
    'admin/Doc/newdoc.php','admin/Doc/searchdoc.php','admin/employ/staff-directory.php','admin/employ/listadmin.php','admin/employ/listteacher.php','admin/employ/addemploy.php','admin/employ/addteacher.php',
    'admin/tasks/assigned-tasks.php','admin/Collague/add-collague.php','admin/Collague/edit-collague.php','admin/Collague/unactive-collague.php','admin/Collague/sub-collague.php',
    'admin/setting/setting.php','admin/setting/levels.php','admin/setting/blacklist.php','admin/setting/credit-card.php','admin/setting/ready-message.php',
    'admin/report/students.php','admin/report/colleague.php','admin/course/homework_check.php','admin/Bill/active-factor.php','admin/Bill/factor-notpay.php','admin/Bill/returned.php',
    'admin/Bill/waitingWallet.php','admin/Bill/SearchBillView.php','admin/Bill/advanced-report.php','admin/Bill/payment.php','admin/finance/monthly-payments.php','admin/employ/staff-documents.php','admin/employ/insurance.php',
];
$registeredPaths = array_filter(array_column($nodes, 'path'));
$missing = array_values(array_diff($menuPaths, $registeredPaths));
if ($missing) throw new RuntimeException('Menu routes missing from registry: ' . implode(', ', $missing));
foreach (array_unique(array_filter(array_column($nodes, 'path'))) as $path) {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false || !str_contains($source, 'inc/function.php')) {
        throw new RuntimeException("Route does not load common permission gate: $path");
    }
}
foreach ($nodes as $code => $node) {
    if ($node['parent'] !== null && !isset($nodes[$node['parent']])) throw new RuntimeException("Missing parent for $code");
}
if (($nodes['finance.wallet']['modes'] ?? []) !== ['institute']) throw new RuntimeException('Wallet must be institute-only.');
foreach (['dashboard.summary.cards.achievements', 'dashboard.summary.cards.learners-current', 'dashboard.summary.cards.announcements'] as $code) {
    if (($nodes[$code]['modes'] ?? []) !== ['institute']) throw new RuntimeException("Institute dashboard card has wrong mode: $code");
}
foreach (['dashboard.summary.cards.messages', 'school-notifications'] as $code) {
    if (($nodes[$code]['modes'] ?? []) !== ['school']) throw new RuntimeException("School-only section has wrong mode: $code");
}
foreach (['dashboard.summary.notifications', 'dashboard.summary.academic-archive'] as $code) {
    if (($nodes[$code]['modes'] ?? ['school', 'institute']) !== ['school', 'institute']) {
        throw new RuntimeException("Shared dashboard control has wrong mode: $code");
    }
}
foreach (['dashboard.calendar.executive', 'dashboard.calendar.academic'] as $code) {
    if (($nodes[$code]['parent'] ?? null) !== 'dashboard.calendar') {
        throw new RuntimeException("Dashboard calendar kind needs its own grant: $code");
    }
}
if (count(AdminPermissionCodesForPath('/admin/notifications/school_notifications.php')) !== 2) {
    throw new RuntimeException('Shared notification page must allow either of its two menu grants.');
}
foreach (['page-b9857fa208', 'page-25d387505a'] as $salesPage) {
    $base = 'dashboard.summary.overview.manager.' . $salesPage;
    foreach (['today', 'period'] as $period) foreach (['own', 'all'] as $scope) {
        if (($nodes[$base . '.' . $period . '.' . $scope]['parent'] ?? null) !== $base . '.' . $period) {
            throw new RuntimeException("Sales report scope missing: $base.$period.$scope");
        }
    }
}
foreach ($nodes as $code => $node) {
    if (str_contains($code, '.page-') && $node['path'] && preg_match('/صفحهٔ داخلی:|[a-z]{3}/i', $node['label'])) {
        throw new RuntimeException("Untranslated internal page label: $code");
    }
}
$safeCallSiblings = [
    'public-relations.safe-call.manage',
    'public-relations.safe-call.exchange',
    'public-relations.safe-call.assignment',
    'public-relations.safe-call.reports',
];
foreach ($safeCallSiblings as $code) {
    if (($nodes[$code]['parent'] ?? null) !== 'public-relations.safe-call') {
        throw new RuntimeException("Safe-call card must be a direct sibling: $code");
    }
}
if (!isset($nodes['public-relations.safe-call.manage.total-statistics'])) {
    throw new RuntimeException('Safe-call total statistics section is missing.');
}
foreach (['total-statistics', 'operator-statistics', 'phone-search'] as $section) {
    $code = 'public-relations.safe-call.manage.' . $section;
    if (($nodes[$code]['parent'] ?? null) !== 'public-relations.safe-call.manage') {
        throw new RuntimeException("Safe-call section must have an independent grant: $code");
    }
}
foreach (['temp-users-sales.php', 'permanet-users-sales.php'] as $salesPage) {
    if (count(AdminPermissionCodesForPath('/admin/panel/' . $salesPage)) !== 2) {
        throw new RuntimeException("Sales report must be reachable from manager and desktop grants: $salesPage");
    }
}
if (isset($nodes['public-relations.safe-call.manage.action-save'])) {
    throw new RuntimeException('Reviewed manage-call sections must not be mixed with generic root actions.');
}
echo 'OK: ' . count($nodes) . ' nodes; ' . count($menuPaths) . ' live menu pages and ' . count(array_unique(array_filter(array_column($nodes, 'path')))) . " registered pages pass through the common backend gate.\n";
