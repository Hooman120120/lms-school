<?php
/*
 * Staff and teacher permissions: one tree, config/admin_permission_registry.php.
 *
 * A node is a menu item, a page, a section of a page or one of its buttons.
 * An account holds a set of codes in admin_user_permissions. Holding a code
 * grants that node and everything below it. The code '*' grants the whole
 * tree (the school manager). Nothing else grants access: the old dbadmin
 * permission columns and adminrole are not consulted.
 *
 * Every admin page must be a node (its 'path'), or it is refused. Pages that
 * any signed-in account may open are listed in ADMIN_PAGES_FOR_EVERY_ACCOUNT.
 */

const ADMIN_PERMISSION_ALL = '*';

/* Admin entry points that need a signed-in account but no permission. */
const ADMIN_PAGES_FOR_EVERY_ACCOUNT = [
    'admin/login.php',
    'admin/inc/impersonate_start.php', // checks system.impersonate of the real account itself
    'admin/inc/impersonate_stop.php',
    // The notification centre also opens for the viewer's own direct notifications and checks this itself.
    'admin/notifications/school_notifications.php',
    'admin/notifications/school_notification_list.php',
    'admin/notifications/institute_notifications.php',
    'admin/notifications/institute_notification_list.php',
];

function AdminPermissionRegistry(): array
{
    static $tree = null;
    if ($tree === null) $tree = AdminPermissionAddViewNodes(require BASE_PATH . '/config/admin_permission_registry.php');
    return $tree;
}

/*
 * A page that has sections or buttons also gets «مشاهدهٔ این صفحه» as its
 * first child ('<code>.view'), so opening a page can be granted without any
 * of its operations.
 */
function AdminPermissionAddViewNodes(array $nodes): array
{
    foreach ($nodes as &$node) {
        if (empty($node['children'])) continue;
        $node['children'] = AdminPermissionAddViewNodes($node['children']);
        if (!empty($node['path'])) {
            array_unshift($node['children'], ['code' => $node['code'] . '.view', 'label' => 'مشاهدهٔ این صفحه']);
        }
    }
    unset($node);
    return $nodes;
}

/** @return array<string, array{parent:?string, modes:string[], paths:string[], label:string, node:array}> */
function AdminPermissionIndex(): array
{
    static $index = null;
    if ($index !== null) return $index;
    $index = [];
    $walk = static function (array $nodes, ?string $parent, array $parentModes) use (&$walk, &$index): void {
        foreach ($nodes as $node) {
            $modes = array_values(array_intersect($parentModes, $node['modes'] ?? ['school', 'institute']));
            $paths = array_values(array_filter(array_merge(
                isset($node['path']) ? [$node['path']] : [],
                $node['paths'] ?? []
            )));
            $index[$node['code']] = ['parent' => $parent, 'modes' => $modes, 'paths' => $paths,
                'label' => (string)$node['label'], 'node' => $node];
            $walk($node['children'] ?? [], $node['code'], $modes);
        }
    };
    $walk(AdminPermissionRegistry(), null, ['school', 'institute']);
    return $index;
}

function AdminPermissionCodeAvailable(string $code): bool
{
    $index = AdminPermissionIndex();
    return isset($index[$code]) && in_array(inst_type(), $index[$code]['modes'], true);
}

function AdminPermissionNodeAvailable(array $node): bool
{
    return in_array(inst_type(), $node['modes'] ?? ['school', 'institute'], true);
}

/** Label in the panel's own vocabulary (مدرسه / آموزشگاه). */
function AdminPermissionDisplayLabel(array $node): string
{
    $label = (string)($node['labels'][inst_type()] ?? $node['label'] ?? '');
    return function_exists('inst_display_text') ? inst_display_text($label) : $label;
}

/** The account that signed in, also while it views the panel as someone else. */
function AdminRealUsername(): string
{
    return (string)($_SESSION['admin']['real_user'] ?? $_SESSION['admin']['user'] ?? '');
}

/** The signed-in account whose permissions apply (the viewed one during «View as»). */
function AdminCurrentUsername(): string
{
    return (string)($_SESSION['admin']['user'] ?? '');
}

/** @return array<string, true> codes held by the account; ['*' => true] for the whole tree. */
function AdminGrantSet(?string $username = null, bool $reload = false): array
{
    static $cache = [];
    global $conn;
    $username = $username ?? AdminCurrentUsername();
    if ($username === '') return [];
    if ($reload) unset($cache[$username]);
    if (!isset($cache[$username])) {
        $cache[$username] = [];
        try {
            $stmt = $conn->prepare('SELECT permission_code FROM admin_user_permissions WHERE admin_username = ?');
            $stmt->execute([$username]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $code) $cache[$username][(string)$code] = true;
        } catch (Throwable $e) {
            error_log('AdminGrantSet: ' . $e->getMessage());
        }
    }
    return $cache[$username];
}

/** The account holds this node: the code itself, an ancestor of it, or '*'. */
function AdminCan(string $code, ?string $username = null): bool
{
    if (!AdminPermissionCodeAvailable($code)) return false;
    $grants = AdminGrantSet($username);
    if ($grants === []) return false;
    if (isset($grants[ADMIN_PERMISSION_ALL])) return true;
    $index = AdminPermissionIndex();
    for ($node = $code; $node !== null; $node = $index[$node]['parent'] ?? null) {
        if (isset($grants[$node])) return true;
    }
    return false;
}

/** At least one of the codes. */
function AdminCanAny(string ...$codes): bool
{
    foreach ($codes as $code) if (AdminCan($code)) return true;
    return false;
}

/*
 * The node or anything below it is held. Use it only to show a menu group or
 * to let a page open whose sections are checked one by one; it never grants a
 * sibling.
 */
function AdminCanSee(string $code, ?string $username = null): bool
{
    if (AdminCan($code, $username)) return true;
    if (!AdminPermissionCodeAvailable($code)) return false;
    foreach (AdminGrantSet($username) as $granted => $_) {
        if (str_starts_with((string)$granted, $code . '.') && AdminPermissionCodeAvailable((string)$granted)) return true;
    }
    return false;
}

function AdminDeny(string $message = 'مجوز انجام این عملیات برای شما فعال نشده است.'): void
{
    http_response_code(403);
    exit($message);
}

/** Stop the request unless one of the codes is held. */
function AdminRequire(string ...$codes): void
{
    if (!AdminCanAny(...$codes)) AdminDeny();
}

/*
 * Record scope of a list: 'all', 'own' or null (neither). A node with this
 * scope has children '<code>.all' and '<code>.own'; holding the node itself
 * (or an ancestor) means 'all'.
 */
function AdminScope(string $code): ?string
{
    if (AdminCan($code . '.all')) return 'all';
    if (AdminCan($code . '.own')) return 'own';
    return null;
}

/** A financial record belongs to the account that created its factor. */
function AdminCanAccessFinanceFactor(int $factorId, string $scopeCode): bool
{
    global $conn;
    $scope = AdminScope($scopeCode);
    if ($scope === null || $factorId <= 0) return false;
    if ($scope === 'all') return true;
    $user = AdminCurrentUsername();
    $stmt = $conn->prepare('SELECT 1 FROM dbfactor WHERE idfactor = ? AND (factoradder = ? OR useradder = ?) LIMIT 1');
    $stmt->execute([$factorId, $user, $user]);
    return (bool)$stmt->fetchColumn();
}

/** Filter already-loaded factor rows to the account's scope. */
function AdminFilterFinanceFactors(array $rows, string $scopeCode): array
{
    $scope = AdminScope($scopeCode);
    if ($scope === 'all') return $rows;
    if ($scope === null) return [];
    $user = AdminCurrentUsername();
    return array_values(array_filter($rows, static function ($row) use ($user): bool {
        $factorAdder = is_object($row) ? ($row->factoradder ?? null) : ($row['factoradder'] ?? null);
        $userAdder = is_object($row) ? ($row->useradder ?? null) : ($row['useradder'] ?? null);
        return (string)$factorAdder === $user || (string)$userAdder === $user;
    }));
}

/** Path of the running admin script, e.g. admin/Bill/payment.php. */
function AdminCurrentPath(?string $script = null): string
{
    $script = str_replace('\\', '/', $script ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
    $position = strpos($script, '/admin/');
    return $position === false ? ltrim($script, '/') : substr($script, $position + 1);
}

/** @return string[] codes of the nodes whose page this is. */
function AdminPermissionCodesForPath(?string $script = null): array
{
    $path = AdminCurrentPath($script);
    $codes = [];
    foreach (AdminPermissionIndex() as $code => $meta) {
        if (in_array($path, $meta['paths'], true) && in_array(inst_type(), $meta['modes'], true)) $codes[] = $code;
    }
    return $codes;
}

/*
 * The page gate, run for every admin script from inc/function.php. A page
 * that is not in the tree is refused; a page in the tree opens when the
 * account holds its node or something inside it. Sections and buttons are
 * checked on the page itself with AdminCan()/AdminRequire().
 */
function EnforceAdminPagePermission(): void
{
    if (PHP_SAPI === 'cli' || empty($_SESSION['admin']) || isset($_GET['logoutadmin'])) return;
    $path = AdminCurrentPath();
    if (!str_starts_with($path, 'admin/') || in_array($path, ADMIN_PAGES_FOR_EVERY_ACCOUNT, true)) return;
    $codes = AdminPermissionCodesForPath();
    if ($codes === []) AdminDeny('این صفحه در ساختار دسترسی‌ها ثبت نشده است.');
    foreach ($codes as $code) if (AdminCanSee($code)) return;
    AdminDeny('دسترسی شما به این بخش فعال نشده است.');
}

/** Replace an account's grants. Unknown codes are dropped. */
function SyncAdminPermissionGrants(string $username, array $codes): void
{
    global $conn;
    $index = AdminPermissionIndex();
    $codes = array_values(array_unique(array_filter($codes, static fn($code) =>
        $code === ADMIN_PERMISSION_ALL || (is_string($code) && isset($index[$code])))));
    try {
        $conn->beginTransaction();
        $conn->prepare('DELETE FROM admin_user_permissions WHERE admin_username = ?')->execute([$username]);
        $insert = $conn->prepare('INSERT INTO admin_user_permissions(admin_username, permission_code) VALUES (?, ?)');
        foreach ($codes as $code) $insert->execute([$username, $code]);
        $conn->commit();
        AdminGrantSet($username, true);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('SyncAdminPermissionGrants: ' . $e->getMessage());
    }
}

/*
 * What an editor may hand out: everything for a '*' holder, otherwise only
 * nodes the editor holds. Codes of the target that the editor does not hold
 * stay as they are, so the editor can neither add nor remove them.
 */
function AdminGrantableCodes(array $requested, string $target): array
{
    if (isset(AdminGrantSet()[ADMIN_PERMISSION_ALL])) return $requested;
    $kept = array_values(array_filter($requested, static fn($code): bool =>
        is_string($code) && $code !== ADMIN_PERMISSION_ALL && AdminCan($code)));
    foreach (AdminGrantSet($target, true) as $existing => $_) {
        if ($existing === ADMIN_PERMISSION_ALL || !AdminCan((string)$existing)) $kept[] = (string)$existing;
    }
    return array_values(array_unique($kept));
}

/*
 * SQL condition "<column> holds <code>" for listing accounts, e.g. the
 * supporters that can be assigned. With $includeAll false a '*' holder
 * (the manager) counts only when it holds the node itself.
 */
function AdminHoldersSql(string $code, string $column = 'username', bool $includeAll = true): string
{
    global $conn;
    if (!AdminPermissionCodeAvailable($code)) return '0 = 1';
    $codes = $includeAll ? [ADMIN_PERMISSION_ALL] : [];
    $index = AdminPermissionIndex();
    for ($node = $code; $node !== null; $node = $index[$node]['parent'] ?? null) $codes[] = $node;
    return $column . ' IN (SELECT admin_username FROM admin_user_permissions WHERE permission_code IN ('
        . implode(', ', array_map([$conn, 'quote'], $codes)) . '))';
}

/** A code of the notification centre of this panel: school-notifications.* or institute-notifications.*. */
function AdminNotificationCode(string $suffix): string
{
    return (inst_is('institute') ? 'institute-notifications' : 'school-notifications') . ($suffix === '' ? '' : '.' . $suffix);
}

/** The account holds the whole tree. */
function AdminHoldsEverything(?string $username = null): bool
{
    return isset(AdminGrantSet($username)[ADMIN_PERMISSION_ALL]);
}

require_once __DIR__ . '/admin_permissions_convert.php';
