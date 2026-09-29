<?php
// Run independently: php tests/notification_homework_permissions.php
if (PHP_SAPI !== 'cli') exit;
define('BASE_PATH', dirname(__DIR__));
$mode = 'school';
function inst_type(): string { global $mode; return $mode; }
function inst_is(string $type): bool { return inst_type() === $type; }
require BASE_PATH . '/inc/admin_permissions.php';
$conn = new PDO('sqlite::memory:');
$conn->exec('CREATE TABLE admin_user_permissions (admin_username TEXT, permission_code TEXT)');
$insert = $conn->prepare('INSERT INTO admin_user_permissions VALUES (?, ?)');
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
foreach (['school', 'institute'] as $mode) {
    $prefix = AdminNotificationCode('information.homework-approval');
    $codes = AdminNotificationHomeworkGrantCodes(['homework-approval.review', 'homework-approval.history.delete',
        'assigned-tasks', 'assigned-tasks.create', 'assigned-tasks.status', 'documents', '*']);
    check($codes === [$prefix . '.review', $prefix . '.history.delete', 'documents', '*'], "$mode grant migration");
    check(AdminNotificationHomeworkGrantCodes($codes) === $codes, "$mode migration is idempotent");
    check(AdminNotificationHomeworkGrantCodes(['homework-approval.view']) === [$prefix . '.view'], "$mode view migration");
    check(AdminNotificationHomeworkGrantCodes(['homework-approval']) === [$prefix], "$mode branch migration");
    $_SESSION['admin']['user'] = $mode . '-reviewer';
    $insert->execute([AdminCurrentUsername(), $prefix . '.review']);
    check(AdminCanSee(AdminNotificationCode('')), "$mode notification entry visible");
    check(AdminCanSee($prefix), "$mode homework card visible");
    check(AdminCan($prefix . '.review'), "$mode review allowed");
    check(!AdminCan($prefix . '.history.delete'), "$mode delete remains denied");
    check(AdminPermissionCodesForPath('admin/course/homework_check.php') === [$prefix], "$mode homework page gate");
    check(AdminPermissionCodesForPath('admin/course/homework_check_history.php') === [$prefix . '.history'], "$mode history page gate");
    $_SESSION['admin']['user'] = $mode . '-viewer';
    $insert->execute([AdminCurrentUsername(), $prefix . '.view']);
    check(AdminCanSee($prefix) && !AdminCan($prefix . '.review'), "$mode view without review");
    $_SESSION['admin']['user'] = $mode . '-centre';
    $insert->execute([AdminCurrentUsername(), AdminNotificationCode('')]);
    check(AdminCan($prefix . '.review'), "$mode notification parent covers homework");
    check(AdminPermissionCodesForPath('admin/tasks/assigned-tasks.php') === [], "$mode tasks retired");
    check(!isset(AdminPermissionIndex()['assigned-tasks']), "$mode tasks removed from tree");
    echo "$mode: OK\n";
}
