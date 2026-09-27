<?php
/**
 * Local permission regression: php tests/admin-permission-regression.php
 * Uses synthetic grants only; no application bootstrap, HTTP calls, or DDL.
 * Every database write is inside one transaction that is always rolled back.
 * Tests route mapping and permission helpers, not complete HTTP POST handlers.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
define('BASE_PATH', dirname(__DIR__));
$_SESSION = [];
require_once BASE_PATH . '/inc/function.php';

$checks = 0;
$conn = null;
$failure = null;
$fixtureUser = 'permtest_' . bin2hex(random_bytes(12));
function permissionCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($label);
    }
    ++$checks;
}

try {
    $routes = [
        'documents.announcements' => 'admin/Doc/announcements.php',
        'documents.announcements.create' => 'admin/Doc/announcement-create.php',
        'documents.announcements.edit' => 'admin/Doc/announcement-edit.php',
        'documents.employee-documents' => 'admin/Doc/employee-documents.php',
        'documents.special-letters' => 'admin/Doc/special-letters.php',
    ];
    $actions = [
        'documents.announcements.action-delete-announcement' => 'documents.announcements',
        'documents.announcements.action-submit' => 'documents.announcements',
        'documents.announcements.create.action-save-announcement' => 'documents.announcements.create',
        'documents.announcements.create.action-submit' => 'documents.announcements.create',
        'documents.announcements.edit.action-submit' => 'documents.announcements.edit',
        'documents.special-letters.action-submit' => 'documents.special-letters',
    ];
    $index = AdminPermissionIndex();
    foreach ($routes as $code => $path) {
        permissionCheck(is_file(BASE_PATH . '/' . $path), "Missing route file: $path");
        permissionCheck(AdminPermissionCodeForPath('/lmsgitea/' . $path) === $code, "Wrong route mapping: $path");
        permissionCheck(AdminPermissionCodeForPath(str_replace('/', '\\', '/lmsgitea/' . $path)) === $code, "Wrong Windows route mapping: $path");
        $source = file_get_contents(BASE_PATH . '/' . $path);
        permissionCheck($source !== false && str_contains($source, 'inc/function.php'), "Route missing common permission bootstrap: $path");
        $expectedParent = in_array($code, ['documents.announcements.create', 'documents.announcements.edit'], true)
            ? 'documents.announcements' : 'documents';
        permissionCheck(($index[$code]['parent'] ?? null) === $expectedParent, "Wrong route parent: $code");
    }
    foreach ($actions as $code => $parent) {
        permissionCheck(isset($index[$code]), "Expected action missing: $code");
        permissionCheck($index[$code]['parent'] === $parent, "Wrong action parent: $code");
        $source = file_get_contents(BASE_PATH . '/' . $routes[$parent]);
        permissionCheck(str_contains($source, "AdminHasMenuPermission('$code')"), "Page missing explicit action check: $code");
    }
    permissionCheck(in_array('documents', AdminPermissionCodesForLegacy('permision_viewdocument'), true), 'Legacy documents mapping missing');
    permissionCheck(AdminPermissionCodeForPath('/lmsgitea/admin/Doc/permission-test-unmapped.php') === null, 'Unknown route must remain unmapped');

    $conn = new PDO('mysql:host=127.0.0.1;dbname=lmsgitea_merge_test;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Refuse nontransactional storage before touching any grant rows.
    $engine = $conn->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_permissions'")->fetchColumn();
    permissionCheck(strcasecmp((string)$engine, 'InnoDB') === 0, 'Permission grants require InnoDB rollback support');
    $countFixture = $conn->prepare('SELECT COUNT(*) FROM admin_user_permissions WHERE admin_username = ?');
    $countFixture->execute([$fixtureUser]);
    permissionCheck((int)$countFixture->fetchColumn() === 0, 'Synthetic fixture collision');
    $conn->beginTransaction();
    $delete = $conn->prepare('DELETE FROM admin_user_permissions WHERE admin_username = ?');
    $insert = $conn->prepare('INSERT INTO admin_user_permissions (admin_username, permission_code) VALUES (?, ?)');
    // Do not use SyncAdminPermissionGrants: it owns/commits its transaction.
    $grant = static function (array $codes) use ($delete, $insert, $fixtureUser): void {
        $delete->execute([$fixtureUser]);
        foreach ($codes as $code) {
            $insert->execute([$fixtureUser, $code]);
        }
    };
    $_SESSION['admin'] = ['user' => $fixtureUser];
    $targets = array_merge(array_keys($routes), array_keys($actions), ['documents.search', 'finance.staff-documents']);
    permissionCheck(!AdminHasAnyCurrentTreePermission(), 'Empty fixture must retain legacy-account behavior');
    foreach ($targets as $target) {
        permissionCheck(!AdminHasMenuPermission($target), "Empty fixture unexpectedly authorized: $target");
        permissionCheck(!AdminHasMenuNavigationPermission($target), "Empty fixture unexpectedly navigates: $target");
    }
    // Exact leaf grants open their ancestry for navigation only.
    foreach ($actions as $action => $parent) {
        $grant([$action]);
        permissionCheck(AdminHasAnyCurrentTreePermission(), "Action grant not recognized: $action");
        foreach ($targets as $target) {
            permissionCheck(AdminHasMenuPermission($target) === ($target === $action), "Action isolation failed: $action -> $target");
        }
        permissionCheck(AdminHasMenuNavigationPermission($parent), "Action cannot open containing page: $action");
        permissionCheck(AdminHasMenuNavigationPermission('documents'), "Action cannot reveal documents navigation: $action");
        permissionCheck(!AdminHasMenuPermission($parent), "Action elevated to parent authority: $action");
        foreach ($actions as $sibling => $unused) {
            if ($sibling !== $action) {
                permissionCheck(!AdminHasMenuNavigationPermission($sibling), "Action reveals sibling action: $action -> $sibling");
            }
        }
        foreach ($routes as $pageCode => $path) {
            $expectedNavigation = $pageCode === $parent
                || ($pageCode === 'documents.announcements' && in_array($parent, ['documents.announcements.create', 'documents.announcements.edit'], true));
            permissionCheck(AdminHasMenuNavigationPermission($pageCode) === $expectedNavigation, "Action page isolation failed: $action -> $pageCode");
        }
    }
    // Page grants intentionally include all descendants, including actions.
    $cases = [
        'documents.announcements.create' => ['documents.announcements.create', 'documents.announcements.create.action-save-announcement', 'documents.announcements.create.action-submit'],
        'documents.announcements.edit' => ['documents.announcements.edit', 'documents.announcements.edit.action-submit'],
        'documents.employee-documents' => ['documents.employee-documents'],
        'documents.special-letters' => ['documents.special-letters', 'documents.special-letters.action-submit'],
        'documents.announcements' => ['documents.announcements', 'documents.announcements.create', 'documents.announcements.edit', 'documents.announcements.action-delete-announcement', 'documents.announcements.action-submit', 'documents.announcements.create.action-save-announcement', 'documents.announcements.create.action-submit', 'documents.announcements.edit.action-submit'],
        'documents' => array_merge(array_keys($routes), array_keys($actions), ['documents.search']),
    ];
    foreach ($cases as $parent => $allowed) {
        $grant([$parent]);
        foreach ($targets as $target) {
            permissionCheck(AdminHasMenuPermission($target) === in_array($target, $allowed, true), "Parent inheritance failed: $parent -> $target");
        }
    }
    $grant(['documents.search']);
    permissionCheck(AdminHasMenuNavigationPermission('documents'), 'Existing child must reveal documents navigation');
    foreach (array_merge(array_keys($routes), array_keys($actions)) as $target) {
        permissionCheck(!AdminHasMenuPermission($target) && !AdminHasMenuNavigationPermission($target), "Existing sibling leaked new permission: $target");
    }
    $grant(['documents.announcements.create.action-save-announcement', 'documents.special-letters.action-submit']);
    permissionCheck(AdminHasMenuPermission('documents.announcements.create.action-save-announcement'), 'Combined grants lost create action');
    permissionCheck(AdminHasMenuPermission('documents.special-letters.action-submit'), 'Combined grants lost letter action');
    permissionCheck(!AdminHasMenuPermission('documents.announcements.edit.action-submit'), 'Combined grants leaked edit action');
    permissionCheck(!AdminHasMenuPermission('documents.special-letters.action-submit', ''), 'Explicit empty identity must not use session grants');
    permissionCheck(!AdminHasMenuPermission(''), 'Empty permission must fail closed');
    $grant(['documents.announcements-extra']);
    permissionCheck(!AdminHasMenuPermission('documents.announcements'), 'Permission prefix boundary leaked authority');
    permissionCheck(!AdminHasMenuNavigationPermission('documents.announcements'), 'Permission prefix boundary leaked navigation');
    permissionCheck(!AdminHasAnyCurrentTreePermission(), 'Unknown grant must not enable current-tree mode');
    permissionCheck($conn->inTransaction(), 'Test unexpectedly left rollback transaction');
} catch (Throwable $error) {
    // Do not print PDO messages, credentials, fixture identifiers, or account data.
    $failure = $error instanceof PDOException ? 'Local database operation failed; verify the test database and permission migration.' : $error->getMessage();
} finally {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION = [];
}

if ($conn instanceof PDO) {
    try {
        $verify = $conn->prepare('SELECT COUNT(*) FROM admin_user_permissions WHERE admin_username = ?');
        $verify->execute([$fixtureUser]);
        permissionCheck((int)$verify->fetchColumn() === 0, 'Rollback left synthetic permission rows');
    } catch (Throwable $error) {
        $failure = 'Rollback verification failed.';
    }
}
if ($failure !== null) {
    fwrite(STDERR, "FAIL: $failure\n");
    exit(1);
}
echo "PASS: $checks permission checks; synthetic grants rolled back and cleanup verified.\n";
