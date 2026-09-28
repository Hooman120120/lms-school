<?php
// Run without a database: php tests/notification_permissions.php
define('BASE_PATH', dirname(__DIR__));
$institutionMode = 'school';
function inst_type(): string { global $institutionMode; return $institutionMode; }
function inst_is(string $mode): bool { return inst_type() === $mode; }
require BASE_PATH . '/inc/function.php';

class NotificationPermissionStatement
{
    private array $args = [];
    public function __construct(private string $sql) {}
    public function execute(array $args): bool { $this->args = $args; return true; }
    public function fetchColumn()
    {
        global $notificationTestGrants;
        if (count($this->args) === 1) return $notificationTestGrants !== [] ? 1 : false; // tree-account check
        foreach ($notificationTestGrants as $grant) {
            if ($this->args[1] === $grant || str_starts_with($this->args[1], $grant . '.')) return 1;
        }
        return false;
    }
    public function fetchAll($mode): array
    {
        global $notificationTestGrants;
        return str_contains($this->sql, 'CONCAT(?, ".")')
            ? array_values(array_filter($notificationTestGrants, fn($grant) => str_starts_with($grant, $this->args[1] . '.')))
            : $notificationTestGrants;
    }
}
class NotificationPermissionConnection
{
    public function prepare(string $sql): NotificationPermissionStatement { return new NotificationPermissionStatement($sql); }
}
$conn = new NotificationPermissionConnection();
$_SESSION['admin'] = ['user' => 'notification-test', 'adminrole' => 1];
$notificationTestGrants = [];
$checks = 0;
function verifyNotification(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
foreach (['school', 'institute'] as $institutionMode) {
    $notificationTestGrants = ['dashboard.summary.notifications'];
    verifyNotification(AdminPermissionCodeAvailable('dashboard.summary.notifications'), 'Notification button must exist in both modes');
    verifyNotification(AdminPermissionCodeAvailable('dashboard.summary.academic-archive'), 'Archive button must exist in both modes');
    verifyNotification(AdminHasMenuPermission('dashboard.summary.notifications'), 'Button-only grant must be usable');
    verifyNotification(!AdminHasMenuPermission('dashboard.summary.academic-archive'), 'Notification grant must not grant archive');
    verifyNotification(!AdminHasMenuNavigationPermission('school-notifications'), 'Button must not grant school page');
    verifyNotification(!AdminHasMenuNavigationPermission('institute-notifications'), 'Button must not grant institute page');
    verifyNotification(!in_array('dashboard.summary.notifications', AdminPermissionCodesForPath('/admin/notifications/school_notifications.php'), true), 'Display permission must not authorize page entry');
    verifyNotification(AdminPermissionCodeAvailable('school-notifications') === ($institutionMode === 'school'), 'School branch mode');
    verifyNotification(AdminPermissionCodeAvailable('institute-notifications') === ($institutionMode === 'institute'), 'Institute branch mode');
}
$institutionMode = 'school';
$notificationTestGrants = ['school-notifications.categories.attendance'];
verifyNotification(AdminHasMenuNavigationPermission('school-notifications'), 'Category must reveal menu');
verifyNotification(!AdminHasMenuPermission('school-notifications.categories.exam'), 'Category must not grant sibling');
verifyNotification(!AdminHasMenuPermission('school-notifications.mark-all'), 'Viewing must not grant mark-all');
$notificationTestGrants = ['school-notifications'];
verifyNotification(AdminHasMenuPermission('school-notifications.mark-all'), 'Whole branch grants its operations');
$institutionMode = 'institute';
$notificationTestGrants = ['institute-notifications.information.sms'];
verifyNotification(AdminHasMenuNavigationPermission('institute-notifications'), 'One feature reveals institute menu');
verifyNotification(!AdminHasMenuPermission('institute-notifications.information.rules'), 'SMS must not grant rules');
$notificationTestGrants = ['institute-notifications.categories.attendance'];
verifyNotification(!AdminHasMenuPermission('institute-notifications.categories.exam'), 'Institute categories stay independent');
verifyNotification(!AdminHasMenuPermission('institute-notifications.mark-all'), 'Category must not grant read-all');
$notificationTestGrants = ['institute-notifications'];
verifyNotification(AdminHasMenuPermission('institute-notifications.information.sms'), 'Full branch includes SMS');
verifyNotification(AdminHasMenuPermission('institute-notifications.financial.invoices'), 'Full branch includes invoices');
verifyNotification(AdminHasMenuPermission('institute-notifications.mark-all'), 'Full branch includes read-all');
$notificationTestGrants = ['dashboard.summary.notifications'];
verifyNotification(!AdminHasMenuNavigationPermission('institute-notifications'), 'Display-only grant must not grant operations');
$notificationTestGrants = ['institute-notifications.support'];
verifyNotification(AdminHasAnyCurrentTreePermission(), 'Retired grants must not fall back to legacy access');
verifyNotification(!AdminHasMenuPermission('institute-notifications.information.sms'), 'Retired grant must not widen access');
$institutionMode = 'school';
$notificationTestGrants = ['finance.unpaid.own'];
verifyNotification(AdminRecordVisibility('finance.unpaid', true) === 'own', 'Unpaid own grant must not show all creators');
verifyNotification(!AdminHasMenuNavigationPermission('finance.returned'), 'Unpaid access must not show returned invoices');
verifyNotification(!AdminHasMenuNavigationPermission('finance.installments'), 'Unpaid access must not show installment cases');
verifyNotification(!AdminHasMenuPermission('finance.unpaid.installment-create'), 'Viewing unpaid invoices must not create installments');
$notificationTestGrants = ['finance.unpaid.installment-create'];
verifyNotification(AdminHasMenuNavigationPermission('finance.unpaid'), 'Installment action must reveal its parent invoice page');
verifyNotification(!AdminHasMenuNavigationPermission('finance.installments'), 'Creating installment must not reveal installment cases');
$notificationTestGrants = ['finance.unpaid.all'];
verifyNotification(AdminRecordVisibility('finance.unpaid', false) === 'all', 'Unpaid all grant must show all creators');
$notificationTestGrants = ['finance.installments.own'];
verifyNotification(AdminRecordVisibility('finance.installments', true) === 'own', 'Installment own grant must remain scoped');
$notificationTestGrants = ['finance.statement.all'];
verifyNotification(AdminRecordVisibility('finance.statement', false) === 'all', 'Statement all grant must show all creators');
$notificationTestGrants = ['reports.students'];
verifyNotification(AdminHasMenuNavigationPermission('reports'), 'Student reports must reveal reports menu');
verifyNotification(!AdminHasMenuNavigationPermission('reports.staff'), 'Student reports must not reveal staff reports');
$notificationTestGrants = ['reports'];
verifyNotification(AdminHasMenuNavigationPermission('reports.students'), 'Reports parent must reveal student reports');
verifyNotification(AdminHasMenuNavigationPermission('reports.staff'), 'Reports parent must reveal staff reports');
$notificationTestGrants = ['finance.cards.create'];
verifyNotification(AdminPermissionCodeAvailable('finance.cards'), 'Card management must be part of finance');
verifyNotification(!AdminPermissionCodeAvailable('settings.cards'), 'Card management must not remain under settings');
verifyNotification(AdminHasMenuNavigationPermission('finance.cards'), 'Card create permission must reveal card management');
verifyNotification(!AdminHasMenuPermission('finance.cards.edit'), 'Card create permission must not grant editing');
verifyNotification(!AdminHasMenuPermission('finance.cards.delete'), 'Card create permission must not grant deletion');
$notificationTestGrants = ['finance.cards'];
verifyNotification(AdminHasMenuPermission('finance.cards.create'), 'Card management parent must include creation');
verifyNotification(AdminHasMenuPermission('finance.cards.edit'), 'Card management parent must include editing');
verifyNotification(AdminHasMenuPermission('finance.cards.delete'), 'Card management parent must include deletion');
foreach (AdminPermissionIndex() as $code => $metadata) {
    if (!str_starts_with($code, 'school-notifications.')) continue;
    $instituteCode = str_replace('school-notifications.', 'institute-notifications.', $code);
    $institutionMode = 'institute';
    verifyNotification(AdminPermissionCodeAvailable($instituteCode), 'Institute is missing feature: ' . $instituteCode);
}
echo "Passed {$checks} notification permission checks.\n";
