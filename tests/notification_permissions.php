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
        foreach ($notificationTestGrants as $grant) {
            if ($this->args[1] === $grant || str_starts_with($this->args[1], $grant . '.')) return 1;
        }
        return false;
    }
    public function fetchAll($mode): array
    {
        global $notificationTestGrants;
        return str_contains($this->sql, 'LIKE CONCAT')
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
$notificationTestGrants = ['institute-notifications.support'];
verifyNotification(array_column(InstituteNotificationLinks(), 0) === ['support'], 'Only granted institute link appears');
$notificationTestGrants = ['institute-notifications.deputy.education'];
verifyNotification(array_column(InstituteNotificationLinks(), 0) === ['deputy.education'], 'Deputy types remain independent');
$notificationTestGrants = ['institute-notifications'];
verifyNotification(count(InstituteNotificationLinks()) === 11, 'Whole institute branch restores all menu links');
$notificationTestGrants = ['dashboard.summary.notifications'];
verifyNotification(InstituteNotificationLinks() === [], 'Display-only grant must not grant operations');
echo "Passed {$checks} notification permission checks.\n";
