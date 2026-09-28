<?php
require_once __DIR__ . '/daily_notifications.php';

/** Shared menu/access tree.  Keep new administrative features in this registry. */
function AdminPermissionRegistry(): array
{
    static $tree = null;
    if ($tree === null) {
        $tree = require BASE_PATH . '/config/admin_permission_registry.php';
        $tree = AdminPermissionAttachPageActions($tree);
    }
    return $tree;
}

/** A node's modes are inherited from its parent when the tree is indexed. */
function AdminPermissionNodeAvailable(array $node): bool
{
    return in_array(inst_type(), $node['modes'] ?? ['school', 'institute'], true);
}

/** Use the existing institution vocabulary for every label in the access UI. */
function AdminPermissionDisplayLabel(array $node): string
{
    $label = (string)($node['labels'][inst_type()] ?? $node['label'] ?? '');
    return inst_display_text($label);
}

function AdminPermissionActionLabel(string $control): string
{
    $labels = [
        'delete' => 'حذف', 'remove' => 'حذف', 'edit' => 'ویرایش',
        'approve' => 'تأیید', 'reject' => 'رد', 'export' => 'خروجی',
        'import' => 'ورود اطلاعات', 'create' => 'ایجاد', 'add' => 'ایجاد',
        'save' => 'ذخیره',
    ];
    foreach ($labels as $keyword => $label) if (stripos($control, $keyword) !== false) return $label . ' اطلاعات این صفحه';
    return 'اجرای عملیات این صفحه';
}

/** Route-specific meanings take precedence over often stale HTML titles. */
function AdminPermissionPageLabel(string $path): string
{
    $labels = [
        'admin/panel/temp-users-sales.php' => 'گزارش ثبت‌نام‌های موقت فروش',
        'admin/panel/permanet-users-sales.php' => 'گزارش ثبت‌نام‌های قطعی فروش',
        'admin/panel/reactivate_experimental.php' => 'درخواست بازگردانی ثبت‌نام‌های موقت',
        'admin/panel/all_users_management.php' => 'مدیریت همهٔ کاربران',
        'admin/panel/alltypeusers.php' => 'گزارش کاربران بر اساس وضعیت',
        'admin/panel/resell-students.php' => 'پیگیری فروش ثبت‌نام‌ها',
        'admin/notifications/operator_notif.php' => 'اعلان‌های اپراتور',
        'admin/notifications/studentoperator_changes_notif.php' => 'اعلان‌های تغییر اپراتور',
        'admin/notifications/new_students.php' => 'اعلان‌های ثبت‌نام جدید',
        'admin/notifications/new_student_tickets.php' => 'درخواست‌های مربوط به ثبت‌نام جدید',
        'admin/notifications/suspended_student_op_ticket.php' => 'درخواست‌های تعلیق ثبت‌نام',
        'admin/notifications/NoOperator_student_op_ticket.php' => 'درخواست‌های ثبت‌نام بدون اپراتور',
        'admin/notifications/teachers_notif.php' => 'اعلان‌های استادان',
        'admin/panel/tomorrow_supports.php' => 'پیگیری‌های فردا',
        'admin/panel/mystudent_status.php' => 'وضعیت ثبت‌نام‌های من',
        'admin/panel/late_supports.php' => 'پیگیری‌های عقب‌افتاده',
        'admin/panel/today_supports.php' => 'پیگیری‌های امروز',
        'admin/notifications/notif_rule_management.php' => 'مدیریت قواعد اعلان‌ها',
        'admin/notifications/thread_list.php' => 'گفت‌وگوهای اعلان‌ها',
        'admin/notifications/notification_view.php' => 'مشاهدهٔ اعلان',
        'admin/notifications/notifications.php' => 'فهرست اعلان‌ها',
        'admin/notifications/verify_by_manager.php' => 'تأیید اعلان‌ها توسط مدیر',
        'admin/Student/suspension_history.php' => 'تاریخچهٔ تعلیق ثبت‌نام‌ها',
        'admin/Student/cancel_operator_history.php' => 'تاریخچهٔ لغو پشتیبانی',
        'admin/course/attendance.php' => 'ثبت حضور و غیاب',
        'admin/course/homework_check_history.php' => 'تاریخچهٔ بررسی تکالیف',
        'admin/course/homeworks.php' => 'تکالیف ثبت‌شده',
        'admin/Bill/paymentinstallments.php' => 'پرداخت اقساط',
        'admin/wallet/dashboard.php' => 'نمای کلی کیف پول',
        'admin/wallet/exchange.php' => 'تبادل اعتبار کیف پول',
        'admin/wallet/searchwalletview.php' => 'جست‌وجوی تراکنش‌های کیف پول',
        'admin/Bill/payment-history.php' => 'تاریخچهٔ پرداخت‌ها',
        'admin/Bill/payment-archive.php' => 'بایگانی پرداخت‌ها',
        'admin/employ/download-document.php' => 'دریافت مستندات همکاران',
    ];
    if (isset($labels[$path])) return $labels[$path];
    $source = @file_get_contents(BASE_PATH . '/' . $path);
    if ($source !== false && preg_match('/<title[^>]*>(.*?)<\/title>/is', $source, $matches)) {
        $title = trim(html_entity_decode(strip_tags(preg_replace('/<\?php.*?\?>/s', '', $matches[1]))));
        $title = trim(preg_split('/\s*[|｜]\s*/u', $title)[0] ?? '');
        if ($title !== '' && preg_match('/[\x{0600}-\x{06FF}]/u', $title)) return $title;
    }
    return 'بخش تکمیلی این صفحه';
}

/*
 * Pages can grow buttons without a developer remembering a second hand-made
 * permission list. Controls with a mutating name become action leaves below
 * their own registered page. The menu/page hierarchy stays declarative in the
 * registry; this only fills its final operation level.
 */
function AdminPermissionAttachPageActions(array $nodes): array
{
    $knownPaths = [];
    $collect = static function (array $items) use (&$collect, &$knownPaths): void {
        foreach ($items as $item) {
            if (!empty($item['path'])) $knownPaths[$item['path']] = true;
            $collect($item['children'] ?? []);
        }
    };
    $collect($nodes);

    $resolvePath = static function (string $target, string $from): ?string {
        $target = html_entity_decode(trim($target));
        if ($target === '' || str_contains($target, '<?') || preg_match('~^(?:https?:|//|\#|javascript:)~i', $target)) return null;
        $target = preg_split('/[?#]/', $target, 2)[0];
        $adminOffset = strpos($target, 'admin/');
        $candidate = $adminOffset === false ? dirname($from) . '/' . ltrim($target, '/') : substr($target, $adminOffset);
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $candidate)) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') { array_pop($parts); continue; }
            $parts[] = $part;
        }
        $candidate = implode('/', $parts);
        if (in_array($candidate, ['admin/setting/setting.php', 'admin/setting/levels.php'], true)) return null;
        return preg_match('#^admin/[A-Za-z0-9_./-]+\.php$#', $candidate) && is_file(BASE_PATH . '/' . $candidate) ? $candidate : null;
    };

    /* New internal pages can in turn link to another page, hence the bounded
       fixed-point loop. The known-path set prevents cycles and duplicates. */
    for ($pass = 0; $pass < 12; $pass++) {
        $changed = false;
        $walk = static function (&$items) use (&$walk, &$changed, &$knownPaths, $resolvePath): void {
            foreach ($items as &$node) {
                if (!isset($node['children'])) $node['children'] = [];
                $walk($node['children']);
                $path = $node['path'] ?? null;
                if (!$path || !preg_match('#^admin/[A-Za-z0-9_./-]+\.php$#', $path)) continue;
                if (($node['auto_scan'] ?? true) === false) continue;
                $source = @file_get_contents(BASE_PATH . '/' . $path);
                if ($source === false) continue;
                /* Ignore disabled markup and only follow real anchor tags.
                   Matching every url() also treated login redirects as child
                   pages, which produced a false permission hierarchy. */
                $scanSource = preg_replace('/<!--.*?-->/s', '', $source) ?? $source;
                $existing = array_column($node['children'], 'code');
                preg_match_all('/\bname\s*=\s*["\']([^"\']+)["\']/i', $scanSource, $controls);
                foreach (array_unique($controls[1]) as $control) {
                    if (!preg_match('/(?:add|create|edit|delete|remove|approve|reject|export|import|save)/i', $control)) continue;
                    $slug = trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $control)), '-');
                    if ($slug === '') continue;
                    $code = $node['code'] . '.action-' . $slug;
                    if (in_array($code, $existing, true)) continue;
                    $action = ['code' => $code, 'label' => AdminPermissionActionLabel($control)];
                    if (isset($node['modes'])) $action['modes'] = $node['modes'];
                    $node['children'][] = $action;
                    $existing[] = $code;
                    $changed = true;
                }
                /* A few legacy forms submit through an unnamed button. Keep
                   them visible and protected as a final operation leaf too. */
                $submitCode = $node['code'] . '.action-submit';
                if (preg_match('/<form\b/i', $scanSource) && !in_array($submitCode, $existing, true)) {
                    $action = ['code' => $submitCode, 'label' => 'ثبت / ذخیرهٔ تغییرات این صفحه'];
                    if (isset($node['modes'])) $action['modes'] = $node['modes'];
                    $node['children'][] = $action;
                    $existing[] = $submitCode;
                    $changed = true;
                }
                preg_match_all('/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>/is', $scanSource, $links, PREG_SET_ORDER);
                foreach ($links as $link) {
                    $target = $link[2];
                    if (preg_match('/\burl\(\s*["\']([^"\']+)/i', $target, $urlTarget)) {
                        $target = $urlTarget[1];
                    }
                    $targetPath = $resolvePath($target, $path);
                    if ($targetPath === null || isset($knownPaths[$targetPath])) continue;
                    $code = $node['code'] . '.page-' . substr(sha1($targetPath), 0, 10);
                    $page = ['code' => $code, 'label' => AdminPermissionPageLabel($targetPath), 'path' => $targetPath];
                    if (isset($node['modes'])) $page['modes'] = $node['modes'];
                    $node['children'][] = $page;
                    $knownPaths[$targetPath] = true;
                    $changed = true;
                }
            }
            unset($node);
        };
        $walk($nodes);
        if (!$changed) break;
    }
    return $nodes;
}

/** @return array<string, array{parent:?string,legacy:?string,modes:array}> */
function AdminPermissionIndex(): array
{
    static $index = null;
    if ($index !== null) return $index;
    $index = [];
    $walk = static function (array $nodes, ?string $parent = null, array $parentModes = ['school', 'institute']) use (&$walk, &$index): void {
        foreach ($nodes as $node) {
            if (empty($node['code'])) continue;
            $modes = array_values(array_intersect($parentModes, $node['modes'] ?? ['school', 'institute']));
            $index[$node['code']] = [
                'parent' => $parent,
                'legacy' => isset($node['legacy']) ? (string)$node['legacy'] : null,
                'modes' => $modes,
            ];
            $walk($node['children'] ?? [], (string)$node['code'], $modes);
        }
    };
    $walk(AdminPermissionRegistry());
    return $index;
}

function AdminPermissionCodeAvailable(string $code): bool
{
    $index = AdminPermissionIndex();
    return isset($index[$code]) && in_array(inst_type(), $index[$code]['modes'], true);
}

/** @return string[] */
function AdminPermissionCodesForLegacy(string $legacy): array
{
    $codes = [];
    foreach (AdminPermissionIndex() as $code => $meta) {
        if ($meta['legacy'] === $legacy && in_array(inst_type(), $meta['modes'], true)) $codes[] = $code;
    }
    return $codes;
}

function SyncAdminPermissionGrants(string $username, array $codes): void
{
    global $conn;
    $index = AdminPermissionIndex();
    $codes = array_values(array_unique(array_filter($codes, static fn($code) => is_string($code) && isset($index[$code]) && in_array(inst_type(), $index[$code]['modes'], true))));
    try {
        $conn->beginTransaction();
        $delete = $conn->prepare('DELETE FROM admin_user_permissions WHERE admin_username = ?');
        $delete->execute([$username]);
        $insert = $conn->prepare('INSERT INTO admin_user_permissions(admin_username, permission_code) VALUES (?, ?)');
        foreach ($codes as $code) $insert->execute([$username, $code]);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('SyncAdminPermissionGrants: ' . $e->getMessage());
    }
}

function AdminHasMenuPermission(string $code, ?string $username = null): bool
{
    global $conn;
    $username = $username ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($username === '' || !AdminPermissionCodeAvailable($code)) return false;
    try {
        $stmt = $conn->prepare('SELECT 1 FROM admin_user_permissions p LEFT JOIN admin_permission_nodes n ON n.code=p.permission_code WHERE p.admin_username=? AND (?=p.permission_code OR ? LIKE CONCAT(p.permission_code, ".%")) LIMIT 1');
        $stmt->execute([$username, $code, $code]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

/* A child grant may reveal its ancestors in the navigation, but it must never
 * grant a sibling page or action. Use this only when deciding whether a menu
 * group itself should be visible. */
function AdminHasMenuNavigationPermission(string $code, ?string $username = null): bool
{
    if (!AdminPermissionCodeAvailable($code)) return false;
    if (AdminHasMenuPermission($code, $username)) return true;
    global $conn;
    $username = $username ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($username === '' || $code === '') return false;
    try {
        $stmt = $conn->prepare('SELECT permission_code FROM admin_user_permissions WHERE admin_username=? AND permission_code LIKE CONCAT(?, ".%")');
        $stmt->execute([$username, $code]);
        $index = AdminPermissionIndex();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $childCode) {
            if (isset($index[$childCode]) && in_array(inst_type(), $index[$childCode]['modes'], true)) return true;
        }
        return false;
    } catch (Throwable $e) { return false; }
}

/** @return string[] All menu entries that lead to this page. */
function AdminPermissionCodesForPath(?string $script = null): array
{
    $script = str_replace('\\', '/', $script ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
    $position = strpos($script, '/admin/');
    $path = $position === false ? ltrim($script, '/') : substr($script, $position + 1);
    $codes = [];
    $walk = static function (array $nodes) use (&$walk, &$codes, $path): void {
        foreach ($nodes as $node) {
            if (($node['path'] ?? null) === $path) $codes[] = (string)$node['code'];
            $walk($node['children'] ?? []);
        }
    };
    $walk(AdminPermissionRegistry());
    return $codes;
}

function AdminPermissionCodeForPath(?string $script = null): ?string
{
    return AdminPermissionCodesForPath($script)[0] ?? null;
}

function AdminHasAnyCurrentTreePermission(?string $username = null): bool
{
    global $conn;
    $username = $username ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($username === '') return false;
    try {
        $stmt = $conn->prepare('SELECT permission_code FROM admin_user_permissions WHERE admin_username = ?');
        $stmt->execute([$username]);
        $index = AdminPermissionIndex();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $code) {
            // Retired notification grants must fail closed rather than enable legacy access.
            if (isset($index[$code]) || $code === 'settings' || str_starts_with($code, 'settings.') || str_starts_with($code, 'institute-notifications.')) return true;
        }
    } catch (Throwable $e) { }
    return false;
}

/**
 * Use the exact tree branch for migrated accounts and the old column(s) only
 * for accounts that have no tree grants yet. This prevents two sibling pages
 * that shared one legacy flag from granting access to each other.
 *
 * @param string[] $legacyPermissions
 */
function AdminCanUsePermissionScope(string $code, array $legacyPermissions = [], ?string $username = null): bool
{
    if (!AdminPermissionCodeAvailable($code)) return false;
    static $treeAccounts = [];
    static $treeScopes = [];
    static $legacyGrants = [];
    $username = $username ?? (string)($_SESSION['admin']['user'] ?? '');
    if (!array_key_exists($username, $treeAccounts)) {
        $treeAccounts[$username] = AdminHasAnyCurrentTreePermission($username);
    }
    if ($treeAccounts[$username]) {
        $cacheKey = $username . "\0" . $code;
        if (!array_key_exists($cacheKey, $treeScopes)) {
            $treeScopes[$cacheKey] = AdminHasMenuNavigationPermission($code, $username);
        }
        return $treeScopes[$cacheKey];
    }
    foreach ($legacyPermissions as $legacyPermission) {
        $cacheKey = $username . "\0" . $legacyPermission;
        if (!array_key_exists($cacheKey, $legacyGrants)) {
            $legacyGrants[$cacheKey] = $username === (string)($_SESSION['admin']['user'] ?? '')
                ? Permision($legacyPermission)
                : PermisionUser($legacyPermission, $username);
        }
        if (!$legacyGrants[$cacheKey]) return false;
    }
    return $legacyPermissions !== [];
}

/** Keep legacy menu behaviour only until an account receives tree grants. */
function AdminMenuCan(string $code, bool $legacyAllowed): bool
{
    return AdminHasAnyCurrentTreePermission()
        ? AdminHasMenuNavigationPermission($code)
        : ($legacyAllowed && AdminPermissionCodeAvailable($code));
}

/** Resolve a record list to all records or records owned by the signed-in operator. */
function AdminRecordVisibility(string $scopeCode, bool $legacyAll): ?string
{
    if (!AdminHasAnyCurrentTreePermission()) return $legacyAll ? 'all' : 'own';
    if (AdminHasMenuPermission($scopeCode)) return 'all';
    if (AdminHasMenuPermission($scopeCode . '.all')) return 'all';
    if (AdminHasMenuPermission($scopeCode . '.own')) return 'own';
    // The sales cards appear on both dashboard pages. A full "My desktop"
    // grant must keep its own-operator cards usable without granting all data.
    if (str_contains($scopeCode, 'page-b9857fa208.')
        && AdminHasMenuPermission('dashboard.summary.overview.desktop.sales-temporary')) return 'own';
    if (str_contains($scopeCode, 'page-25d387505a.')
        && AdminHasMenuPermission('dashboard.summary.overview.desktop.sales-permanent')) return 'own';
    return null;
}

/** A financial record belongs to the administrator who created its factor. */
function AdminCanAccessFinanceFactor(int $factorId, string $scopeCode, bool $legacyAll = false): bool
{
    global $conn;
    $scope = AdminRecordVisibility($scopeCode, $legacyAll);
    if ($scope === null || $factorId <= 0) return false;
    if ($scope === 'all') return true;
    $user = (string)($_SESSION['admin']['user'] ?? '');
    $stmt = $conn->prepare('SELECT 1 FROM dbfactor WHERE idfactor = ? AND (factoradder = ? OR useradder = ?) LIMIT 1');
    $stmt->execute([$factorId, $user, $user]);
    return (bool)$stmt->fetchColumn();
}

/** Filter already-loaded factor rows before legacy pages render them. */
function AdminFilterFinanceFactors(array $rows, string $scopeCode, bool $legacyAll = false): array
{
    if (AdminRecordVisibility($scopeCode, $legacyAll) === 'all') return $rows;
    $user = (string)($_SESSION['admin']['user'] ?? '');
    return array_values(array_filter($rows, static function ($row) use ($user): bool {
        $factorAdder = is_object($row) ? ($row->factoradder ?? null) : ($row['factoradder'] ?? null);
        $userAdder = is_object($row) ? ($row->useradder ?? null) : ($row['useradder'] ?? null);
        return (string)$factorAdder === $user || (string)$userAdder === $user;
    }));
}

/* Applied from admin/header.php. Legacy-only accounts keep their current
 * behaviour; accounts configured in the new tree are constrained by it. */
function EnforceAdminPageTreePermission(): void
{
    if (!AdminHasAnyCurrentTreePermission()) return;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ((str_ends_with($script, '/admin/notifications/school_notifications.php')
            || str_ends_with($script, '/admin/notifications/institute_notifications.php'))
        && CountSchoolDirectNotifications((string)($_SESSION['admin']['user'] ?? ''), false) > 0) {
        return;
    }
    $codes = AdminPermissionCodesForPath();
    // A grant for an operation also permits opening its containing page, but
    // never a sibling page (navigation helper only traverses descendants).
    if ($codes === []) return;
    foreach ($codes as $code) if (AdminHasMenuNavigationPermission($code)) return;
    http_response_code(403);
    exit('دسترسی شما به این بخش فعال نشده است.');
}

function check_national_code($value)
{
    if (!preg_match('/^[0-9]{10}$/', $value)) {
        return (bool) false;
    }
    for ($i = 0; $i < 10; $i++)
        if (preg_match('/^' . $i . '{10}$/', $value)) {
            return (bool) false;
        }
    for ($i = 0, $sum = 0; $i < 9; $i++)
        $sum += ((10 - $i) * intval(substr($value, $i, 1)));
    $ret = $sum % 11;
    $parity = intval(substr($value, 9, 1));
    if (($ret < 2 && $ret == $parity) || ($ret >= 2 && $ret == 11 - $parity)) {
        return (bool) true;
    }
    return (bool) false;
}

function checkCodemeli($var)
{

    global $conn;
    $sql = 'SELECT codemeli FROM dbpersonal WHERE codemeli=?';
    $query = $conn->prepare($sql);
    $query->execute([$var]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function checkTell($tel)
{
    global $conn;
    $sql = 'SELECT tel1 FROM dbpersonal WHERE tel1=?';
    $query = $conn->prepare($sql);
    $query->execute([$tel]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}
function checkTell2($tel)
{
    global $conn;
    $sql = 'SELECT codemeli FROM dbpersonal WHERE codemeli=?';
    $query = $conn->prepare($sql);
    $query->execute([$tel]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function checkTellWithoume($tel, $codemeli)
{
    global $conn;
    $sql = 'SELECT tel1 FROM dbpersonal WHERE tel1=? AND codemeli!=? AND CHAR_LENGTH(codemeli) = 10';
    $query = $conn->prepare($sql);
    $query->execute([$tel, $codemeli]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

//if(GetIp()!='127.0.0.1'){
//    $ip = GetIp();
//    $today = date("Y-m-d H:i:s" );
//    $ipblock = 'INSERT INTO blacklist_ip(ip_blackList_ip,date_blacklist_ip) VALUES (?,?)';
//    $quip = $conn->prepare($ipblock);
//    $quip->execute([$ip, $today]);
//}

function retstate($id)
{
    global $conn;
    $sql = 'SELECT * FROM `state` WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    return $result['name'];
}

function checkout($user, $code)
{
    global $conn;
    $sql = 'SELECT * FROM `checkout` WHERE username=? and coursecode=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $code]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function importCheckout($user)
{
    global $conn;
    $sql = 'SELECT * FROM `checkout` WHERE username=? ';
    $query = $conn->prepare($sql);
    $query->execute([$user]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function importCheckout_collague($user)
{
    global $conn;
    $sql = 'SELECT * FROM `checkout_collague` WHERE useradder=? AND username=? ';
    $query = $conn->prepare($sql);
    $query->execute([$_SESSION['auth']['user'], $user]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function getfee($var)
{
    global $conn;
    $sql = 'SELECT * FROM `codtbl_course` WHERE codecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$var]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        if ($result['courseactive'] == 1) {
            return $result['coursefee'];
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function Getcourse($code, $act = 1, $mode = 1)
{
    global $conn;
    $sql = 'SELECT * FROM `codtbl_course` WHERE codecourse=? AND courseactive=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code, $act]);
    $result = $query->fetch();
    if ($mode == 1) {
        if ($query->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    } else {
        if ($query->rowCount() > 0) {
            return $result;
        } else {
            return false;
        }

    }
}

function checkactCourse($user, $code)
{
    global $conn;
    $sql = 'SELECT * FROM `dbcourse` WHERE username=? AND codecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$user, $code]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function countCheckDoc()
{
    global $conn;
    $sql = 'SELECT COUNT(*) as countcheck FROM documention WHERE view=? ';
    $query = $conn->prepare($sql);
    $query->execute([0]);
    $result = $query->fetch();
    return $result['countcheck'];
}

function Delsync($user, $code)
{
    global $conn;
    $sql = 'SELECT * FROM `dbcourse`  WHERE `dbcourse`.`username`=? AND `dbcourse`.`codecourse`=? ';
    $query = $conn->prepare($sql);
    $query->execute([$user, $code]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}


function discountEntity($dis)
{
    global $conn;
    $sql = 'SELECT * FROM `dbdiscount` WHERE discount=? ';
    $query = $conn->prepare($sql);
    $query->execute([$dis]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        return $result;
    } else {
        return false;
    }
}

function discountUse($dis, $username)
{
    global $conn;
    $sql = 'SELECT * FROM `dbfactor` WHERE discountcode=? AND `username`=? ';
    $query = $conn->prepare($sql);
    $query->execute([$dis, $username]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function totalWallet($username, $status = 'موفق')
{
    global $conn;
    $sql = 'SELECT SUM(fee) AS  entwallet FROM `db_wallet` WHERE username=? and status=? ';
    $query = $conn->prepare($sql);
    $query->execute([$username, $status]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        return $result['entwallet'];
    }

}

function queryFactor($id)
{
    global $conn;
    $sql = 'SELECT * FROM `dbfactor` WHERE idfactor=? ';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result;
    } else {
        return false;
    }
}

function queryWallet($id)
{
    global $conn;
    $sql = 'SELECT * FROM `db_wallet` WHERE order_id=? ';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result;
    } else {
        return false;
    }
}

function returnCourse($code)
{
    global $conn;
    $sql = 'SELECT * FROM `codtbl_course` WHERE codecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result;
    } else {
        return false;
    }
}

function returnCourseDemo($code)
{
    global $conn;
    $sql = 'SELECT * FROM `course_demo` WHERE codecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result;
    } else {
        return false;
    }
}

function checkvalidcourse($code)
{
    global $conn;
    $sql = 'SELECT * FROM `codtbl_course` WHERE codecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);

    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function checkvalidcourseDemo($code)
{
    global $conn;
    $sql = 'SELECT * FROM `course_demo` WHERE codecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);

    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function isfactorNotpay($username)
{
    global $conn;
    $sql = 'SELECT * FROM `dbfactor` WHERE username=? AND status != ?  AND useradder IS  NULL ';
    $query = $conn->prepare($sql);
    $query->execute([$username, 'موفق']);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function isfactorNotpay_collague($username)
{
    global $conn;
    $sql = 'SELECT * FROM `dbfactor` WHERE username=? AND status != ? AND useradder IS NOT NULL';
    $query = $conn->prepare($sql);
    $query->execute([$username, 'موفق']);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function iscourseActivate($code)
{
    global $conn;
    $sql = 'SELECT *  FROM `codtbl_course` WHERE codecourse=?  AND activecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code, 1]);
    if ($query->rowCount() > 0) {
        return true;
    }
}

function countCardDB()
{
    global $conn;
    $sql = 'SELECT COUNT(id) as countcard FROM `db_card` ';
    $query = $conn->prepare($sql);
    $query->execute();
    $result = $query->fetch();
    return $result['countcard'];
}

function EntityDBserver($username)
{
    global $conn;
    $sql = 'SELECT * FROM `card_server` WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([$username]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}


function resultCardDB($id)
{
    global $conn;
    $sql = 'SELECT * FROM `db_card` WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $results = $query->fetch();
    return $results;
}

function recieveDetailCARD($username)
{
    global $conn;
    $sql = 'SELECT * FROM card_server INNER JOIN db_card ON `db_card`.`card_num`=`card_server`.`card_num` WHERE `username`=?';
    $query = $conn->prepare($sql);
    $query->execute([$username]);
    $results = $query->fetch();
    return $results;
}

function bankCardCheck($card = '', $irCard = true)
{
    $card = (string) preg_replace('/\D/', '', $card);
    $strlen = strlen($card);
    if ($irCard == true and $strlen != 16)
        return false;
    if ($irCard != true and ($strlen < 13 or $strlen > 19))
        return false;
    if (!in_array($card[0], [2, 4, 5, 6, 9]))
        return false;

    for ($i = 0; $i < $strlen; $i++) {
        $res[$i] = $card[$i];
        if (($strlen % 2) == ($i % 2)) {
            $res[$i] *= 2;
            if ($res[$i] > 9)
                $res[$i] -= 9;
        }
    }
    return array_sum($res) % 10 == 0 ? true : false;
}

function ActiveAdmin()
{
    global $conn;
    $sql = 'SELECT * FROM setting_public WHERE id =?';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result = $query->fetch();
    if ($result['act_admin'] == 1) {
        return true;
    } else {
        return false;
    }
}

function entityCard($card)
{
    global $conn;
    $sql = 'SELECT * FROM `db_card` WHERE card_num=?';
    $query = $conn->prepare($sql);
    $query->execute([$card]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function entitySheba($card)
{
    global $conn;
    $sql = 'SELECT * FROM `db_card` WHERE sheba=?';
    $query = $conn->prepare($sql);
    $query->execute([$card]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function SendSms($phone, $text, $olgo, $codemeli)
{
    global $conn;
    $username = __USERSMS;
    $password = __PASSSMS;
    $data = array('username' => $username, 'password' => $password, 'text' => $text, 'to' => $phone, "bodyId" => $olgo);
    $post_data = http_build_query($data);
    $handle = curl_init('https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber');
    curl_setopt($handle, CURLOPT_HTTPHEADER, array(
        'content-type' => 'application/x-www-form-urlencoded'
    ));
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_POST, true);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
    $response = curl_exec($handle);
    $response = json_decode($response);
    $response = $response->Value;
    date_default_timezone_set("Iran");
    $ip = GetIp();
    $os = GetOs();
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $sql = 'INSERT INTO db_smsun (username,olgo,text,tel,response,dateresponse,timeresponse,ip,os) VALUES (?,?,?,?,?,?,?,?,?)';
    $exe = $conn->prepare($sql);
    $exe->execute([$codemeli, $olgo, $text, $phone, $response, $datenow, $timenow, $ip, $os]);

}

function measurement($regs, $regc, $logp, $loga)
{
    global $conn;
    $sql = 'UPDATE setting_public SET act_register_student_two=?,act_register_collague_two=?,act_login_two=?,act_admin=? WHERE id=? ';
    $query = $conn->prepare($sql);
    $query->execute([$regs, $regc, $logp, $loga, 1]);
}

function tel_sms($phone, $text, $olgo, $idtel, $box)
{
    global $conn;
    $username = __USERSMS;
    $password = __PASSSMS;
    $data = array('username' => $username, 'password' => $password, 'text' => $text, 'to' => $phone, "bodyId" => $olgo);
    $post_data = http_build_query($data);
    $handle = curl_init('https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber');
    curl_setopt($handle, CURLOPT_HTTPHEADER, array(
        'content-type' => 'application/x-www-form-urlencoded'
    ));
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_POST, true);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
    $response = curl_exec($handle);
    $response = json_decode($response);
    $response = $response->Value;
    date_default_timezone_set("Iran");
    $ip = GetIp();
    $os = GetOs();
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $sql = 'INSERT INTO tel_sms (dbtel,useradder,box,tel,olgo,txt,response,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
    $exe = $conn->prepare($sql);
    $exe->execute([$idtel, $_SESSION['admin']['user'], $box, $phone, $olgo, $text, $response, $datenow, $timenow, $ip, $os]);
}

function retPerson($var)
{

    global $conn;
    $sql = 'SELECT * FROM dbpersonal WHERE codemeli=?';
    $query = $conn->prepare($sql);
    $query->execute([$var]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;
}

function retAdmin($var)
{

    global $conn;
    $sql = 'SELECT * FROM dbadmin WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([$var]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;
}

function retdbmovie($var)
{
    global $conn;
    $sql = 'SELECT * FROM dbmovie WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$var]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;
}

function retdbdoc($var)
{
    global $conn;
    $sql = 'SELECT * FROM dbaddon WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$var]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;
}

function countTicket($user, $status = 'منتظر پاسخ کارشناس')
{
    global $conn;
    $sql = 'SELECT COUNT(ticket_num)  as count_Ticket FROM `ticket_topic` WHERE username=? AND ticket_status=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $status]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        return $result;
    } else {
        return false;
    }
}

function CheckTicketValid($id, $user)
{
    global $conn;
    $sql = 'SELECT * FROM `ticket_topic` WHERE ticket_num=? AND username=?';
    $query = $conn->prepare($sql);
    $query->execute([$id, $user]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }

}

function freeCall($tel, $name)
{

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://' . SIMOTEL_SERVER . '//api/v4/call/originate/act',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
    "caller":"' . $tel . '",
    "callee":"412",
    "context":"apiroute",
    "caller_id":" ' . $name . '",
    "trunk_name":"982191098185",
          "timeout":"600"
}',
        CURLOPT_HTTPHEADER => array(
            'X-APIKEY: ' . SIMOTEL_API_KEY,
            'Authorization: ' . SIMOTEL_BASIC_AUTH,
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);


}

function Call($tel, $dakheli, $name)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://' . SIMOTEL_SERVER . '//api/v4/call/originate/act',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
    "caller":"' . $dakheli . '",
    "callee":"' . $tel . '",
    "context":"apiroute",
    "caller_id":" ' . $name . '",
    
          "timeout":"600"
}',
        CURLOPT_HTTPHEADER => array(
            'X-APIKEY: ' . SIMOTEL_API_KEY,
            'Authorization: Basic ' . SIMOTEL_BASIC_AUTH,
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
}

function keylogin($username)
{
    global $conn;
    $sql = 'SELECT * FROM dblogin WHERE username=? AND true_key=? AND keyactive IS NOT NULL ';
    $query = $conn->prepare($sql);
    $query->execute([$username, 0]);
    if ($query->rowCount() > 0) {
        return true;
    }

}

function keyactcourse($username)
{
    global $conn;
    $sql = 'SELECT * FROM dblogin WHERE username=? AND true_key=? AND keyactive IS NOT NULL ';
    $query = $conn->prepare($sql);
    $query->execute([$username, 1]);
    if ($query->rowCount() > 0) {
        $sql1 = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE `dbcourse`.`username`=? AND `codtbl_course`.`activecourse`=? AND `dbcourse`.`activityuser`=?';
        $query1 = $conn->prepare($sql1);
        $query1->execute([$username, 1, 1]);
        if ($query1->rowCount() > 0) {
            return 100;
        } else {
            return 400;
        }
    } else {
        return 500;
    }

}

function retdblogin($codemeli)
{
    global $conn;
    $sql = 'SELECT * FROM dblogin WHERE username=?  ';
    $query = $conn->prepare($sql);
    $query->execute([$codemeli]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;
}

function collague_student($code)
{
    global $conn;
    $sql = 'SELECT * FROM dbpersonal WHERE useradder=? AND codemeli=?';
    $ququ = $conn->prepare($sql);
    $ququ->execute([$_SESSION['auth']['user'], $code]);
    if ($ququ->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function checkout_collague($student, $code)
{
    global $conn;
    $sql = 'SELECT * FROM checkout_collague WHERE useradder=? AND username=? AND codecourse=?';
    $ququ = $conn->prepare($sql);
    $ququ->execute([$_SESSION['auth']['user'], $student, $code]);
    if ($ququ->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function wage()
{
    global $conn;
    $sql = 'SELECT wage FROM setting_public';
    $query = $conn->prepare($sql);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result->wage;
}

function uncourse($student)
{
    global $conn;
    $sql = 'SELECT * FROM dbcourse WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([$student]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function Permision($tab)
{
    if ($tab === 'permision_viewsetting') return false; // Retired settings section.
    global $conn;
    if (!is_string($tab) || !preg_match('/^[a-zA-Z0-9_]+$/', $tab)) return false;
    $mappedCodes = AdminPermissionCodesForLegacy($tab);
    if ($mappedCodes !== [] && AdminHasAnyCurrentTreePermission()) {
        foreach ($mappedCodes as $code) if (AdminHasMenuNavigationPermission($code)) return true;
        return false;
    }
    $sql = 'SELECT ' . $tab . '  FROM dbadmin WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([$_SESSION['admin']['user']]);
    $result = $query->fetch();
    if ($result && !empty($result[$tab])) return true;
    foreach ($mappedCodes as $code) {
        if (AdminHasMenuNavigationPermission($code)) return true;
    }
    return false;
}

function PermisionUser($tab, $user)
{
    if ($tab === 'permision_viewsetting') return false; // Retired settings section.
    global $conn;
    if (!is_string($tab) || !preg_match('/^[a-zA-Z0-9_]+$/', $tab)) return false;
    $mappedCodes = AdminPermissionCodesForLegacy($tab);
    if ($mappedCodes !== [] && AdminHasAnyCurrentTreePermission((string)$user)) {
        foreach ($mappedCodes as $code) if (AdminHasMenuNavigationPermission($code, (string)$user)) return true;
        return false;
    }
    $sql = 'SELECT ' . $tab . '  FROM dbadmin WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([$user]);
    $result = $query->fetch();
    if ($result && !empty($result[$tab])) return true;
    foreach ($mappedCodes as $code) {
        if (AdminHasMenuNavigationPermission($code, (string)$user)) return true;
    }
    return false;
}

/**
 * Teacher-only capabilities. Non-teacher administrators are intentionally not
 * restricted by these flags because they use the regular staff permissions.
 */
function TeacherPermission(string $permission, ?string $user = null): bool
{
    global $conn;

    $allowed = [
        'teacher_perm_courses',
        'teacher_perm_online_class',
        'teacher_perm_students',
        'teacher_perm_attendance',
        'teacher_perm_homework',
        'teacher_perm_content',
        'teacher_perm_messages',
    ];
    if (!in_array($permission, $allowed, true)) {
        return false;
    }

    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($user === '') {
        return false;
    }

    $stmt = $conn->prepare("SELECT adminrole, {$permission} FROM dbadmin WHERE username = ? LIMIT 1");
    $stmt->execute([$user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }

    // Teacher capabilities only narrow teacher accounts. Existing staff/admin
    // authorization continues to be controlled by the regular permissions.
    if ((int)$row['adminrole'] !== 3) {
        return true;
    }

    return (int)($row[$permission] ?? 0) === 1;
}

function TeacherHasClassAccess(int $classId, ?string $user = null): bool
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($classId <= 0 || $user === '') {
        return false;
    }

    $stmt = $conn->prepare('SELECT 1 FROM course_teacher WHERE class_id = ? AND teacher_username = ? LIMIT 1');
    $stmt->execute([$classId, $user]);
    return (bool)$stmt->fetchColumn();
}

function TeacherHasCourseAccess(int $codecourse, ?string $user = null): bool
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($codecourse <= 0 || $user === '') {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT 1
        FROM course_teacher ct
        INNER JOIN classes c ON c.id = ct.class_id
        WHERE c.codecourse = ? AND ct.teacher_username = ?
        LIMIT 1
    ');
    $stmt->execute([$codecourse, $user]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Return the subjects explicitly assigned to a teacher in a class.
 * An empty result means a legacy class assignment and keeps the former
 * all-subject access until the first subject is explicitly assigned.
 */
function TeacherAssignedDarsIds(int $classId, ?string $user = null): array
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($classId <= 0 || $user === '') {
        return [];
    }

    $stmt = $conn->prepare('SELECT dars_id FROM course_teacher_dars WHERE class_id = ? AND teacher_username = ? ORDER BY dars_id');
    $stmt->execute([$classId, $user]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function TeacherHasDarsAccess(int $classId, int $darsId, ?string $user = null): bool
{
    if ($classId <= 0 || $darsId <= 0 || !TeacherHasClassAccess($classId, $user)) {
        return false;
    }

    global $conn;
    $classDarsStmt = $conn->prepare('SELECT 1 FROM class_dars WHERE class_id = ? AND dars_id = ? LIMIT 1');
    $classDarsStmt->execute([$classId, $darsId]);
    if (!$classDarsStmt->fetchColumn()) {
        return false;
    }

    $assignedDars = TeacherAssignedDarsIds($classId, $user);
    return $assignedDars === [] || in_array($darsId, $assignedDars, true);
}

/** Subjects available in a class, optionally narrowed to one teacher. */
function RegisterClassDarsOptions(int $classId, ?string $teacherUsername = null): array
{
    global $conn;
    if ($classId <= 0) return [];

    $stmt = $conn->prepare('SELECT d.id, d.dars
        FROM class_dars cd
        INNER JOIN dbdars d ON d.id = cd.dars_id
        WHERE cd.class_id = ?
        ORDER BY d.dars, d.id');
    $stmt->execute([$classId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($teacherUsername === null || $teacherUsername === '') return $rows;
    $assigned = TeacherAssignedDarsIds($classId, $teacherUsername);
    if ($assigned === []) return $rows;
    return array_values(array_filter($rows, static fn(array $row): bool =>
        in_array((int)$row['id'], $assigned, true)));
}

/**
 * Return the organizational school role key assigned to an admin account.
 */
function SchoolStaffRoleKey(?string $user = null): ?string
{
    global $conn;
    static $cache = [];

    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($user === '') {
        return null;
    }
    if (array_key_exists($user, $cache)) {
        return $cache[$user];
    }

    $stmt = $conn->prepare('
        SELECT r.role_key
        FROM dbadmin a
        LEFT JOIN school_admin_roles r ON r.id = a.school_role_id
        WHERE a.username = ?
        LIMIT 1
    ');
    $stmt->execute([$user]);
    $roleKey = $stmt->fetchColumn();
    $cache[$user] = $roleKey !== false ? (string)$roleKey : null;
    return $cache[$user];
}

/**
 * Central catalogue and audience policy for school notifications.
 * Keeping this matrix in one place prevents menu/page authorization drift.
 */
function SchoolNotificationCatalog(): array
{
    return [
        'new_student'        => ['title' => inst_is('institute') ? 'فراگیر جدید' : 'دانش‌آموز جدید', 'icon' => 'fe-user-plus'],
        'attendance'         => ['title' => 'حضور و غیاب جدید', 'icon' => 'fe-check-square'],
        'exam'               => ['title' => 'آزمون و نمره جدید', 'icon' => 'fe-edit-3'],
        'discipline'         => ['title' => 'مورد انضباطی جدید', 'icon' => 'fe-alert-triangle'],
        'executive_calendar' => ['title' => 'تغییر تقویم اجرایی', 'icon' => 'fe-calendar'],
        'academic_calendar'  => ['title' => 'تغییر تقویم آموزشی', 'icon' => 'fe-book-open'],
        'announcement'       => ['title' => 'اطلاعیه', 'icon' => 'fe-bell'],
        'circular'           => ['title' => 'بخشنامه', 'icon' => 'fe-file-text'],
        'special_letter'     => ['title' => 'نامه اختصاصی', 'icon' => 'fe-mail'],
    ];
}

/** Personal staff documents are always available to their exact recipient. */
function SchoolDirectNotificationTypes(): array
{
    return ['announcement', 'circular', 'special_letter'];
}

function IsSchoolDirectNotificationType(string $eventType): bool
{
    return in_array($eventType, SchoolDirectNotificationTypes(), true);
}

function SchoolNotificationAllowedTypes(?string $roleKey = null): array
{
    $roleKey = $roleKey ?? SchoolStaffRoleKey();
    $all = array_keys(SchoolNotificationCatalog());
    $direct = SchoolDirectNotificationTypes();

    switch ($roleKey) {
        case 'manager':
        case 'general_deputy':
            return $all;
        case 'grade_deputy':
            $roleTypes = ['new_student', 'executive_calendar', 'attendance', 'exam', 'discipline'];
            break;
        case 'executive_deputy':
            $roleTypes = ['attendance', 'exam', 'discipline', 'executive_calendar', 'academic_calendar'];
            break;
        case 'cultural_disciplinary_deputy':
            $roleTypes = ['attendance', 'discipline', 'executive_calendar', 'new_student'];
            break;
        case 'counselor':
            $roleTypes = ['attendance', 'new_student', 'exam', 'discipline', 'executive_calendar', 'academic_calendar'];
            break;
        case 'teacher':
            $roleTypes = ['attendance', 'new_student', 'exam', 'academic_calendar'];
            break;
        case 'financial_deputy':
            $roleTypes = ['executive_calendar'];
            break;
        case 'technical_deputy':
            $roleTypes = ['executive_calendar', 'academic_calendar'];
            break;
        default:
            $roleTypes = [];
            break;
    }

    return array_values(array_unique(array_merge($roleTypes, $direct)));
}

function SchoolNotificationsReady(): bool
{
    global $conn;
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $ready = (bool)$conn->query("SHOW TABLES LIKE 'school_notifications'")->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/** Store one domain event. Audience selection is deliberately deferred to read time. */
function CreateSchoolNotification(
    string $eventType,
    string $title,
    string $body = '',
    ?int $actorAdminId = null,
    ?string $actorUsername = null,
    ?int $codecourse = null,
    ?int $classId = null,
    ?string $studentUsername = null,
    ?string $targetUrl = null,
    ?string $sourceType = null,
    ?string $sourceId = null
): bool {
    global $conn;
    if (!SchoolNotificationsReady() || !array_key_exists($eventType, SchoolNotificationCatalog())) {
        return false;
    }

    if (($actorUsername === null || $actorUsername === '') && $actorAdminId) {
        $actorStmt = $conn->prepare('SELECT username FROM dbadmin WHERE idadmin = ? LIMIT 1');
        $actorStmt->execute([$actorAdminId]);
        $foundActor = $actorStmt->fetchColumn();
        $actorUsername = $foundActor !== false ? (string)$foundActor : null;
    }
    if (($actorAdminId === null || $actorAdminId <= 0) && $actorUsername) {
        $actorStmt = $conn->prepare('SELECT idadmin FROM dbadmin WHERE username = ? LIMIT 1');
        $actorStmt->execute([$actorUsername]);
        $foundActorId = $actorStmt->fetchColumn();
        $actorAdminId = $foundActorId !== false ? (int)$foundActorId : null;
    }

    $dedupeKey = ($sourceType && $sourceId !== null && $sourceId !== '')
        ? substr($eventType . ':' . $sourceType . ':' . $sourceId, 0, 190)
        : null;

    try {
        $stmt = $conn->prepare('INSERT INTO school_notifications
            (event_type, title, body, actor_admin_id, actor_username, codecourse, class_id,
             student_username, target_url, source_type, source_id, dedupe_key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id = id');
        return $stmt->execute([
            $eventType, $title, $body !== '' ? $body : null, $actorAdminId,
            $actorUsername ?: null, $codecourse ?: null, $classId ?: null,
            $studentUsername ?: null, $targetUrl ?: null, $sourceType ?: null,
            $sourceId !== null ? (string)$sourceId : null, $dedupeKey
        ]);
    } catch (Throwable $e) {
        error_log('CreateSchoolNotification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Visibility SQL shared by notification count and list.
 * Scoped roles only see assigned grades; global calendar changes have no grade
 * and are intentionally visible to every role that is allowed that event type.
 */
function SchoolNotificationVisibility(string $user, ?string $eventType = null, bool $unreadOnly = false): array
{
    global $conn;
    $types = SchoolNotificationAllowedTypes(SchoolStaffRoleKey($user));
    if ($eventType !== null) {
        $types = in_array($eventType, $types, true) ? [$eventType] : [];
    }
    if ($user === '' || $types === []) {
        return ['0=1', []];
    }

    $adminStmt = $conn->prepare('SELECT idadmin FROM dbadmin WHERE username = ? LIMIT 1');
    $adminStmt->execute([$user]);
    $adminId = (int)$adminStmt->fetchColumn();
    $where = ['n.event_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')'];
    $args = $types;
    // Personal documents must never leak to another colleague.
    $where[] = '(n.recipient_username IS NULL OR n.recipient_username = ?)';
    $args[] = $user;
    $directTypes = SchoolDirectNotificationTypes();
    $where[] = '(n.event_type NOT IN (' . implode(',', array_fill(0, count($directTypes), '?')) . ') OR n.status = ?)';
    $args = array_merge($args, $directTypes, ['published']);
    // اعلان‌های نسخه قبلی برای هر دانش‌آموز جداگانه ذخیره می‌شدند.
    // آن رکوردها نگه داشته می‌شوند، اما در شمارنده و فهرست نمایش داده نمی‌شوند
    // تا از این پس هر جلسه فقط یک اعلان تجمیعی داشته باشد.
    $where[] = '(n.source_type IS NULL OR n.source_type NOT IN (?, ?))';
    $args[] = 'register_class_attendance';
    $args[] = 'register_class_evaluation';
    $where[] = '(n.actor_username IS NULL OR n.actor_username <> ?)';
    $args[] = $user;
    if ($adminId > 0) {
        $where[] = '(n.actor_admin_id IS NULL OR n.actor_admin_id <> ?)';
        $args[] = $adminId;
    }

    $roleKey = SchoolStaffRoleKey($user);
    if (in_array($roleKey, ['grade_deputy', 'counselor'], true)) {
        $where[] = '(n.codecourse IS NULL OR EXISTS (
            SELECT 1 FROM school_staff_courses ssc
            WHERE ssc.admin_username = ? AND ssc.codecourse = n.codecourse
        ))';
        $args[] = $user;
    } elseif ($roleKey === 'teacher') {
        $where[] = '(n.codecourse IS NULL OR EXISTS (
            SELECT 1 FROM course_teacher ct
            INNER JOIN classes tc ON tc.id = ct.class_id
            WHERE ct.teacher_username = ? AND tc.codecourse = n.codecourse
        ))';
        $args[] = $user;
    }

    if ($unreadOnly) {
        $where[] = 'NOT EXISTS (SELECT 1 FROM school_notification_reads nr
            WHERE nr.notification_id = n.id AND nr.admin_username = ?)';
        $args[] = $user;
    }
    return [implode(' AND ', $where), $args];
}

function CountSchoolNotifications(?string $user = null, bool $unreadOnly = true): int
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if (!SchoolNotificationsReady()) {
        return 0;
    }
    [$where, $args] = SchoolNotificationVisibility($user, null, $unreadOnly);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM school_notifications n WHERE {$where}");
    $stmt->execute($args);
    return (int)$stmt->fetchColumn();
}

/** Unread and total counts per visible notification type, for inbox cards. */
function SchoolNotificationTypeCounts(?string $user = null): array
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if (!SchoolNotificationsReady()) return [];
    [$where, $args] = SchoolNotificationVisibility($user, null, false);
    $sql = "SELECT n.event_type, COUNT(*) AS total_count,
                   SUM(CASE WHEN nr.notification_id IS NULL THEN 1 ELSE 0 END) AS unread_count
            FROM school_notifications n
            LEFT JOIN school_notification_reads nr ON nr.notification_id = n.id AND nr.admin_username = ?
            WHERE {$where} GROUP BY n.event_type";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge([$user], $args));
    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $counts[(string)$row['event_type']] = ['total' => (int)$row['total_count'], 'unread' => (int)$row['unread_count']];
    }
    return $counts;
}

function CountSchoolDirectNotifications(?string $user = null, bool $unreadOnly = false): int
{
    $total = 0;
    foreach (SchoolNotificationTypeCounts($user) as $type => $counts) {
        if (!IsSchoolDirectNotificationType((string)$type)) continue;
        $total += (int)($counts[$unreadOnly ? 'unread' : 'total'] ?? 0);
    }
    return $total;
}

/** Counts for operational notifications that pre-date school_notifications. */
function SchoolLegacyNotificationCounts(?string $user = null): array
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    $roleKey = SchoolStaffRoleKey($user);
    $counts = [
        'pending_invoice' => 0,
        'unread_sms' => 0,
        'contact_ticket' => 0,
        'contact_alert' => 0,
        'wallet_charge_pending' => 0,
    ];

    try {
        if (in_array($roleKey, ['manager', 'general_deputy'], true)
            || ($roleKey === 'financial_deputy' && PermisionUser('permision_actfactor', $user))) {
            $counts['pending_invoice'] = (int)$conn->query("SELECT COUNT(*) FROM deposit_verifications WHERE status = 'pending'")->fetchColumn();
        }
        if (inst_feature('wallet') && (in_array($roleKey, ['manager', 'general_deputy'], true)
            || ($roleKey === 'financial_deputy' && PermisionUser('permision_actwallet', $user)))) {
            $counts['wallet_charge_pending'] = (int)$conn->query("SELECT COUNT(*) FROM db_wallet WHERE status = 'در انتظار بررسی'")->fetchColumn();
        }
        if (in_array($roleKey, ['manager', 'general_deputy', 'executive_deputy'], true)) {
            $counts['unread_sms'] = (int)$conn->query('SELECT COUNT(*) FROM sms_inbox WHERE is_read = 0')->fetchColumn();
        }
        if (in_array($roleKey, ['manager', 'general_deputy'], true)) {
            $counts['contact_ticket'] = countReportThreadsVisible($user, true, true);
        } elseif ($roleKey === 'counselor') {
            $counts['contact_ticket'] = countReportThreadsVisible($user, false, false);
            $counts['contact_alert'] = CountCounselorCallAlerts($user);
        }
    } catch (Throwable $e) {
        error_log('SchoolLegacyNotificationCounts: ' . $e->getMessage());
    }
    return $counts;
}

function CountCounselorCallAlerts(?string $user = null): int
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($user === '' || SchoolStaffRoleKey($user) !== 'counselor') {
        return 0;
    }
    $rule = $conn->query('SELECT min_calls, min_talk_time FROM notification_rules
        WHERE active = 1 ORDER BY created_at DESC, id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$rule) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT s.codemeli)
        FROM simotel_calls_data s
        WHERE s.operator_username = ?
          AND s.snapshot_day = (SELECT MAX(s2.snapshot_day) FROM simotel_calls_data s2 WHERE s2.operator_username = ?)
          AND (COALESCE(s.total_calls,0) < ?
               OR COALESCE(s.total_talk_time,0) < ?)
          AND EXISTS (
              SELECT 1 FROM dbcourse d
              INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
              INNER JOIN dbfactor f ON f.idfactor = d.numfactor
              INNER JOIN school_staff_courses ssc
                ON ssc.codecourse = d.codecourse AND ssc.admin_username = ?
              WHERE d.username = s.codemeli AND d.activityuser = 1
                AND c.activecourse = 1 AND f.status = \'موفق\'
          )');
    $stmt->execute([$user, $user, (int)$rule['min_calls'], (int)$rule['min_talk_time'], $user]);
    return (int)$stmt->fetchColumn();
}

function CountSchoolNotificationMenu(?string $user = null): int
{
    $prefix = inst_is('institute') ? 'institute-notifications' : 'school-notifications';
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if (!AdminHasAnyCurrentTreePermission($user)) {
        return CountSchoolNotifications($user, true) + array_sum(SchoolLegacyNotificationCounts($user));
    }
    $total = 0;
    foreach (SchoolNotificationTypeCounts($user) as $type => $counts) {
        if (IsSchoolDirectNotificationType((string)$type)
            || AdminHasMenuPermission($prefix . '.categories.' . $type, $user)) {
            $total += (int)$counts['unread'];
        }
    }
    $legacyScopes = [
        'pending_invoice' => 'financial.invoices', 'wallet_charge_pending' => 'financial.wallet',
        'unread_sms' => 'information.sms', 'contact_ticket' => 'information.tickets',
        'contact_alert' => 'information.my-calls',
    ];
    foreach (SchoolLegacyNotificationCounts($user) as $kind => $count) {
        if (isset($legacyScopes[$kind]) && AdminHasMenuNavigationPermission($prefix . '.' . $legacyScopes[$kind], $user)) {
            $total += (int)$count;
        }
    }
    return $total;
}

function CountInstituteNotificationMenu(): int
{
    return inst_is('institute') ? CountSchoolNotificationMenu() : 0;
}

function GetSchoolNotifications(?string $user = null, ?string $eventType = null, int $limit = 100, ?bool $read = null): array
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if (!SchoolNotificationsReady()) {
        return [];
    }
    [$where, $args] = SchoolNotificationVisibility($user, $eventType, $read === false);
    if ($read === true) {
        $where .= ' AND EXISTS (SELECT 1 FROM school_notification_reads nr_read WHERE nr_read.notification_id = n.id AND nr_read.admin_username = ?)';
        $args[] = $user;
    }
    $limit = max(1, min(300, $limit));
    $sql = "SELECT n.*, c.coursename, cl.name AS class_name,
                   CONCAT(COALESCE(a.fname, au.fname, ''), ' ', COALESCE(a.lname, au.lname, '')) AS actor_name,
                   CASE WHEN nr.notification_id IS NULL THEN 0 ELSE 1 END AS is_read
            FROM school_notifications n
            LEFT JOIN codtbl_course c ON c.codecourse = n.codecourse
            LEFT JOIN classes cl ON cl.id = n.class_id
            LEFT JOIN dbadmin a ON a.idadmin = n.actor_admin_id
            LEFT JOIN dbadmin au ON au.username = n.actor_username
            LEFT JOIN school_notification_reads nr
              ON nr.notification_id = n.id AND nr.admin_username = ?
            WHERE {$where}
            ORDER BY n.created_at DESC, n.id DESC LIMIT {$limit}";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge([$user], $args));
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // اعلان‌های ایجاد تقویم که پیش از اضافه‌شدن لینک عمیق ساخته شده‌اند نیز
    // در صورت داشتن شناسه رویداد، مستقیماً همان رویداد را باز کنند.
    foreach ($notifications as &$notification) {
        if (in_array($notification['event_type'] ?? '', ['executive_calendar', 'academic_calendar'], true)
            && ctype_digit((string)($notification['source_id'] ?? ''))
            && !empty($notification['target_url'])
            && strpos((string)($notification['target_url'] ?? ''), 'event=') === false) {
            $separator = strpos((string)$notification['target_url'], '?') === false ? '?' : '&';
            $notification['target_url'] .= $separator . 'event=' . (int)$notification['source_id'];
        }
    }
    unset($notification);

    return $notifications;
}

function MarkSchoolNotificationRead(int $notificationId, ?string $user = null): bool
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($notificationId <= 0 || $user === '' || !SchoolNotificationsReady()) {
        return false;
    }
    [$where, $args] = SchoolNotificationVisibility($user, null, false);
    $check = $conn->prepare("SELECT 1 FROM school_notifications n WHERE n.id = ? AND {$where} LIMIT 1");
    $check->execute(array_merge([$notificationId], $args));
    if (!$check->fetchColumn()) {
        return false;
    }
    $stmt = $conn->prepare('INSERT INTO school_notification_reads (notification_id, admin_username)
        VALUES (?, ?) ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP');
    return $stmt->execute([$notificationId, $user]);
}

/**
 * Grade deputies and counselors may only access explicitly assigned grades.
 */
function StaffCourseScopeRequired(?string $user = null): bool
{
    return in_array(SchoolStaffRoleKey($user), ['grade_deputy', 'counselor'], true);
}

function StaffHasCourseAccess(int $codecourse, ?string $user = null): bool
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($codecourse <= 0 || $user === '') {
        return false;
    }
    if (!StaffCourseScopeRequired($user)) {
        return true;
    }

    $stmt = $conn->prepare('
        SELECT 1
        FROM school_staff_courses
        WHERE admin_username = ? AND codecourse = ?
        LIMIT 1
    ');
    $stmt->execute([$user, $codecourse]);
    return (bool)$stmt->fetchColumn();
}

function StaffHasClassAccess(int $classId, ?string $user = null): bool
{
    global $conn;
    $user = $user ?? (string)($_SESSION['admin']['user'] ?? '');
    if ($classId <= 0 || $user === '') {
        return false;
    }
    if (!StaffCourseScopeRequired($user)) {
        return true;
    }

    $stmt = $conn->prepare('
        SELECT 1
        FROM classes c
        INNER JOIN school_staff_courses ssc ON ssc.codecourse = c.codecourse
        WHERE c.id = ? AND ssc.admin_username = ?
        LIMIT 1
    ');
    $stmt->execute([$classId, $user]);
    return (bool)$stmt->fetchColumn();
}

function RequireStaffCourseAccess(int $codecourse, ?string $redirect = null): void
{
    if (StaffHasCourseAccess($codecourse)) {
        return;
    }

    if ($redirect !== null && !headers_sent()) {
        header('location: ' . $redirect);
        exit;
    }

    http_response_code(403);
    exit('شما به این پایه دسترسی ندارید.');
}

function RequireTeacherPermission(string $permission, ?string $redirect = null): void
{
    if ((int)($_SESSION['admin']['adminrole'] ?? -1) !== 3) {
        return;
    }
    if (TeacherPermission($permission)) {
        return;
    }

    if ($redirect !== null && !headers_sent()) {
        header('location: ' . $redirect);
        exit;
    }

    http_response_code(403);
    exit('شما مجوز انجام این عملیات را ندارید.');
}

/**
 * Private personnel documents shared by employees and teachers.
 * Files are stored outside the public upload folders and are only served by
 * the authenticated download endpoint.
 */
function AdminDocumentDefinitions(): array
{
    return [
        'document_file' => [
            'input' => 'document_file_upload',
            'label' => 'مدارک',
            'help' => 'مدارک تحصیلی، گواهی‌ها یا سایر مستندات مرتبط',
            'type' => 'documents',
        ],
        'resume_file' => [
            'input' => 'resume_file_upload',
            'label' => 'سوابق',
            'help' => 'رزومه، سوابق کاری یا سوابق آموزشی',
            'type' => 'resume',
        ],
        'identity_file' => [
            'input' => 'identity_file_upload',
            'label' => 'شناسایی هویت',
            'help' => 'تصویر کارت ملی، شناسنامه یا مدرک معتبر شناسایی',
            'type' => 'identity',
        ],
    ];
}

function SaveAdminDocumentUploads(string $username): array
{
    global $conn;

    $errors = [];
    $definitions = AdminDocumentDefinitions();
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
    $maxBytes = 10 * 1024 * 1024;
    $storageDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'admin_documents';

    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
        return ['امکان ایجاد فضای نگهداری امن مدارک وجود ندارد.'];
    }

    $currentStmt = $conn->prepare('SELECT document_file, resume_file, identity_file FROM dbadmin WHERE username = ? LIMIT 1');
    $currentStmt->execute([$username]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        return ['حساب کاربری برای ذخیره مدارک یافت نشد.'];
    }

    foreach ($definitions as $column => $definition) {
        $input = $definition['input'];
        if (!isset($_FILES[$input]) || (int)$_FILES[$input]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $file = $_FILES[$input];
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'بارگذاری «' . $definition['label'] . '» کامل نشد.';
            continue;
        }
        if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
            $errors[] = 'حجم فایل «' . $definition['label'] . '» باید حداکثر ۱۰ مگابایت باشد.';
            continue;
        }

        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'فرمت فایل «' . $definition['label'] . '» مجاز نیست.';
            continue;
        }

        $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = $storageDirectory . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = 'ذخیره فایل «' . $definition['label'] . '» انجام نشد.';
            continue;
        }

        try {
            $updateStmt = $conn->prepare("UPDATE dbadmin SET {$column} = ? WHERE username = ?");
            $updateStmt->execute([$storedName, $username]);

            $oldName = basename((string)($current[$column] ?? ''));
            if ($oldName !== '' && $oldName !== $storedName) {
                $oldPath = $storageDirectory . DIRECTORY_SEPARATOR . $oldName;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        } catch (Throwable $exception) {
            @unlink($destination);
            $errors[] = 'ثبت فایل «' . $definition['label'] . '» در اطلاعات کاربر انجام نشد.';
        }
    }

    return $errors;
}

function RenderAdminDocumentUploadFields($admin = null): void
{
    foreach (AdminDocumentDefinitions() as $column => $definition) {
        $storedName = is_object($admin) ? (string)($admin->{$column} ?? '') : '';
        ?>
        <div class="mb-3">
            <label class="form-label"><?= htmlspecialchars($definition['label']) ?></label>
            <input class="form-control" type="file" name="<?= htmlspecialchars($definition['input']) ?>"
                   accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
            <small class="text-muted"><?= htmlspecialchars($definition['help']) ?> — حداکثر ۱۰ مگابایت</small>
            <?php if ($storedName !== ''): ?>
                <div class="mt-2">
                    <a class="btn btn-sm btn-outline-primary"
                       href="<?= url('admin/employ/download-document.php?user=' . rawurlencode((string)$admin->username) . '&type=' . rawurlencode($definition['type'])) ?>"
                       target="_blank" rel="noopener">
                        <i class="fe fe-eye"></i> مشاهده یا دانلود فایل فعلی
                    </a>
                    <small class="text-warning d-block mt-1">انتخاب فایل جدید، فایل فعلی را جایگزین می‌کند.</small>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

function retCardServer($user)
{
    global $conn;
    $sql = 'SELECT card_num FROM card_server WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([$user]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;

}

function CheckFactorNotSuccess($num)
{
    global $conn;
    $sql = 'SELECT * FROM dbfactor WHERE `status`!=? AND `idfactor`=? ';
    $query = $conn->prepare($sql);
    $query->execute(['موفق', $num]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function CheckOrderNotSuccess($num)
{
    global $conn;
    $sql = 'SELECT * FROM db_wallet WHERE `status`!=? AND `order_id`=? ';
    $query = $conn->prepare($sql);
    $query->execute(['موفق', $num]);
    if ($query->rowCount() > 0) {
        return true;
    }
}

function CheckTrackId($track)
{
    global $conn;
    $sql = 'SELECT * FROM db_wallet WHERE `ref_id`=?  ';
    $query = $conn->prepare($sql);
    $query->execute([$track]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function ActivatePublic($tab)
{
    global $conn;
    $sql = 'SELECT ' . $tab . '  FROM setting_public WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result = $query->fetch();
    if ($result[$tab] == 1) {
        return true;
    } else {
        return false;
    }
}

function typeUser($codemeli)
{
    global $conn;
    $sql = 'SELECT role FROM dblogin WHERE username=?  ';
    $query = $conn->prepare($sql);
    $query->execute([$codemeli]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result->role;
    }
}

function passUser($codemeli)
{
    global $conn;
    $sql = 'SELECT password FROM dblogin WHERE username=?  ';
    $query = $conn->prepare($sql);
    $query->execute([$codemeli]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result->password;
}

function actUser($codemeli)
{
    global $conn;
    $sql = 'SELECT activity FROM dblogin WHERE username=?  ';
    $query = $conn->prepare($sql);
    $query->execute([$codemeli]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result->activity;
}

function keyUser($codemeli)
{
    global $conn;
    $sql = 'SELECT keyactive FROM dblogin WHERE username=?  ';
    $query = $conn->prepare($sql);
    $query->execute([$codemeli]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result->keyactive;
}

function countCheckWallet()
{
    global $conn;
    $sql = 'SELECT COUNT(*) as countcheck FROM db_wallet WHERE status=? ';
    $query = $conn->prepare($sql);
    $query->execute(['در انتظار بررسی']);
    $result = $query->fetch();
    return $result['countcheck'];
}

function countCheckfactor()
{
    global $conn;
    $sql = 'SELECT COUNT(*) as countcheck FROM dbfactor WHERE status=? ';
    $query = $conn->prepare($sql);
    $query->execute(['در انتظار بررسی']);
    $result = $query->fetch();
    return $result['countcheck'];
}

function CheckTicketNotReffer($ticket_num)
{
    global $conn;
    $sql = 'SELECT metod FROM ticket_topic WHERE metod=? AND ticket_num=?  ';
    $query = $conn->prepare($sql);
    $query->execute([0, $ticket_num]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function Checkcodecourse($code)
{
    global $conn;
    $sql = 'SELECT codecourse FROM codtbl_course WHERE codecourse=?   ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}

function CheckcodecourseForupdat($code, $course)
{
    global $conn;
    $sql = 'SELECT codecourse FROM codtbl_course WHERE codecourse=?   ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        if ($result['codecourse'] != $course) {
            return false;
        } else {
            return true;
        }
    } else {
        return true;
    }
}

function CheckcodecourseForupdatDemo($code, $course)
{
    global $conn;
    $sql = 'SELECT codecourse FROM course_demo WHERE codecourse=?   ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        if ($result['codecourse'] != $course) {
            return false;
        } else {
            return true;
        }
    } else {
        return true;
    }
}

function CountRegisterCourse($code, $act)
{
    global $conn;
    $sql = 'SELECT COUNT(*) as jam FROM dbcourse WHERE codecourse=? AND activityuser=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code, $act]);
    $result = $query->fetch();
    return $result['jam'];

}

function countCheckinbox()
{
    global $conn;
    $sql = 'SELECT COUNT(*) as countcheck FROM ticket_topic WHERE metod=?  AND responser=? ';
    $query = $conn->prepare($sql);
    $query->execute([1, $_SESSION['admin']['user']]);
    $result = $query->fetch();
    return $result['countcheck'];
}

function countCheckemergency()
{
    global $conn;
    $sql = 'SELECT COUNT(*) as countcheck FROM ticket_topic WHERE metod=?   ';
    $query = $conn->prepare($sql);
    $query->execute([4]);
    $result = $query->fetch();
    return $result['countcheck'];
}

function countCheckNotreffer()
{
    global $conn;
    $sql = 'SELECT COUNT(*) as countcheck FROM ticket_topic WHERE metod=?   ';
    $query = $conn->prepare($sql);
    $query->execute([0]);
    $result = $query->fetch();
    return $result['countcheck'];
}

function retmovie($code)
{
    global $conn;
    $sql = 'SELECT * FROM dbmovie WHERE codecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result;
    }
}

function retMovieForThisClass($code, $classId) {
    global $conn;
    $sql = 'SELECT * FROM dbmovie INNER JOIN class_movie ON dbmovie.id = class_movie.movie_id WHERE dbmovie.codecourse = ? AND class_movie.class_id = ?';
    $query = $conn->prepare($sql);
    $query->execute([$code, $classId]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result;
    }
}

function retaddon($code)
{
    global $conn;
    $sql = 'SELECT * FROM dbaddon WHERE codecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result;
    }
}

function retAddonForThisClass($code, $classId) {
    global $conn;
    $sql = 'SELECT * FROM dbaddon INNER JOIN addon_class ON dbaddon.id = addon_class.addon_id WHERE dbaddon.codecourse = ? AND addon_class.class_id = ?';
    $query = $conn->prepare($sql);
    $query->execute([$code, $classId]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        return $result;
    }
}

function retIdMovie($id)
{
    global $conn;
    $sql = 'SELECT * FROM dbmovie WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        return $result;
    }
}

function retIdDoc($id)
{
    global $conn;
    $sql = 'SELECT * FROM dbaddon WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        return $result;
    }
}

function Countdiscount($discount)
{
    global $conn;
    $sql = 'SELECT COUNT(*) as jcount FROM dbfactor WHERE discountcode=?';
    $query = $conn->prepare($sql);
    $query->execute([$discount]);
    $result = $query->fetch();
    return $result['jcount'];
}

function retTelGroup($id)
{
    global $conn;
    $sql = 'SELECT * FROM tel_group WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    return $result;
}

function retTelDb($id)
{
    global $conn;
    $sql = 'SELECT * FROM tel_db WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    if ($query->rowCount() > 0) {
        return $result;
    }
}

function act_classSkyroom($code)
{
    global $conn;
    $sql = 'SELECT * FROM codtbl_course WHERE codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch();
    return $result['act_class'];
}

function act_ostadSkyroom($code)
{
    global $conn;
    $sql = 'SELECT * FROM codtbl_course WHERE codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch();
    return $result['act_ostadclass'];
}

function act_classSkyroomDemo($code)
{
    global $conn;
    $sql = 'SELECT * FROM course_demo WHERE codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch();
    return $result['act_class'];
}

function act_classSkyroomForThisClass($classId)
{
    global $conn;
    $sql = 'SELECT * FROM classes WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$classId]);
    $result = $query->fetch();
    return $result['act_class'];
}

function act_generalSkyroom($code)
{
    global $conn;
    $sql = 'SELECT * FROM codtbl_course WHERE codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch();
    return $result['act_general'];
}

function act_generalSkyroomForThisClass($classId)
{
    global $conn;
    $sql = 'SELECT * FROM classes WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$classId]);
    $result = $query->fetch();
    return $result['act_general'];
}

function act_quiz($code)
{
    global $conn;
    $sql = 'SELECT * FROM codtbl_course WHERE codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$code]);
    $result = $query->fetch();
    return $result['act_quiz'];
}

function act_quizForThisClass($classId)
{
    global $conn;
    $sql = 'SELECT * FROM classes WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$classId]);
    $result = $query->fetch();
    return $result['act_quiz'];
}

function retDbquiz($id)
{
    global $conn;
    $sql = 'SELECT * FROM dbquiz WHERE idquiz=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;
}

function countresultquiz($id)
{
    global $conn;
    $sql = 'SELECT COUNT(idresult) as countquiz FROM `result_quiz` WHERE quizid=? ';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    return $result['countquiz'];
}
function countResultQuizForThisClass($id, $class)
{
    global $conn;
    $sql = 'SELECT COUNT(idresult) as countquiz FROM `result_quiz` INNER JOIN dbcourse ON dbcourse.username = result_quiz.username WHERE result_quiz.quizid=? AND dbcourse.class_id = ? AND dbcourse.activityuser = ?';
    $query = $conn->prepare($sql);
    $query->execute([$id, $class, 1]);
    $result = $query->fetch();
    return $result['countquiz'];
}

function getAllQuizResults($quizId, $isJame = false) {
    global $conn;

    if ($isJame) {
        $sql = "SELECT username, true_response AS trueresponse, false_response AS falseresponse, unresponse, allquestion, 
                       (true_response/allquestion*100) AS nomre
                FROM result_quizzes_jame
                WHERE quiz_id = ?";
    } else {
        $sql = "SELECT username, trueresponse, falseresponse, unresponse, allquestion, 
                       (trueresponse/allquestion*100) AS nomre
                FROM result_quiz
                WHERE quizid = ?";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([$quizId]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $results;
}

function getClassQuizResults($quizId, $classId, $isJame = false)
{
    global $conn;

    if (!$isJame) {
        $sql = "
            SELECT rq.username, rq.taraz
            FROM result_quiz rq
            INNER JOIN dbcourse dc ON dc.username = rq.username
            WHERE rq.quizid = ?
              AND dc.class_id = ?
        ";
    } else {
        $sql = "
            SELECT rq.username, rq.taraz
            FROM result_quizzes_jame rq
            INNER JOIN dbcourse dc ON dc.username = rq.username
            WHERE rq.quiz_id = ?
              AND dc.class_id = ?
        ";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([$quizId, $classId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calcClassRankAndTaraz($results, $currentUsername)
{
    if (empty($results)) {
        return null;
    }

    // مرتب‌سازی نزولی بر اساس تراز
    usort($results, function ($a, $b) {
        return $b['taraz'] <=> $a['taraz'];
    });

    // محاسبه میانگین تراز کلاس
    $sum = 0;
    foreach ($results as $r) {
        $sum += $r['taraz'];
    }
    $avg = $sum / count($results);

    $rank = 0;
    $realRank = 0;
    $prevTaraz = null;

    foreach ($results as $index => $row) {

        // اگر تراز تغییر کرده، رتبه جدید بده
        if ($prevTaraz !== $row['taraz']) {
            $realRank = $index + 1;
            $prevTaraz = $row['taraz'];
        }

        if ($row['username'] == $currentUsername) {

            // تراز نرمال‌شده در کلاس
            $classTaraz = 10000 + ($row['taraz'] - $avg);

            return [
                'rank'        => $realRank,
                'taraz'       => round($classTaraz),
                'avg_taraz'   => round($avg),
                'total'       => count($results)
            ];
        }
    }

    return null;
}






function countResultQuizForThisCourse($id, $code)
{
    global $conn;
    $sql = 'SELECT COUNT(idresult) as countquiz FROM `result_quiz` INNER JOIN dbcourse ON dbcourse.username = result_quiz.username WHERE result_quiz.quizid=? AND dbcourse.codecourse = ? AND dbcourse.activityuser = ?';
    $query = $conn->prepare($sql);
    $query->execute([$id, $code, 1]);
    $result = $query->fetch();
    return $result['countquiz'];
}

function countresultquizJame($id)
{
    global $conn;
    $sql = 'SELECT COUNT(id) as countquiz FROM `result_quizzes_jame` WHERE quiz_id=? ';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    return $result['countquiz'];
}

function countResultQuizJameForThisCourse($id, $code)
{
    global $conn;
    $sql = 'SELECT COUNT(id) as countquiz FROM `result_quizzes_jame` INNER JOIN dbcourse ON dbcourse.username = result_quizzes_jame.username WHERE result_quizzes_jame.quiz_id = ? AND dbcourse.codecourse = ? AND dbcourse.activityuser = ?';
    $query = $conn->prepare($sql);
    $query->execute([$id, $code, 1]);
    $result = $query->fetch();
    return $result['countquiz'];
}

function absentuser($id)
{
    global $conn;
    $sql = 'SELECT * FROM dbquiz WHERE idquiz=? ';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    $dars = $result['dars'];
    $result = $result['codecourse'];
    if ($dars == 21) {
        return CountRegisterCourse($result, 1) - countResultQuizJameForThisCourse($id, $result);
    } else {
        return CountRegisterCourse($result, 1) - countResultQuizForThisCourse($id, $result);
    }
}

function avequiz($id)
{
    global $conn;
    $sql = 'SELECT SUM(nomre) as sumnum FROM result_quiz WHERE quizid=? ';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    if (countresultquiz($id) != 0) {
        return $result['sumnum'] / countresultquiz($id);
    } else {
        return 0;
    }
}

function avequizJame($id)
{
    global $conn;
    $sql = 'SELECT SUM(nomre) AS sumnum FROM result_quizzes_jame WHERE quiz_id = ?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    if (countresultquizJame($id) != 0) {
        return $result['sumnum'] / countresultquizJame($id);
    } else {
        return 0;
    }
}

function checkDubDars($dars, $tasalsol, $course)
{
    global $conn;
    $sql = 'SELECT * FROM dbquiz WHERE dars=? AND tasalsol=? AND codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$dars, $tasalsol, $course]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }

}

function getdars($id)
{
    global $conn;
    $sql = 'SELECT * FROM dbdars WHERE id=? ';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch();
    return $result['dars'];

}

function checkusercourse($id, $user)
{
    global $conn;
    $code = retDbquiz($id);
    $query = $conn->prepare('SELECT * FROM dbcourse WHERE codecourse=? AND username=? AND activityuser=?');
    $query->execute([$code->codecourse, $user, 1]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function maxtaraz($id)
{
    global $conn;
    $sql = 'SELECT MAX(taraz) as maxtaraz FROM result_quiz WHERE quizid=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($result->maxtaraz != null) {
        return $result->maxtaraz;
    } else {
        return 0;
    }
}

function maxtarazJame($id)
{
    global $conn;
    $sql = 'SELECT MAX(taraz) as maxtaraz FROM result_quizzes_jame WHERE quiz_id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($result->maxtaraz != null) {
        return $result->maxtaraz;
    } else {
        return 0;
    }
}


function mintaraz($id)
{
    global $conn;
    $sql = 'SELECT MIN(taraz) as mintaraz FROM result_quiz WHERE quizid=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($result->mintaraz != null) {
        return $result->mintaraz;
    } else {
        return 0;
    }
}

function mintarazJame($id)
{
    global $conn;
    $sql = 'SELECT MIN(taraz) as mintaraz FROM result_quizzes_jame WHERE quiz_id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($result->mintaraz != null) {
        return $result->mintaraz;
    } else {
        return 0;
    }
}

function retallcourse($user, $startdate, $enddate)
{
    global $conn;
    $course = array();
    $sql = 'SELECT * FROM `dbcourse` INNER JOIN dbfactor ON dbcourse.numfactor=dbfactor.idfactor WHERE dbcourse.username=? AND dbfactor.datecreate BETWEEN ? AND ?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $startdate, $enddate]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            array_push($course, $res->codecourse);
        }
        return $course;
    } else {
        return NULL;
    }
}

function returnAllCoursesWithStatus($user, $startdate, $enddate, $courseActive, $userActive)
{
    global $conn;
    $course = array();
    $sql = 'SELECT * FROM `dbcourse` 
    INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor
    INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse
    WHERE dbcourse.username = ? 
    AND codtbl_course.activecourse = ?
    AND dbfactor.datecreate BETWEEN ? AND ?';

    $query = $conn->prepare($sql);
    if ($userActive != 2) {
        $sql .= ' AND dbcourse.activityuser = ?';
        $query = $conn->prepare($sql);
        $query->execute([$user, $courseActive, $startdate, $enddate, $userActive]);
    } else {
        $query = $conn->prepare($sql);
        $query->execute([$user, $courseActive, $startdate, $enddate]);
    }
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            array_push($course, $res->codecourse);
        }
        return $course;
    } else {
        return NULL;
    }
}

function retallcourseposhtiban($user, $startdate, $enddate)
{
    global $conn;
    $course = array();
    $sql = 'SELECT * FROM `dbcourse` INNER JOIN dbfactor ON dbcourse.numfactor=dbfactor.idfactor WHERE dbcourse.username=? AND dbfactor.datecreate BETWEEN ? AND ?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $startdate, $enddate]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            array_push($course, $res->suporter);
        }
        return $course;
    } else {
        return NULL;
    }
}

function returnAllSupportersWithStatus($user, $startdate, $enddate, $courseActive, $userActive)
{
    global $conn;
    $course = array();
    $sql = 'SELECT * FROM `dbcourse` 
    INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor
    INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse
    WHERE dbcourse.username = ? 
    AND codtbl_course.activecourse = ?
    AND dbfactor.datecreate BETWEEN ? AND ?';

    if ($userActive != 2) {
        $sql .= ' AND dbcourse.activityuser = ?';
        $query = $conn->prepare($sql);
        $query->execute([$user, $courseActive, $startdate, $enddate, $userActive]);
    } else {
        $query = $conn->prepare($sql);
        $query->execute([$user, $courseActive, $startdate, $enddate]);
    }

    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            $course[] = $res->suporter;
        }
    }
    return $course;
}

function retallcoursefactor($user, $startdate, $enddate)
{
    global $conn;
    $course = array();
    $sql = 'SELECT * FROM `dbcourse` INNER JOIN dbfactor ON dbcourse.numfactor=dbfactor.idfactor WHERE dbcourse.username=? AND dbfactor.datecreate BETWEEN ? AND ?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $startdate, $enddate]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            array_push($course, $res->idfactor);
        }
        return $course;
    } else {
        return NULL;
    }
}

function retfactorcourse($user, $idcourse)
{
    global $conn;
    $course = array();
    $sql = 'SELECT * FROM `dbcourse` INNER JOIN dbfactor ON dbcourse.numfactor=dbfactor.idfactor WHERE dbfactor.idfactor=? AND dbcourse.username=?';
    $query = $conn->prepare($sql);
    $query->execute([$idcourse, $user]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            array_push($course, $res->codecourse);
        }
        return $course;
    } else {
        return NULL;
    }
}

function sumfactoruser($codemeli, $startdate, $enddate)
{
    global $conn;
    $code = array();
    $fee = array();
    $sql = 'SELECT * FROM  dbfactor  WHERE username=? AND dbfactor.datecreate BETWEEN ? AND ?';
    $query = $conn->prepare($sql);
    $query->execute([$codemeli, $startdate, $enddate]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            if (!in_array($res->idfactor, $code)) {
                array_push($code, $res->idfactor);
                $ssu = $res->factorfee + $res->wallet;
                array_push($fee, $ssu);
            }
        }
        return array_sum($fee);

    }
}

function checkDublicate($user, $yd)
{
    global $conn;
    global $id;
    global $uss;
    $what = 'SELECT * from stdhozor where `user`=:user';
    $query = $conn->prepare($what);
    $query->bindParam(':user', $user, PDO::PARAM_STR);
    $query->execute();
    $rgb = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 1) {
        foreach ($rgb as $dfv) {
            $id = $dfv->id;
            $uss = $dfv->user;
            if ($id != $yd) {
                $del = 'DELETE FROM stdhozor WHERE id=:id';
                $com = $conn->prepare($del);
                $com->bindParam(':id', $id, PDO::PARAM_STR);
                $com->execute();
            }
        }
    }
}

function ghayeb($user, $timehozor, $act_sec, $coding)
{
    global $conn;
    if ($timehozor > $act_sec) {
        $sql = 'SELECT * FROM stdhozor WHERE `user`=:user ';
        $query = $conn->prepare($sql);
        $query->bindParam(':user', $user, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch();
        $sab = 'SELECT * FROM dbattendance WHERE `username`=? and coding=?';
        $query1 = $conn->prepare($sab);
        $query1->execute([$user, $coding]);
        if ($query1->rowCount() > 0) {
            $up = 'UPDATE dbattendance SET `status`=? WHERE `username`=? and coding=?';
            $query2 = $conn->prepare($up);
            $query2->execute([$result['hozor'], $user, $coding]);

        }
        return true;
    }


}

function countattendance($shenase)
{
    global $conn;
    $sab = 'SELECT COUNT(*) as ghc FROM dbattendance WHERE coding=? AND status=?';
    $query1 = $conn->prepare($sab);
    $query1->execute([$shenase, 'غایب']);
    $ter = $query1->fetch();
    $sab = 'SELECT COUNT(*) as hoco FROM dbattendance WHERE coding=? AND status !=?';
    $query2 = $conn->prepare($sab);
    $query2->execute([$shenase, 'غایب']);
    $ert = $query2->fetch();

    $result = array(
        "ghayeb" => $ter['ghc'],
        "hozor" => $ert['hoco']
    );
    return $result;


}

function rettblattendance($id)
{
    global $conn;
    $sql = 'SELECT * FROM tbl_attendanced WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$id]);
    if ($query->rowCount() > 0) {
        $result = $query->fetch(PDO::FETCH_OBJ);
        return $result;
    }
}

function retattendance($user, $coding)
{
    global $conn;
    $sql = 'SELECT * FROM dbattendance WHERE username=? AND coding=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $coding]);
    if ($query->rowCount() > 0) {
        $result = $query->fetch(PDO::FETCH_OBJ);
        return $result;
    } else {
        return false;
    }
}

function getTblattendance($codecourse, $user)
{
    global $conn;
    $hozor = 0;
    $ghayeb = 0;

    $sql = 'SELECT * FROM tbl_attendanced WHERE codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$codecourse]);

    if ($query->rowCount() > 0) {
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        foreach ($result as $res) {

            $sql = 'SELECT * FROM dbattendance WHERE username=? AND coding=?';
            $query1 = $conn->prepare($sql);
            $query1->execute([$user, $res->id]);

            if ($query1->rowCount() > 0) {
                $qu = $query1->fetch(PDO::FETCH_OBJ);

                // ⬅️ جلسات مشکل‌دار در آمار حساب نمی‌شوند
                if ($qu->status === 'problematic') { // یا 'مشکل' اگر همین را ذخیره می‌کنی
                    continue;
                }

                if ($qu->status === 'غایب') {
                    $ghayeb++;
                } else {
                    $hozor++;
                }
            }
        }
    }

    return [$hozor, $ghayeb];
}

function getTblattendanceWithClass($codecourse, $class, $user)
{
    global $conn;
    $hozor = 0;
    $ghayeb = 0;

    $sql = 'SELECT * FROM tbl_attendanced WHERE codecourse=? AND class_id=?';
    $query = $conn->prepare($sql);
    $query->execute([$codecourse, $class]);

    if ($query->rowCount() > 0) {
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        foreach ($result as $res) {

            $sql = 'SELECT * FROM dbattendance WHERE username=? AND coding=?';
            $query1 = $conn->prepare($sql);
            $query1->execute([$user, $res->id]);

            if ($query1->rowCount() > 0) {
                $qu = $query1->fetch(PDO::FETCH_OBJ);

                // ⬅️ جلسات مشکل‌دار در آمار حساب نمی‌شوند
                if ($qu->status === 'problematic') { // یا 'مشکل' اگر همین را ذخیره می‌کنی
                    continue;
                }

                if ($qu->status === 'غایب') {
                    $ghayeb++;
                } else {
                    $hozor++;
                }
            }
        }
    }

    return [$hozor, $ghayeb];
}

function hasThisCourseAnyMeetings ($code) {
    global $conn;
    $sql = 'SELECT 1 FROM tbl_attendanced WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$code]);
    if ($stmt->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function getTblQuizColor($codecourse, $user)
{
    global $conn;
    global $hozor;
    global $ghayeb;
    $hozor = 0;
    $ghayeb = 0;
    $sql = 'SELECT * FROM dbquiz WHERE codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$codecourse]);
    if ($query->rowCount() > 0) {
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        foreach ($result as $res) {
            $sql = 'SELECT * FROM result_quiz WHERE username=? AND quizid=?';
            $query1 = $conn->prepare($sql);
            $query1->execute([$user, $res->idquiz]);
            if ($query1->rowCount() > 0) {
                $hozor++;
            } else {
                $ghayeb++;
            }
        }
    }
    $exp = array($hozor, $ghayeb);
    return $exp;
}

function retLogViewFilm($codecourse, $user)
{
    global $conn;
    $sql = 'SELECT * FROM logviewfilm WHERE username=? AND codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $codecourse]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }

}

function retLogViewFilmDemo($codecourse, $user)
{
    global $conn;
    $sql = 'SELECT * FROM logviewfilm_demo WHERE username=? AND codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $codecourse]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function retLogViewDoc($codecourse, $user)
{
    global $conn;
    $sql = 'SELECT * FROM logviewdoc WHERE username=? AND codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $codecourse]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }

}

function retLogViewDocDemo($codecourse, $user)
{
    global $conn;
    $sql = 'SELECT * FROM logviewdoc_demo WHERE username=? AND codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $codecourse]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }

}

function returnquiz($user, $quizid)
{
    global $conn;
    $sql = 'SELECT * FROM result_quiz WHERE username=? AND quizid=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $quizid]);
    if ($query->rowCount() > 0) {
        $result = $query->fetch(PDO::FETCH_OBJ);
        return $result;
    } else {
        return false;
    }
}

function retnumFactor($user, $codecourse)
{
    global $conn;
    $sql = 'SELECT * FROM dbcourse WHERE username=? AND codecourse=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $codecourse]);
    if ($query->rowCount() > 0) {
        $result = $query->fetch(PDO::FETCH_OBJ);
        return $result;
    } else {
        return false;
    }
}

function getcourses($codemeli, $num = 1)
{
    global $conn;
    $sql = 'SELECT *,codtbl_course.coursename as cou, codtbl_course.codecourse as coc FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username=?';
    $query = $conn->prepare($sql);
    $query->execute([1, 1, $codemeli]);
    $result = $query->fetchAll();
    if ($query->rowCount() > 0) {
        $value = array();
        $coding = array();
        foreach ($result as $res) {
            array_push($value, $res['cou']);
            array_push($coding, $res['coc']);

        }
        if ($num == 1) {
            return $value;
        } else {
            return $coding;
        }
    }
}

function getsuport_user($codemeli)
{
    global $conn;
    $sql = 'SELECT * FROM suport_user WHERE username=? ';
    $query = $conn->prepare($sql);
    $query->execute([$codemeli]);
    if ($query->rowCount() > 0) {
        $result = $query->fetch();
        return $result;
    } else {
        return false;
    }
}

function retCourse($act = 1)
{
    global $conn;
    $sql = 'SELECT * FROM codtbl_course WHERE activecourse=? ';
    $query = $conn->prepare($sql);
    $query->execute([$act]);
    if ($query->rowCount() > 0) {
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        return $result;
    } else {
        return false;
    }
}

function notSupportAdmin($mode = 1)
{
    global $conn;
    if ($mode == 1) {
        $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.suporter=?';
        $query = $conn->prepare($sql);
        $query->execute([1, 1, $_SESSION['admin']['user']]);
    }
    if ($mode == 2) {
        $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=?';
        $query = $conn->prepare($sql);
        $query->execute([1, 1]);
    }
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    $counter = 0;

    $user = array();
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            if (!in_array($res->username, $user)) {
                array_push($user, $res->username);
                $sql = 'SELECT * FROM suport_user WHERE username=?';
                $exe = $conn->prepare($sql);
                $exe->execute([$res->username]);
                if (!$exe->rowCount() > 0) {
                    $counter++;
                }
            }
        }
    }
    return $counter;
}

function delaySupportAdmin($mode = 1)
{
    global $conn;
    if ($mode == 1) {
        $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.suporter=?';
        $query = $conn->prepare($sql);
        $query->execute([1, 1, $_SESSION['admin']['user']]);
    }
    if ($mode == 2) {
        $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=?';
        $query = $conn->prepare($sql);
        $query->execute([1, 1]);
    }
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    $today = 0;
    $tomarow = 0;
    $last = 0;
    $user = array();
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            if (!in_array($res->username, $user)) {
                array_push($user, $res->username);
                $sql = 'SELECT * FROM suport_user WHERE username=?  ';
                $exe = $conn->prepare($sql);
                $exe->execute([$res->username]);
                $datedelay = $exe->fetchAll(PDO::FETCH_OBJ);
                if ($exe->rowCount() > 0) {
                    foreach ($datedelay as $delay) {
                        $date1 = date_create(date('Y-m-d'));
                        $date2 = date_create($delay->afterdate);
                        $diff = date_diff($date1, $date2);
                        $a = $diff->format("%R%a");
                        if ($a == 0) {
                            $today++;
                        }
                        if ($a == 1) {
                            $tomarow++;
                        }
                        if ($a < 0) {
                            $last++;
                        }
                    }
                }
            }
        }
    }
    $osr = [
        "Last" => $last,
        "Today" => $today,
        "Tomarow" => $tomarow
    ];
    return $osr;
}
function reportCallLMS($start, $end, $op)
{
    global $conn;
    $sql = 'SELECT * FROM suport_report WHERE poshtiban=? AND  datenow BETWEEN ? AND ? ';
    $query = $conn->prepare($sql);
    $query->execute([$op, $start, $end]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    $answered = 0;
    $noanswered = 0;
    $verifytext = 0;
    $notverifytext = 0;
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            if ($res->type == 'تماس تلفنی' and $res->status == 'موفقیت آمیز') {
                $answered++;
            }
            if ($res->type == 'تماس تلفنی' and $res->status == 'عدم پاسخگویی') {
                $noanswered++;
            }
            if ($res->type == 'پشتیبانی متنی' and $res->status == 'موفقیت آمیز') {
                $verifytext++;
            }
            if ($res->type == 'پشتیبانی متنی' and $res->status == 'عدم پاسخگویی') {
                $notverifytext++;
            }
        }
    }
    $totalCall = $answered + $noanswered;
    $totalText = $verifytext + $notverifytext;
    $report = array(
        'LMS TOTAL VoiceCall' => $totalCall,
        'LMS VoiceCall Successful' => $answered,
        'LMS VoiceCall NOT' => $noanswered,
        'LMS TOTAL TEXTSUPPORT' => $totalText,
        'LMS TEXTSUPPORT Successful' => $verifytext,
        'LMS TEXTSUPPORT NOT' => $notverifytext
    );
    return $report;
}
function reportCallLMSmanager($start, $end)
{
    global $conn;
    $sql = 'SELECT * FROM suport_report WHERE   datenow BETWEEN ? AND ? ';
    $query = $conn->prepare($sql);
    $query->execute([$start, $end]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    $answered = 0;
    $noanswered = 0;
    $verifytext = 0;
    $notverifytext = 0;
    if ($query->rowCount() > 0) {
        foreach ($result as $res) {
            if ($res->type == 'تماس تلفنی' and $res->status == 'موفقیت آمیز') {
                $answered++;
            }
            if ($res->type == 'تماس تلفنی' and $res->status == 'عدم پاسخگویی') {
                $noanswered++;
            }
            if ($res->type == 'پشتیبانی متنی' and $res->status == 'موفقیت آمیز') {
                $verifytext++;
            }
            if ($res->type == 'پشتیبانی متنی' and $res->status == 'عدم پاسخگویی') {
                $notverifytext++;
            }
        }
    }
    $totalCall = $answered + $noanswered;
    $totalText = $verifytext + $notverifytext;
    $report = array(
        'LMS TOTAL VoiceCall' => $totalCall,
        'LMS VoiceCall Successful' => $answered,
        'LMS VoiceCall NOT' => $noanswered,
        'LMS TOTAL TEXTSUPPORT' => $totalText,
        'LMS TEXTSUPPORT Successful' => $verifytext,
        'LMS TEXTSUPPORT NOT' => $notverifytext
    );
    return $report;
}
function reportCallSimotel($start, $end, $op, $count = 100000)
{
    $results = ['ANSWERED' => 0, 'NO ANSWERED' => 0, 'TIME ANSWERED' => '00:00:00', 'TIME NO ANSWERED' => '00:00:00', 'TIME TOTAL' => '00:00:00'];
    if (!defined('SIMOTEL_SERVER') || !$op || !function_exists('curl_init')) {
        return $results;
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://' . SIMOTEL_SERVER . '/api/v4/reports/quick/search',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'conditions' => ['from' => (string)$op, 'to' => '', 'cuid' => '', 'type' => 'outgoing'],
            'date_range' => ['from' => $start, 'to' => $end],
            'pagination' => ['start' => 0, 'count' => (int)$count, 'sorting' => new stdClass()],
            'alike' => 'true',
        ]),
        CURLOPT_HTTPHEADER => array(
            'X-APIKEY: ' . SIMOTEL_API_KEY,
            'Authorization: Basic ' . SIMOTEL_BASIC_AUTH,
            'Content-Type: application/json'
        ),
    ));
    $response = json_decode((string)curl_exec($curl));
    curl_close($curl);

    // سیموتل در دسترس نباشد یا پاسخ نامعتبر بدهد: گزارش صفر، بدون هشدار
    $calls = (isset($response->data->data) && is_array($response->data->data)) ? $response->data->data : [];
    $answered = $noanswered = $time_answered = $time_noanswered = 0;
    foreach ($calls as $call) {
        $disposition = $call->disposition ?? '';
        if ($disposition == 'ANSWERED') {
            $answered++;
            $time_answered += (int)($call->duration ?? 0);
        } elseif ($disposition == 'NO ANSWER' || $disposition == 'BUSY') {
            $noanswered++;
            $time_noanswered += (int)($call->duration ?? 0);
        }
    }
    $toTime = static fn($seconds) => sprintf('%02d:%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60), $seconds % 60);

    return [
        'ANSWERED' => $answered,
        'NO ANSWERED' => $noanswered,
        'TIME ANSWERED' => $toTime($time_answered),
        'TIME NO ANSWERED' => $toTime($time_noanswered),
        'TIME TOTAL' => $toTime($time_answered + $time_noanswered),
    ];
}
function countUnseenMessages($user)
{
    global $conn;
    $sql = 'SELECT COUNT(*) as count_Message FROM suport_message WHERE receiver_username = ? AND status = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user, 'sent']);
    $result = $stmt->fetch();
    if ($stmt->rowCount() > 0) {
        return $result;
    } else {
        return false;
    }
}

function didTheUserEnrollThisCourse($user, $code)
{
    global $conn;
    $sql = 'SELECT * FROM dbcourse WHERE username = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user]);
    $courses = $stmt->fetchAll(PDO::FETCH_OBJ);
    foreach ($courses as $course) {
        if ($course->codecourse == $code) {
            return true;
        }
    }
    return false;
}

if (!function_exists('countReportThreadsVisible')) {
    function countReportThreadsVisible( string $me, bool $isManager, bool $canSeeAll): int
    {
        global $conn;
        if ($me === '') return 0;

        $where = ["t.status IN ('open','chatting')"];
        $args  = [];

        if ($isManager) {
            if (!$canSeeAll) {
                $where[] = "(t.responder_username IS NULL OR t.responder_username = ?)";
                $args[]  = $me;
            }
        } else {
            $where[] = "t.operator_username = ?";
            $args[]  = $me;
        }

        $sql = "SELECT COUNT(*) FROM report_threads t WHERE " . implode(' AND ', $where);
        $st  = $conn->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    }
}

function countSuspensionRequestsNeedAction( string $me, bool $isManager, bool $canSeeAll): int
{
    global $conn;
    if ($me === '') return 0;

    $where = ["r.status = 0"]; // ✅ pending = needs action
    $args  = [];

    if ($isManager) {
        if (!$canSeeAll) {
            // ✅ managers with limited scope: show unassigned OR assigned to me
            $where[] = "(r.reviewed_by IS NULL OR r.reviewed_by = ?)";
            $args[]  = $me;
        }
        // ✅ managers with canSeeAll: see all pending, no extra filter
    } else {
        // ✅ non-managers (operators): show my pending requests
        $where[] = "r.requested_by = ?";
        $args[]  = $me;
    }

    $sql = "SELECT COUNT(*) FROM student_suspension_requests r WHERE " . implode(' AND ', $where);
    $st  = $conn->prepare($sql);
    $st->execute($args);
    return (int)$st->fetchColumn();
}

if (!function_exists('countTeacherTicketsNeedAction')) {
    function countTeacherTicketsNeedAction( string $me, int $adminRole, int $administrator): int
    {
        global $conn;
        if ($me === '') return 0;

        $isTeacher    = ($adminRole === 3);
        $isManagement = ($administrator === 1);
        $isMiddle     = (!$isManagement && $adminRole === 2);
        $isOperator   = (!$isManagement && $adminRole === 1);

        $where = ["t.status IN ('open','chatting')"];
        $args  = [];

        if ($isManagement) {
            // all open/chatting tickets
        } elseif ($isTeacher) {
            $where[] = "t.teacher_username = ?";
            $args[]  = $me;
        } elseif ($isMiddle) {
            $where[] = "t.target_group IN ('admins','operators')";
        } elseif ($isOperator) {
            $where[] = "t.target_group = 'operators'";
            $where[] = "t.codemeli IS NOT NULL";
            $where[] = "EXISTS (SELECT 1 FROM dbcourse dc WHERE dc.username = t.codemeli AND dc.suporter = ?)";
            $args[]  = $me;
        } else {
            $where[] = "1=0";
        }

        $sql = "SELECT COUNT(*) FROM teacher_ticket t WHERE " . implode(' AND ', $where);

        $st = $conn->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    }
}

if (!function_exists('countStudentOperatorChanged')) {
    function countStudentOperatorChanged( string $me, int $administrator): int
    {
        global $conn;
        if ($me === '') return 0;

        // Match the page logic: only rows that will actually show (joins included)
        $where = [
            "d.suporter_updated_at IS NOT NULL",
            "d.suporter_updated_at >= (NOW() - INTERVAL 7 DAY)"
        ];
        $args = [];

        // Non-admin sees only their own new assigned students
        if ($administrator !== 1) {
            $where[] = "d.suporter = ?";
            $args[]  = $me;
        }

        $sql = "
            SELECT COUNT(*)
            FROM dbcourse d
            INNER JOIN dbpersonal p ON p.codemeli = d.username
            INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
            WHERE " . implode(' AND ', $where);

        $st = $conn->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    }
}

if (!function_exists('countNewStudent')) {
   if (!function_exists('countNewStudent')) {
    function countNewStudent(string $me, int $administrator): int
    {
        global $conn;
        if ($me === '') return 0;

        $where = [
            "d.first_suporter_assigned_at IS NOT NULL"
        ];
        $args = [];

        if ($administrator !== 1) {
            $where[] = "d.suporter = ?";
            $args[]  = $me;
        }

        $sql = "
            SELECT COUNT(*)
            FROM (
                SELECT
                    d.username AS student_username,
                    d.suporter AS suporter,
                    MAX(d.first_suporter_assigned_at) AS batch_ts
                FROM dbcourse d
                INNER JOIN dbpersonal p ON p.codemeli = d.username
                INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
                WHERE " . implode(' AND ', $where) . "
                GROUP BY d.username, d.suporter
            ) s
            WHERE NOT EXISTS (
                SELECT 1
                FROM admin_tickets t
                WHERE t.type = 'new_students'
                  AND t.ticket_status = 1
                  AND t.student_username = s.student_username
                  AND t.first_suporter_assigned_at = s.batch_ts
            )
        ";

        $st = $conn->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    }
}

}

    function countTeacherManagementticket( string $me, int $administrator): int
    {
        global $conn;
        if ($me === '') return 0;

        $isManagement = ($administrator === 1);

        $where = ["t.status IN ('open','chatting')"];
        $args  = [];

        if ($isManagement) {
            $where[] = "t.target_group IN ('management')";
      } else {
            $where[] = "1=0";
        }

        $sql = "SELECT COUNT(*) FROM teacher_ticket t WHERE " . implode(' AND ', $where);

        $st = $conn->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    }

function countNewStudentticket( string $me, int $administrator): int
{   //op202 //0
    global $conn;
    if ($me === '') return 0;
    $isManagement = ($administrator === 1);

    if ($isManagement) {
        $where[] = "adt.type = 'new_students' AND adt.ticket_status IN ('0','2','3')";
    } else {
        $where[] = "adt.type = 'new_students' AND adt.ticket_status IN ('0','2','3') AND ticket_creator = " . "'$me'";
    }
    $sql = "SELECT COUNT(*) FROM admin_tickets  adt WHERE " . implode(' AND ', $where);

    $st = $conn->prepare($sql);
    $st->execute();
    return (int)$st->fetchColumn();
}

function getUngroupedStudents($codecourse)
{
    global $conn;

    $sql = "
        SELECT 
            dc.username,
            dp.fname,
            dp.lname
        FROM dbcourse dc
        INNER JOIN dbpersonal dp 
            ON dc.username = dp.codemeli
        WHERE dc.codecourse = ?
          AND dc.activityuser = 1
          AND dc.group_id IS NULL
        ORDER BY dp.fname ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codecourse]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
//دانش آموزان موقت امروز

function countTodayUsers(?string $creator = null): int
{
    global $conn;

    $sql = "
        SELECT COUNT(*)
        FROM dbpersonal p
        WHERE CHAR_LENGTH(p.codemeli) = 11
          AND p.datecreated = CURDATE()
    ";

    if ($creator !== null && $creator !== '') {
        $sql .= " AND p.creator = ? ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$creator]);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }

    return (int)$stmt->fetchColumn();
}
//دانش آموزان موقت کانتر

// function countUsers(?string $creator = null): int
// {
//     global $conn;

//     $sql = "
//         SELECT COUNT(*)
//         FROM dbpersonal p
//         WHERE CHAR_LENGTH(p.codemeli) = 11
//     ";

//     if ($creator !== null && $creator !== '') {
//         $sql .= " AND p.creator = ? ";
//         $stmt = $conn->prepare($sql);
//         $stmt->execute([$creator]);
//     } else {
//         $stmt = $conn->prepare($sql);
//         $stmt->execute();
//     }

//     return (int)$stmt->fetchColumn();
// }

function countUsers(?string $creator = null): int
{
    global $conn;

    $sql = "
        SELECT COUNT(*)
        FROM dbpersonal p
        WHERE CHAR_LENGTH(p.codemeli) = 11
          AND NOT EXISTS (
              SELECT 1
              FROM user_account_status uas
              WHERE uas.username = p.codemeli
                AND uas.status = 1
                AND uas.id = (
                    SELECT uas2.id
                    FROM user_account_status uas2
                    WHERE uas2.username = p.codemeli
                    ORDER BY uas2.created_at DESC, uas2.id DESC
                    LIMIT 1
                )
          )
    ";

    $params = [];

    if ($creator !== null && $creator !== '') {
        $sql .= " AND p.creator = ? ";
        $params[] = $creator;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

//دانش آموزان قطعی امروز

function countTodayPermanetUsers(?string $creator = null): int
{
    global $conn;

    $sql = "
        SELECT COUNT(*)
        FROM dbpersonal p
        WHERE  p.datecreated = CURDATE()
        AND LENGTH(p.codemeli) = 10
    ";

    if ($creator !== null && $creator !== '') {
        $sql .= " AND p.creator = ? ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$creator]);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }

    return (int)$stmt->fetchColumn();
}
//دانش آموزان قطعی

function countPermanetUsers(?string $creator = null): int
{
    global $conn;

    // Approx Jalali-year window: Mar 20 -> next Mar 20
    $today = date('Y-m-d');

    $year = (int)substr($today, 0, 4);
    $startThisYear = sprintf('%04d-06-22', $year); // ~ 1 Tir

    if ($today < $startThisYear) {
        $start = sprintf('%04d-06-22', $year - 1);
        $end   = sprintf('%04d-06-22', $year);
    } else {
        $start = $startThisYear;
        $end   = sprintf('%04d-06-22', $year + 1);
    }

    $sql = "
        SELECT COUNT(*)
        FROM dbpersonal p
        WHERE  p.datecreated >= ?
          AND p.datecreated < ?
          AND LENGTH(p.codemeli) = 10
    ";

    $params = [$start, $end];

    if ($creator !== null && $creator !== '') {
        $sql .= " AND p.creator = ? ";
        $params[] = $creator;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}


function allUsersCount(): int
{
    global $conn;

    $sql = "SELECT COUNT(*) FROM dbpersonal";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    return (int)$stmt->fetchColumn();
}

/**
 * Returns the exact bounds of the current school academic year.
 *
 * The academic year starts on 1 Tir and ends on 31 Khordad of the next
 * Jalali year. `end_exclusive_gregorian` is 1 Tir of the next year so SQL
 * filters can safely use a half-open interval for DATE and DATETIME columns.
 */
function getSchoolAcademicYearRange(?int $timestamp = null): array
{
    // The selected archive is stored per administrator session.  Supplying a
    // timestamp deliberately bypasses it, which keeps historical utilities
    // that ask for a specific date deterministic.
    $baseYear = null;
    if ($timestamp === null && isset($_SESSION['academic_year_base'])) {
        $selectedYear = filter_var($_SESSION['academic_year_base'], FILTER_VALIDATE_INT);
        if ($selectedYear !== false && $selectedYear >= 1300 && $selectedYear <= 1600) {
            $baseYear = (int)$selectedYear;
        }
    }
    $timestamp = $timestamp ?? time();

    if ($baseYear === null) {
        $jalaliYear = (int)jdate('Y', $timestamp, '', '', 'en');
        $jalaliMonth = (int)jdate('m', $timestamp, '', '', 'en');
        $baseYear = $jalaliMonth >= 4 ? $jalaliYear : $jalaliYear - 1;
    }

    $startGregorian = jalali_to_gregorian($baseYear, 4, 1);
    $endGregorian = jalali_to_gregorian($baseYear + 1, 3, 31);
    $endExclusiveGregorian = jalali_to_gregorian($baseYear + 1, 4, 1);

    return [
        'base_jalali_year' => $baseYear,
        'start_jalali' => sprintf('%04d/04/01', $baseYear),
        'end_jalali' => sprintf('%04d/03/31', $baseYear + 1),
        'start_gregorian' => sprintf('%04d-%02d-%02d', $startGregorian[0], $startGregorian[1], $startGregorian[2]),
        'end_gregorian' => sprintf('%04d-%02d-%02d', $endGregorian[0], $endGregorian[1], $endGregorian[2]),
        'end_exclusive_gregorian' => sprintf('%04d-%02d-%02d', $endExclusiveGregorian[0], $endExclusiveGregorian[1], $endExclusiveGregorian[2]),
        'label' => sprintf('%04d–%04d', $baseYear + 1, $baseYear),
    ];
}

/**
 * Database identifier for the academic year currently being viewed.
 * Returns null until database/migrate_academic_years.php has been run.
 */
function selectedAcademicYearId(): ?int
{
    global $conn;
    static $resolved = false;
    static $yearId = null;
    if ($resolved) {
        return $yearId;
    }
    $resolved = true;
    try {
        $range = getSchoolAcademicYearRange();
        $stmt = $conn->prepare('SELECT id FROM academic_years WHERE base_jalali_year = ? LIMIT 1');
        $stmt->execute([(int)$range['base_jalali_year']]);
        $id = $stmt->fetchColumn();
        $yearId = $id === false ? null : (int)$id;
    } catch (Throwable $e) {
        // Keep the pre-migration application usable during rollout.
        $yearId = null;
    }
    return $yearId;
}

/** Make a course/base available in the academic year currently being viewed. */
function linkCourseToSelectedAcademicYear(int $courseCode): void
{
    global $conn;
    $yearId = selectedAcademicYearId();
    if ($yearId === null || $courseCode <= 0) {
        return;
    }
    try {
        $stmt = $conn->prepare('INSERT IGNORE INTO academic_year_courses (academic_year_id, codecourse) VALUES (?, ?)');
        $stmt->execute([$yearId, $courseCode]);
    } catch (Throwable $e) {
        // The archive migration may not yet be deployed.
    }
}

/** Set or clear the academic-year view for the current administrator session. */
function setSelectedAcademicYear(?int $baseYear): void
{
    if ($baseYear === null) {
        unset($_SESSION['academic_year_base']);
        return;
    }

    $_SESSION['academic_year_base'] = $baseYear;
}

/** Returns selectable school years, including the current year. */
function getSelectableAcademicYears(int $firstYear = 1400): array
{
    $current = getSchoolAcademicYearRange(time())['base_jalali_year'];
    $years = [];
    for ($year = $current; $year >= $firstYear; $year--) {
        $start = jalali_to_gregorian($year, 4, 1);
        $end = jalali_to_gregorian($year + 1, 4, 1);
        $years[] = [
            'base_jalali_year' => $year,
            'start_jalali' => sprintf('%04d/04/01', $year),
            'end_jalali' => sprintf('%04d/03/31', $year + 1),
            'start_gregorian' => sprintf('%04d-%02d-%02d', $start[0], $start[1], $start[2]),
            'end_exclusive_gregorian' => sprintf('%04d-%02d-%02d', $end[0], $end[1], $end[2]),
            'label' => sprintf('%04d–%04d', $year, $year + 1),
        ];
    }
    return $years;
}
//1tir to 1 tir
function getJalaliYearWindowApprox(?string $today = null): array
{
    if ($today === null && isset($_SESSION['academic_year_base'])) {
        $range = getSchoolAcademicYearRange();
        return [$range['start_gregorian'], $range['end_exclusive_gregorian']];
    }
    $today = $today ?: date('Y-m-d');

    $year = (int)substr($today, 0, 4);
    $startThisYear = sprintf('%04d-06-22', $year); // ~ 1 Tir

    if ($today < $startThisYear) {
        $start = sprintf('%04d-06-22', $year - 1);
        $end   = sprintf('%04d-06-22', $year);
    } else {
        $start = $startThisYear;
        $end   = sprintf('%04d-06-22', $year + 1);
    }

    return [$start, $end];
}
function countbackfromcrmOP(?string $creator = null): int
{
    global $conn;

    if (empty($creator)) {
        return 0;
    }

    $sql = "
        SELECT COUNT(*)
        FROM admin_tickets
        WHERE type = 'reactivate_temp_user'
          AND ticket_status IN (0,3)
          AND ticket_creator = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$creator]);

    return (int)$stmt->fetchColumn();
}

function countSmsInJalaliYearWindow(?string $today = null): array
{
    global $conn;

    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    // Uses your existing function
    [$start, $end] = getJalaliYearWindowApprox($today);

    // db_smsun count
    $sqlSmsun = "
        SELECT COUNT(id) AS smsun
        FROM db_smsun
        WHERE dateresponse >= :start AND dateresponse < :end
    ";
    $stSmsun = $conn->prepare($sqlSmsun);
    $stSmsun->execute([
        ':start' => $start,
        ':end'   => $end,
    ]);
    $rec = $stSmsun->fetch(PDO::FETCH_ASSOC);
    $smsun = (int)($rec['smsun'] ?? 0);

    // tel_sms count
    $sqlTelSms = "
        SELECT COUNT(id) AS telsms
        FROM tel_sms
        WHERE datecreated >= :start AND datecreated < :end
    ";
    $stTelSms = $conn->prepare($sqlTelSms);
    $stTelSms->execute([
        ':start' => $start,
        ':end'   => $end,
    ]);
    $rec1 = $stTelSms->fetch(PDO::FETCH_ASSOC);
    $telsms = (int)($rec1['telsms'] ?? 0);

    return [
        'start'  => $start,
        'end'    => $end,
        'smsun'  => $smsun,
        'telsms' => $telsms,
        'total'  => $smsun + $telsms,
    ];
}

function countDbquizInJalaliYearWindow(?string $today = null): array
{
    global $conn;

    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    // Uses your existing function
    [$start, $end] = getJalaliYearWindowApprox($today);

    $sql = "
        SELECT COUNT(idquiz) AS quizzes
        FROM dbquiz
        WHERE datestart >= :start AND datestart < :end
    ";

    $st = $conn->prepare($sql);
    $st->execute([
        ':start' => $start,
        ':end'   => $end,
    ]);

    $row = $st->fetch(PDO::FETCH_ASSOC);
    $quizzes = (int)($row['quizzes'] ?? 0);

    return [
        'start'   => $start,
        'end'     => $end,
        'quizzes' => $quizzes,
    ];
}

function countAttendanceInJalaliYearWindow(?string $today = null): array
{
    global $conn;

    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    // Uses your existing function
    [$start, $end] = getJalaliYearWindowApprox($today);

    $sql = "
        SELECT COUNT(id) AS attendance
        FROM tbl_attendanced
        WHERE datestart >= :start AND datestart < :end
    ";

    $st = $conn->prepare($sql);
    $st->execute([
        ':start' => $start,
        ':end'   => $end,
    ]);

    $row = $st->fetch(PDO::FETCH_ASSOC);
    $attendance = (int)($row['attendance'] ?? 0);

    return [
        'start'      => $start,
        'end'        => $end,
        'attendance' => $attendance,
    ];
}

function normalizeCodemeli($id): string {
    $id = trim((string)$id);
    // make sure it's digits; if not, just return as-is
    if (!preg_match('/^\d+$/', $id)) return $id;

    if (strlen($id) == 8) return '00' . $id;
    if (strlen($id) == 9) return '0' . $id;
    return $id; // already 10 typically
}

function countSentInRecipients(?string $recipientsJson): int {
    if (!$recipientsJson) return 0;
    $arr = json_decode($recipientsJson, true);
    if (!is_array($arr)) return 0;

    $count = 0;
    foreach ($arr as $item) {
        if (($item['status'] ?? '') === 'ارسال شده') $count++;
    }
    return $count;
}

function getSmsCountForStudent(PDO $conn, string $codemeli, string $startDateG, string $endDateG): int {
    $total = 0;

    // ✅ 1) db_smsun : response length 19 means sent
    $q1 = $conn->prepare("
        SELECT COUNT(*) 
        FROM db_smsun
        WHERE username = ?
          AND dateresponse BETWEEN ? AND ?
          AND CHAR_LENGTH(response) = 19
    ");
    $q1->execute([$codemeli, $startDateG, $endDateG]);
    $total += (int)$q1->fetchColumn();

    // ✅ 2) sms_logs : parse recipients JSON & count status='ارسال شده'
    $q2 = $conn->prepare("
        SELECT recipients
        FROM sms_logs
        WHERE LPAD(student_id, 10, '0') = ?
          AND send_date BETWEEN ? AND ?
    ");
    $q2->execute([$codemeli, $startDateG, $endDateG]);

    while ($r = $q2->fetch(PDO::FETCH_ASSOC)) {
        $total += countSentInRecipients($r['recipients'] ?? '');
    }

    return $total;
}
if (!function_exists('digitsOnly')) {
    function digitsOnly(?string $s): string {
        return $s ? preg_replace('/\D+/', '', $s) : '';
    }
}
if (!function_exists('last10')) {
    function last10(string $d): ?string {
        if ($d === '') return null;
        $k = substr($d, -10);
        return (strlen($k) === 10) ? $k : null;
    }
}
if (!function_exists('norm10')) {
    function norm10(?string $n): ?string {
        $n = digitsOnly($n);
        return $n ? last10($n) : null;
    }
}
if (!function_exists('secToHMS')) {
    function secToHMS(int $seconds): string {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

if (!function_exists('simotelMultiQuery')) {
    function simotelMultiQuery(string $url, array $headers, array $payloads, int $timeoutSec = 20): array {
        if (empty($payloads)) return [];

        $mh = curl_multi_init();
        $chs = [];

        foreach ($payloads as $body) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeoutSec,
            ]);
            curl_multi_add_handle($mh, $ch);
            $chs[] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running > 0 && $status == CURLM_OK);

        $allRows = [];

        foreach ($chs as $ch) {
            $resp = curl_multi_getcontent($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($http >= 200 && $http < 300 && !empty($resp)) {
                $data = json_decode($resp, true);
                $rows = $data['data']['data'] ?? ($data['data'] ?? []);
                if (is_array($rows) && !empty($rows)) {
                    foreach ($rows as $r) $allRows[] = $r;
                }
            }
        }

        curl_multi_close($mh);
        return $allRows;
    }
}

if (!function_exists('getTalkTimeForStudent')) {
    function getTalkTimeForStudent(array $numbers10, string $fromDT, string $toDT, string $apiUrl, array $apiHeaders): int {
        $numbers10 = array_values(array_unique(array_filter($numbers10)));
        if (empty($numbers10)) return 0;

        // Build OUT payloads (match "to")
        $outPayloads = [];
        foreach ($numbers10 as $n) {
            $outPayloads[] = [
                "conditions" => (object)[
                    "to"   => (string)$n,
                    "type" => "outgoing",
                ],
                "date_range"  => ["from" => $fromDT, "to" => $toDT],
                "pagination"  => ["start" => 0, "count" => 1000, "sorting" => (object)[]],
                "alike"       => "true",
            ];
        }

        // Build IN payloads (match "from")
        $inPayloads = [];
        foreach ($numbers10 as $n) {
            $inPayloads[] = [
                "conditions" => (object)[
                    "from" => (string)$n,
                    "type" => "incoming",
                ],
                "date_range"  => ["from" => $fromDT, "to" => $toDT],
                "pagination"  => ["start" => 0, "count" => 1000, "sorting" => (object)[]],
                "alike"       => "true",
            ];
        }

        $total = 0;

        // OUT: sum duration (ANSWERED only)
        $outRows = simotelMultiQuery($apiUrl, $apiHeaders, $outPayloads, 20);
        foreach ($outRows as $r) {
            $disp = strtoupper(trim((string)($r['disposition'] ?? '')));
            if ($disp !== 'ANSWERED') continue;
            $sec = (int)($r['duration'] ?? 0);
            if ($sec > 0) $total += $sec;
        }

        // IN: sum billsec (ANSWERED only)
        $inRows = simotelMultiQuery($apiUrl, $apiHeaders, $inPayloads, 20);
        foreach ($inRows as $r) {
            $disp = strtoupper(trim((string)($r['disposition'] ?? '')));
            if ($disp !== 'ANSWERED') continue;
            $sec = (int)($r['billsec'] ?? 0);
            if ($sec > 0) $total += $sec;
        }

        return $total;
    }
}


function countSuspensionReviewedUnseen(string $me, int $administrator): int
{
    global $conn;
    if ($me === '' || (int)$administrator === 1) return 0;

    $sql = "
        SELECT COUNT(*)
        FROM student_suspension_requests r
        WHERE r.requested_by = ?
          AND r.status IN (1,2)
          AND r.reviewed_at IS NOT NULL
          AND r.seen_at IS NULL
    ";
    $st = $conn->prepare($sql);
    $st->execute([$me]);
    return (int)$st->fetchColumn();
}

function countCancelOpReviewedUnseen(string $me, int $administrator): int
{
    global $conn;
    if ($me === '' || (int)$administrator === 1) return 0;

    $sql = "
        SELECT COUNT(*)
        FROM cancel_student_operator_request r
        WHERE r.requested_by = ?
          AND r.status IN (1,2)
          AND r.reviewed_at IS NOT NULL
          AND r.seen_at IS NULL
    ";
    $st = $conn->prepare($sql);
    $st->execute([$me]);
    return (int)$st->fetchColumn();
}

function countInstallmentOverdue(string $me, int $installment_manager): int
{
    global $conn;
    if ($me === '') return 0;

    $isManagement = ($installment_manager === 1);
    $where = [];

    $where[] = "status = 'pending'"; // Filter for pending status
    $where[] = "due_date < CURDATE()"; // Check if due date is before today's date

    if ($isManagement) {

    } else {
        $where[] = "adt.operator_username = " . "'$me'";
    }

    $sql = "SELECT COUNT(*) FROM payment_installments adt WHERE " . implode(' AND ', $where);

    $st = $conn->prepare($sql);
    $st->execute();
    return (int)$st->fetchColumn();
}

function countPendingTicketReferrals(string $me): int
{
    global $conn;

    if ($me === '') return 0;

    $sql = "
        SELECT COUNT(*) 
        FROM ticket_referrals tr
        INNER JOIN dbadmin a ON tr.referred_to_id = a.idadmin
        WHERE a.username = ? 
          AND tr.status IS NULL
    ";

    $st = $conn->prepare($sql);
    $st->execute([$me]);

    return (int)$st->fetchColumn();
}

function countPendingHomeworkAdmin(): int
{
    global $conn;

    $sql = "SELECT COUNT(*) FROM homework_admin WHERE status = 'pending'";
    $st = $conn->prepare($sql);
    $st->execute();

    return (int)$st->fetchColumn();
}

/**
 * Fetch all event answers for a set of students and events.
 * Returns: [username][eventId] => ['option' => text, 'reason' => text]
 */
function getEventAnswers(PDO $conn, array $studentCodes, array $eventIds): array {
    if (empty($studentCodes) || empty($eventIds)) {
        return [];
    }
    $stuPl = implode(',', array_fill(0, count($studentCodes), '?'));
    $evPl  = implode(',', array_fill(0, count($eventIds), '?'));
    $sql = "
        SELECT a.username, a.event_id, o.events_option, a.reason
        FROM active_event_answers a
        JOIN active_event_options o ON a.option_id = o.id
        WHERE a.event_id IN ($evPl)
          AND a.username IN ($stuPl)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge($eventIds, $studentCodes));
    $answers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $answers[$row['username']][$row['event_id']] = [
            'option' => $row['events_option'],
            'reason' => $row['reason'] ?? ''
        ];
    }
    return $answers;
}

/**
 * Check overlapping calendar events
 */

  function checkOverlapConflict($conn, $currentEventId, $startGreg, $endGreg, $targetGroup, $selectedCourses, $adminRoles, $adminUsers, $isAllAdmins) {
    $end = $endGreg ?? $startGreg;

    $sql = "SELECT e.* FROM calendar_events e 
            WHERE e.overlap = 1 
              AND e.start_date < :end 
              AND (e.end_date IS NULL OR e.end_date > :start) 
              AND e.id != :id";
    $params = [':start' => $startGreg, ':end' => $end, ':id' => $currentEventId];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $conflicting = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($conflicting)) return null;

    $roleNames = [
        0 => 'مدیریت', 1 => 'پشتیبان', 2 => 'مدیرمیانی', 3 => t('role_teacher'),
        6 => 'کارشناس فروش', 51 => 'معاونت اجرایی', 52 => 'معاونت آموزشی',
        53 => 'معاونت فروش', 54 => 'معاونت فنی', 55 => 'معاونت مالی'
    ];
    $adminNameCache = [];
    $getAdminName = function($adminId) use ($conn, &$adminNameCache) {
        if (!isset($adminNameCache[$adminId])) {
            $stmt = $conn->prepare("SELECT fname, lname FROM dbadmin WHERE idadmin = ?");
            $stmt->execute([$adminId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $adminNameCache[$adminId] = $row ? $row['fname'] . ' ' . $row['lname'] : 'نامشخص';
        }
        return $adminNameCache[$adminId];
    };
    $courseNameCache = [];
    $getCourseName = function($courseId) use ($conn, &$courseNameCache) {
        if (!isset($courseNameCache[$courseId])) {
            $stmt = $conn->prepare("SELECT coursename FROM codtbl_course WHERE idcourse = ?");
            $stmt->execute([$courseId]);
            $courseNameCache[$courseId] = $stmt->fetchColumn() ?: 'پایه نامشخص';
        }
        return $courseNameCache[$courseId];
    };
    $getAdminRole = function($adminId) use ($conn, &$adminNameCache) {
        if (!isset($adminNameCache['role_'.$adminId])) {
            $stmt = $conn->prepare("SELECT adminrole FROM dbadmin WHERE idadmin = ?");
            $stmt->execute([$adminId]);
            $adminNameCache['role_'.$adminId] = $stmt->fetchColumn();
        }
        return $adminNameCache['role_'.$adminId];
    };

    foreach ($conflicting as $event) {
        $eTarget = $event['target_type'];
        $conflictTitle = "رویداد «{$event['title']}»";

        // ---- COURSE OVERLAP ----
        if (($targetGroup === 'user' || $targetGroup === 'both') && ($eTarget === 'user' || $eTarget === 'both')) {
            $eCourses = [];
            $cStmt = $conn->prepare("SELECT course_id FROM calendar_course_targets WHERE event_id = ?");
            $cStmt->execute([$event['id']]);
            while ($c = $cStmt->fetch(PDO::FETCH_ASSOC)) $eCourses[] = $c['course_id'];

            if (empty($selectedCourses) && empty($eCourses)) {
                return "$conflictTitle قبلاً برای همه کاربران در این بازه ثبت شده است.";
            }
            if (!empty($selectedCourses) && !empty($eCourses)) {
                $intersect = array_intersect($selectedCourses, $eCourses);
                if (!empty($intersect)) {
                    $names = array_map($getCourseName, $intersect);
                    return "$conflictTitle با پایه‌های " . implode('، ', $names) . " تداخل دارد.";
                }
            }
            if (empty($selectedCourses) && !empty($eCourses)) {
                return "$conflictTitle برای پایه‌های مشخصی ثبت شده و با این رویداد (همه کاربران) تداخل دارد.";
            }
            if (!empty($selectedCourses) && empty($eCourses)) {
                return "$conflictTitle برای همه کاربران ثبت شده و با پایه‌های انتخابی شما تداخل دارد.";
            }
        }

        // ---- ADMIN OVERLAP ----
        if (($targetGroup === 'admin' || $targetGroup === 'both') && ($eTarget === 'admin' || $eTarget === 'both')) {
            $eRoles = [];
            $rStmt = $conn->prepare("SELECT adminrole FROM calendar_admin_role_targets WHERE event_id = ?");
            $rStmt->execute([$event['id']]);
            while ($r = $rStmt->fetch(PDO::FETCH_ASSOC)) $eRoles[] = $r['adminrole'];

            $eAdmins = [];
            $aStmt = $conn->prepare("SELECT admin_id FROM calendar_admin_user_targets WHERE event_id = ?");
            $aStmt->execute([$event['id']]);
            while ($a = $aStmt->fetch(PDO::FETCH_ASSOC)) $eAdmins[] = $a['admin_id'];

            $isAllExisting = empty($eRoles) && empty($eAdmins);

            // ---- Core checks ----
            if ($isAllAdmins && $isAllExisting) {
                return "$conflictTitle قبلاً برای همه ادمین‌ها در این بازه ثبت شده است.";
            }
            if ($isAllAdmins && !$isAllExisting) {
                return "$conflictTitle برای ادمین‌های خاصی ثبت شده و با انتخاب همه ادمین‌ها تداخل دارد.";
            }
            if (!$isAllAdmins && $isAllExisting) {
                return "$conflictTitle برای همه ادمین‌ها ثبت شده و با انتخاب خاص شما تداخل دارد.";
            }

            // Role – Role intersection
            if (!empty($adminRoles) && !empty($eRoles)) {
                $intersectRoles = array_intersect($adminRoles, $eRoles);
                if (!empty($intersectRoles)) {
                    $roleNamesList = array_map(function($r) use ($roleNames) { return $roleNames[$r] ?? $r; }, $intersectRoles);
                    return "$conflictTitle با نقش‌های " . implode('، ', $roleNamesList) . " تداخل دارد.";
                }
            }

            // Admin – Admin intersection
            if (!empty($adminUsers) && !empty($eAdmins)) {
                $intersectAdmins = array_intersect($adminUsers, $eAdmins);
                if (!empty($intersectAdmins)) {
                    $names = array_map($getAdminName, $intersectAdmins);
                    return "$conflictTitle با کارمندان " . implode('، ', $names) . " تداخل دارد.";
                }
            }

            // New roles vs existing specific admins
            if (!empty($adminRoles) && !empty($eAdmins)) {
                $conflictAdmins = [];
                foreach ($eAdmins as $eAid) {
                    if (in_array($getAdminRole($eAid), $adminRoles)) {
                        $conflictAdmins[] = $eAid;
                    }
                }
                if (!empty($conflictAdmins)) {
                    $names = array_map($getAdminName, $conflictAdmins);
                    return "$conflictTitle شامل کارمندانی (" . implode('، ', $names) . ") است که دارای نقش‌های انتخابی شما هستند.";
                }
            }

            // Existing roles vs new specific admins
            if (!empty($eRoles) && !empty($adminUsers)) {
                $conflictAdmins = [];
                foreach ($adminUsers as $newAid) {
                    if (in_array($getAdminRole($newAid), $eRoles)) {
                        $conflictAdmins[] = $newAid;
                    }
                }
                if (!empty($conflictAdmins)) {
                    $names = array_map($getAdminName, $conflictAdmins);
                    return "$conflictTitle شامل نقش‌هایی است که کارمندان انتخابی شما (" . implode('، ', $names) . ") دارای آن هستند.";
                }
            }
        }
    }
    return null;
}
function toGregorian($jalaliStr, $time = null) {
    if (empty($jalaliStr)) return null;

    // 1. Convert Persian and Arabic digits to standard English ASCII digits
    $clean = function_exists('tr_num')
        ? tr_num(trim($jalaliStr), 'en')
        : str_replace(
            ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
            ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'],
            trim($jalaliStr)
        );

    // 2. Replace dot and dash delimiters with slash
    $clean = str_replace(['-', '.'], '/', $clean);
    $parts = explode('/', $clean);

    if (count($parts) !== 3) return null;

    // 3. Force integer casting to eliminate jdf string-concatenation bugs
    $jy = (int)$parts[0];
    $jm = (int)$parts[1];
    $jd = (int)$parts[2];

    if ($jy > 1200 && $jm >= 1 && $jm <= 12 && $jd >= 1 && $jd <= 31) {
        $g = jalali_to_gregorian($jy, $jm, $jd);
        if (is_array($g) && count($g) === 3 && (int)$g[0] > 0) {
            $dateStr = sprintf('%04d-%02d-%02d', (int)$g[0], (int)$g[1], (int)$g[2]);

            if ($time === true) return $dateStr . ' 23:59:59';
            if ($time === false) return $dateStr . ' 00:00:00';
            if (is_string($time) && !empty($time)) return $dateStr . ' ' . trim($time);

            return $dateStr;
        }
    }

    return null;
}

function toJalali($gregorianStr, $format = 'Y/m/d', $digits = 'en') {
    if (empty($gregorianStr) || $gregorianStr === '0000-00-00 00:00:00' || $gregorianStr === '0000-00-00') {
        return '';
    }

    $ts = strtotime($gregorianStr);
    if (!$ts) return '';

    return jdate($format, $ts, '', 'Asia/Tehran', $digits);
}

/**
 * سال تحصیلی جاری را بر می گرداند.
 * از شهریور به بعد سال تحصیلی با سال شمسی جاری شروع شده است؛
 * فروردین تا خرداد هنوز ادامه سال تحصیلی قبل است؛
 * تیر و مرداد بین دو سال تحصیلی است و سال تحصیلی پیشِ رو در نظر گرفته می شود.
 */
function registerClassAcademicYear(int $ts): int
{
    $jy = (int)jdate('Y', $ts, '', 'Asia/Tehran', 'en');
    $jm = (int)jdate('n', $ts, '', 'Asia/Tehran', 'en');
    return ($jm <= 3) ? $jy - 1 : $jy;
}

/**
 * هفته های یک سال تحصیلی را از اولین شنبه ی شهریور تا پایان خرداد می سازد.
 * نام هر هفته از ماهی گرفته می شود که بیشتر روزهای هفته در آن است
 * (روز چهارم هفته، یعنی سه شنبه، معیار قرار می گیرد).
 */
function registerClassWeeks(int $jy): array
{
    $ordinals = ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم'];
    $monthNames = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                   'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    $gs = jalali_to_gregorian($jy, 6, 1);
    $startTs = mktime(0, 0, 0, (int)$gs[1], (int)$gs[2], (int)$gs[0]);
    $ge = jalali_to_gregorian($jy + 1, 3, 31);
    $endTs = mktime(0, 0, 0, (int)$ge[1], (int)$ge[2], (int)$ge[0]);

    // عقب رفتن تا شنبه ی همان هفته (در PHP شنبه برابر ۶ است)
    $back = ((int)date('w', $startTs) + 1) % 7;
    $cursor = strtotime('-' . $back . ' days', $startTs);

    $weeks = [];
    $counters = [];
    while ($cursor <= $endTs) {
        $weekEndTs = strtotime('+6 days', $cursor);
        $midTs     = strtotime('+3 days', $cursor);

        $jm = (int)jdate('n', $midTs, '', 'Asia/Tehran', 'en');
        $jyy = (int)jdate('Y', $midTs, '', 'Asia/Tehran', 'en');
        // هفته ی پایانی ممکن است وسطش به تیر بیفتد؛ در این صورت نام از روز شنبه گرفته می شود
        if ($jm === 4 || $jm === 5) {
            $jm  = (int)jdate('n', $cursor, '', 'Asia/Tehran', 'en');
            $jyy = (int)jdate('Y', $cursor, '', 'Asia/Tehran', 'en');
        }

        $bucket = $jyy . '-' . $jm;
        $counters[$bucket] = ($counters[$bucket] ?? 0) + 1;
        $nth = $counters[$bucket];

        $weeks[] = [
            'key'   => date('Y-m-d', $cursor),
            'start' => date('Y-m-d', $cursor),
            'end'   => date('Y-m-d', $weekEndTs),
            'label' => 'هفته‌ی ' . ($ordinals[$nth - 1] ?? $nth) . ' ' . $monthNames[$jm],
            'range' => jdate('Y/m/d', $cursor) . ' تا ' . jdate('Y/m/d', $weekEndTs),
        ];

        $cursor = strtotime('+7 days', $cursor);
    }

    return $weeks;
}

/**
 * هفته ای که یک ستون دفتر کلاسی به آن تعلق دارد.
 * ملاک، تاریخ ستون است و اگر ستون تاریخ نداشته باشد، تاریخ ساخته شدن آن.
 * ستون هایی که تاریخشان بیرون از سال تحصیلی است به اولین یا آخرین هفته نسبت داده
 * می شوند تا هیچ ستونی از دسترس خارج نشود.
 */
function registerClassEventWeekKey(array $event, array $weeks): string
{
    if (empty($weeks)) {
        return '';
    }

    $day = !empty($event['event_date'])
        ? substr($event['event_date'], 0, 10)
        : substr($event['created_at'] ?? '', 0, 10);

    if ($day === '') {
        return $weeks[0]['key'];
    }

    $firstStart = $weeks[0]['start'];
    $lastEnd    = $weeks[count($weeks) - 1]['end'];
    if ($day < $firstStart) {
        $day = $firstStart;
    } elseif ($day > $lastEnd) {
        $day = $lastEnd;
    }

    foreach ($weeks as $week) {
        if ($day >= $week['start'] && $day <= $week['end']) {
            return $week['key'];
        }
    }

    return $weeks[0]['key'];
}

/**
 * هفته ای که تاریخ داده شده در آن قرار دارد؛ اگر بیرون از سال تحصیلی بود، اولین هفته.
 */
function registerClassWeekKeyFor(array $weeks, int $ts): string
{
    $today = date('Y-m-d', $ts);
    foreach ($weeks as $week) {
        if ($today >= $week['start'] && $today <= $week['end']) {
            return $week['key'];
        }
    }
    return $weeks[0]['key'] ?? '';
}

function getTeachersList(): array
{
    global $conn;

    $sql = "
        SELECT *
        FROM dbadmin 
        WHERE adminrole = 3 
    ";

    $st = $conn->prepare($sql);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Create a new class event (Exam or Attendance)
 */
function createRegisterClassEvent(
    int $classId,
    int $createdBy,
    int $teacherId,
    string $title,
    ?string $eventDate,
    int $type,
    string $description = '',
    ?string $groupUid = null,
    string $homeworkNotificationTarget = 'family',
    ?int $darsId = null
): bool {
    global $conn;

    if ($classId <= 0 || $createdBy <= 0 || $teacherId <= 0 || $title === '') {
        return false;
    }

    // تاریخ اختیاری است؛ رشته خالی به NULL تبدیل می شود
    $eventDate = ($eventDate === '' || $eventDate === null) ? null : $eventDate;

    if (!in_array($homeworkNotificationTarget, ['family', 'family_student'], true)) {
        $homeworkNotificationTarget = 'family';
    }

    $sql = "
        INSERT INTO register_class_events
            (class_id, teacher_id, dars_id, title, event_date, group_uid, description, type,
             homework_notification_target, status, created_by)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, '0', ?)
    ";

    $st = $conn->prepare($sql);
    return $st->execute([
        $classId,
        $teacherId, $darsId,
        $title,
        $eventDate,
        $groupUid,
        $description,
        $type,
        $homeworkNotificationTarget,
        $createdBy
    ]);
}

/**
 * انواع ستون های دفتر کلاسی
 * برای هر بار افزودن، این ۵ ستون پشت سر هم ساخته می شوند.
 * date_bound یعنی تاریخ زیر عنوان ستون نمایش داده می شود (فقط حضور و غیاب).
 */
function registerClassColumnKinds(): array
{
    return [
        ['type' => 0, 'title' => 'حضور و غیاب دستی',   'date_bound' => true,  'input' => 'attendance', 'quality' => false],
        ['type' => 2, 'title' => 'حضور و غیاب آنلاین', 'date_bound' => true,  'input' => 'attendance', 'quality' => false],
        // نمره کلاسی دو بخش دارد: عدد و ارزیابی کیفی
        ['type' => 1, 'title' => 'نمره کلاسی',          'date_bound' => false, 'input' => 'score',      'quality' => true],
        ['type' => 4, 'title' => 'تراز آزمون آنلاین',   'date_bound' => false, 'input' => 'score',      'quality' => false],
        ['type' => 3, 'title' => 'توضیحات',             'date_bound' => false, 'input' => 'note',       'quality' => false],
    ];
}

/**
 * گزینه های ارزیابی کیفی ستون نمره کلاسی
 */
function registerClassQualityOptions(): array
{
    return [
        1 => 'خیلی خوب',
        2 => 'خوب',
        3 => 'قابل قبول',
        4 => 'نیاز به تلاش بیشتر',
    ];
}

/**
 * حذف یک دسته کامل از ستون ها به همراه رکوردهای مرتبط
 */
function deleteRegisterClassEventGroup(int $classId, string $groupUid): bool
{
    global $conn;
    if ($classId <= 0 || $groupUid === '') {
        return false;
    }

    $st = $conn->prepare("SELECT id FROM register_class_events WHERE class_id = ? AND group_uid = ?");
    $st->execute([$classId, $groupUid]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (empty($ids)) {
        return false;
    }

    $in = implode(',', array_fill(0, count($ids), '?'));
    $conn->prepare("DELETE FROM register_class_evaluation WHERE register_class_events_id IN ($in)")->execute($ids);
    $conn->prepare("DELETE FROM register_class_attendance WHERE register_class_events_id IN ($in)")->execute($ids);
    return $conn->prepare("DELETE FROM register_class_events WHERE id IN ($in)")->execute($ids);
}


function registerClassLog(
    int $classId,
        $eventName = '',
    string $username = '',
    ?float $score = null,
    ?int $statusCode = null,
    ?string $disciplinaryReason = '',
    $createdBy = '',
    string $actionType = 'log_entry'
): bool {
    global $conn;

    if ($classId <= 0) {
        return false;
    }

    $rawBody = [
        'action_type'         => $actionType,
        'course_class'        => $classId,
        'event_name'          => (string)$eventName,
        'username'            => $username,
        'score'               => $score,
        'status_code'         => $statusCode,
        'disciplinary_reason' => $disciplinaryReason,
        'created_by'          => (string)$createdBy
    ];

    $cleanBody = array_filter($rawBody, function ($value) {
        return $value !== null && $value !== '';
    });

    $jsonBody = json_encode($cleanBody, JSON_UNESCAPED_UNICODE);

    $sql = "INSERT INTO register_class_log (body, created_at) VALUES (?, NOW())";

    try {
        $st = $conn->prepare($sql);
        return $st->execute([$jsonBody]);
    } catch (PDOException $e) {
        error_log("registerClassLog Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch list of enrolled students in a class
 */

function getRegisterClassStudents(int $classId): array
{
    global $conn;
    if ($classId <= 0) return [];

    $sql = "
        SELECT 
            c.username, 
            p.fname, 
            p.lname 
        FROM dbcourse c
        INNER JOIN dbpersonal p ON c.username = p.codemeli
        WHERE c.class_id = ? 
        ORDER BY c.idcourse ASC
    ";

    $st = $conn->prepare($sql);
    $st->execute([$classId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Fetch active events for a class created by a specific admin/teacher
 */
function getRegisterClassEvents(int $classId, int $createdBy, int $adminrole): array
{
    global $conn;
    if ($classId <= 0) {
        return [];
    }

    $sql = "SELECT ev.*, d.dars AS dars_name
            FROM register_class_events ev
            LEFT JOIN dbdars d ON d.id = ev.dars_id
            WHERE ev.class_id = ?";
    $params = [$classId];

    if ($adminrole === 3 && $createdBy > 0) {
        $sql .= " AND ev.teacher_id = ?";
        $params[] = $createdBy;
    }

    // تاریخ قدیمی تر اول؛ داخل هر دسته، ترتیب ثابت انواع ستون رعایت می شود
    // (حضور دستی، حضور آنلاین، نمره کلاسی، تراز آزمون، توضیحات)
    // به این ترتیب دسته های قدیمی هم با همین چیدمان نمایش داده می شوند.
    $kindOrder = implode(',', array_map(
        static fn($k) => "'" . $k['type'] . "'",
        registerClassColumnKinds()
    ));
    // ستون های بدون تاریخ انتهای جدول قرار می گیرند
    $sql .= " ORDER BY (ev.event_date IS NULL) ASC, ev.event_date ASC, ev.group_uid ASC,
              FIELD(ev.type, $kindOrder), ev.id ASC";

    $st = $conn->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function saveStudentRegisterClassEntry(
    int $classId,
    int $eventId,
    string $studentUsername,
    ?float $score = null,
    ?int $statusCode = null,
    ?string $disciplinaryReason = null,
    string $createdBy = '',
    ?string $note = null,
    ?int $quality = null,
    string $visibilityTarget = 'both'
): bool {
    global $conn;

    if ($classId <= 0 || $eventId <= 0 || empty($studentUsername)) {
        return false;
    }

    if (!in_array($visibilityTarget, ['student', 'family', 'both'], true)) {
        $visibilityTarget = 'both';
    }

    if ($score !== null || $quality !== null) {
        $check = $conn->prepare("SELECT id FROM register_class_evaluation
                                 WHERE register_class_events_id = ? AND username = ?");
        $check->execute([$eventId, $studentUsername]);

        // ارزیابی کیفی: null یعنی دست نخورد، صفر یعنی پاک شود
        $qualityValue = ($quality !== null && $quality > 0) ? $quality : null;

        if ($check->fetch()) {
            $sets = [];
            $vals = [];
            if ($score !== null)   { $sets[] = 'score = ?';   $vals[] = $score; }
            if ($quality !== null) { $sets[] = 'quality = ?'; $vals[] = $qualityValue; }
            $sets[] = 'visibility_target = ?';
            $vals[] = $visibilityTarget;
            $sets[] = 'created_by = ?';
            $vals[] = $createdBy;
            $sets[] = 'created_at = NOW()';
            $vals[] = $eventId;
            $vals[] = $studentUsername;

            $stmt = $conn->prepare("UPDATE register_class_evaluation
                                    SET " . implode(', ', $sets) . "
                                    WHERE register_class_events_id = ? AND username = ?");
            $stmt->execute($vals);
        } else {
            $stmt = $conn->prepare("INSERT INTO register_class_evaluation
                                    (register_class_events_id, username, score, quality, visibility_target, created_by, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$eventId, $studentUsername, $score, $qualityValue, $visibilityTarget, $createdBy]);
        }
    }

    // ستون توضیحات؛ رشته خالی یعنی پاک کردن متن
    if ($note !== null) {
        $noteValue = ($note === '') ? null : mb_substr($note, 0, 500);

        $check = $conn->prepare("SELECT id FROM register_class_evaluation
                                 WHERE register_class_events_id = ? AND username = ?");
        $check->execute([$eventId, $studentUsername]);

        if ($check->fetch()) {
            $stmt = $conn->prepare("UPDATE register_class_evaluation
                                    SET note = ?, created_by = ?, created_at = NOW()
                                    WHERE register_class_events_id = ? AND username = ?");
            $stmt->execute([$noteValue, $createdBy, $eventId, $studentUsername]);
        } else {
            $stmt = $conn->prepare("INSERT INTO register_class_evaluation
                                    (register_class_events_id, username, note, created_by, created_at)
                                    VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$eventId, $studentUsername, $noteValue, $createdBy]);
        }
    }

    if ($statusCode !== null) {
        $dbStatus = ($statusCode == 1) ? '1' : '0';

        $check = $conn->prepare("SELECT id FROM register_class_attendance 
                                 WHERE register_class_events_id = ? AND username = ?");
        $check->execute([$eventId, $studentUsername]);

        if ($check->fetch()) {
            $stmt = $conn->prepare("UPDATE register_class_attendance 
                                    SET status = ?, created_by = ?, created_at = NOW()
                                    WHERE register_class_events_id = ? AND username = ?");
            $stmt->execute([$dbStatus, $createdBy, $eventId, $studentUsername]);
        } else {
            $stmt = $conn->prepare("INSERT INTO register_class_attendance 
                                    (register_class_events_id, username, status, created_by, created_at)
                                    VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$eventId, $studentUsername, $dbStatus, $createdBy]);
        }
    }

    $contextStmt = $conn->prepare('SELECT c.codecourse, c.name AS class_name,
            ev.title AS event_title, ev.event_date, ev.type AS event_type
        FROM register_class_events ev
        INNER JOIN classes c ON c.id = ev.class_id
        WHERE ev.id = ? AND ev.class_id = ? LIMIT 1');
    $contextStmt->execute([$eventId, $classId]);
    $context = $contextStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $className = (string)($context['class_name'] ?? '');
    $eventTitle = trim((string)($context['event_title'] ?? ''));
    $eventDate = trim((string)($context['event_date'] ?? ''));
    $eventDescription = ($className !== '' ? 'کلاس: ' . $className : '');
    if ($eventTitle !== '') {
        $eventDescription .= ($eventDescription !== '' ? ' - ' : '') . 'جلسه: ' . $eventTitle;
    }
    if ($eventDate !== '') {
        $eventDescription .= ($eventDescription !== '' ? ' - ' : '') . 'تاریخ: ' . $eventDate;
    }
    $actorAdminId = ctype_digit((string)$createdBy) ? (int)$createdBy : null;
    $actorUsername = $actorAdminId ? null : $createdBy;

    if ($score !== null || $quality !== null) {
        CreateSchoolNotification(
            'exam',
            'ثبت آزمون و نمره جدید' . ($className !== '' ? ' - ' . $className : ''),
            $eventDescription,
            $actorAdminId,
            $actorUsername,
            isset($context['codecourse']) ? (int)$context['codecourse'] : null,
            $classId,
            null,
            'admin/course/class-register-notification.php?event=' . $eventId,
            'register_class_exam_event',
            (string)$eventId
        );
    }
    if ($statusCode !== null) {
        CreateSchoolNotification(
            'attendance',
            'ثبت حضور و غیاب جدید' . ($className !== '' ? ' - ' . $className : ''),
            $eventDescription,
            $actorAdminId,
            $actorUsername,
            isset($context['codecourse']) ? (int)$context['codecourse'] : null,
            $classId,
            null,
            'admin/course/class-register-notification.php?event=' . $eventId,
            'register_class_attendance_event',
            (string)$eventId
        );
    }

    registerClassLog(
        $classId,
        $eventId,
        $studentUsername,
        $score,
        $statusCode,
        trim($disciplinaryReason ?? ''),
        $createdBy,
        'log_entry'
    );

    return true;
}


function getRegisterClassEvaluations(int $classId, int $adminId, int $adminRole): array
{
    global $conn;
    $sql = "
        SELECT e.register_class_events_id AS event_id, e.username, e.score, e.note, e.quality,
               e.visibility_target
        FROM register_class_evaluation e
        INNER JOIN register_class_events ev ON e.register_class_events_id = ev.id
        WHERE ev.class_id = ?
    ";

    if ($adminRole === 3) {
        $sql .= " AND ev.teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$classId, $adminId]);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$classId]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function getRegisterClassAttendance(int $classId, int $adminId, int $adminRole): array
{
    global $conn;
    $sql = "
        SELECT a.register_class_events_id AS event_id, a.username, a.status
        FROM register_class_attendance a
        INNER JOIN register_class_events ev ON a.register_class_events_id = ev.id
        WHERE ev.class_id = ?
    ";

    if ($adminRole === 3) {
        $sql .= " AND ev.teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$classId, $adminId]);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$classId]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSingleRegisterClassEvent(int $eventId): ?array {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM register_class_events WHERE id = ?");
    $stmt->execute([$eventId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function deleteRegisterClassEvent(int $eventId): bool {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM register_class_events WHERE id = ?");
    return $stmt->execute([$eventId]);
}
function updateRegisterClassEvent(int $eventId, string $title, ?string $eventDate, int $type, ?int $darsId = null): bool {
    global $conn;
    $eventDate = ($eventDate === '' || $eventDate === null) ? null : $eventDate;
    $stmt = $conn->prepare("UPDATE register_class_events SET title=?, event_date=?, type=?, dars_id=? WHERE id=?");
    return $stmt->execute([$title, $eventDate, $type, $darsId, $eventId]);
}

/** Visible classroom grades for the authenticated student. */
function GetStudentVisibleClassGrades(string $studentUsername, int $limit = 100, ?int $codecourse = null): array
{
    global $conn;
    if ($studentUsername === '') return [];
    $limit = max(1, min(300, $limit));

    $sql = "SELECT DISTINCT e.id, e.score, e.quality, e.note, e.created_at, e.visibility_target,
                   ev.id AS event_id, ev.title, ev.event_date, ev.type, ev.dars_id,
                   c.id AS class_id, c.name AS class_name, c.codecourse,
                   cr.coursename, d.dars AS dars_name,
                   TRIM(CONCAT(COALESCE(a.fname, ''), ' ', COALESCE(a.lname, ''))) AS teacher_name,
                   a.username AS teacher_username
            FROM register_class_evaluation e
            INNER JOIN register_class_events ev ON ev.id = e.register_class_events_id
            INNER JOIN classes c ON c.id = ev.class_id
            INNER JOIN dbcourse dc ON dc.class_id = c.id AND dc.username = e.username
            INNER JOIN codtbl_course cr ON cr.codecourse = c.codecourse
            LEFT JOIN dbdars d ON d.id = ev.dars_id
            LEFT JOIN dbadmin a ON a.idadmin = ev.teacher_id
            WHERE e.username = ?
              AND e.visibility_target IN ('student', 'both')
              AND ev.type = '1'
              AND (e.score IS NOT NULL OR e.quality IS NOT NULL)
              AND dc.activityuser = 1
            " . (($codecourse !== null && $codecourse > 0) ? " AND c.codecourse = ?" : "") . "
            ORDER BY COALESCE(ev.event_date, DATE(e.created_at)) DESC, e.created_at DESC, e.id DESC
            LIMIT {$limit}";
    $stmt = $conn->prepare($sql);
    $params = [$studentUsername];
    if ($codecourse !== null && $codecourse > 0) $params[] = $codecourse;
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function deleteStudentRegisterClassEntry(int $classId, int $eventId, string $studentUsername): bool {
    global $conn;
    $conn->prepare("DELETE FROM register_class_evaluation WHERE register_class_events_id=? AND username=?")->execute([$eventId, $studentUsername]);
    $conn->prepare("DELETE FROM register_class_attendance WHERE register_class_events_id=? AND username=?")->execute([$eventId, $studentUsername]);
    return true;
}
function getTeacherUsernamesByIds(array $teacherIds): array {
    global $conn;
    if (empty($teacherIds)) return [];
    $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
    $sql = "SELECT idadmin, username FROM dbadmin WHERE idadmin IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_values($teacherIds));
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * نام نمایشی معلم‌ها بر اساس شناسه مدیر؛ در نبود نام، نام کاربری برگردانده می‌شود.
 */
function getTeacherFullNamesByIds(array $teacherIds): array {
    global $conn;
    if (empty($teacherIds)) return [];
    $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
    $sql = "SELECT idadmin, fname, lname, username FROM dbadmin WHERE idadmin IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_values($teacherIds));

    $names = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $full = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
        $names[$row['idadmin']] = ($full !== '') ? $full : $row['username'];
    }
    return $names;
}

function saveDisciplineEntry(int $classId, string $username, string $reason, int $createdBy): bool {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO register_class_discipline (username, reason, class_id, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt->execute([$username, $reason, $classId, $createdBy])) {
        return false;
    }
    $disciplineId = (string)$conn->lastInsertId();
    $contextStmt = $conn->prepare('SELECT c.codecourse, c.name AS class_name, p.fname, p.lname
        FROM classes c LEFT JOIN dbpersonal p ON p.codemeli = ? WHERE c.id = ? LIMIT 1');
    $contextStmt->execute([$username, $classId]);
    $context = $contextStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $studentName = trim((string)($context['fname'] ?? '') . ' ' . (string)($context['lname'] ?? ''));
    CreateSchoolNotification(
        'discipline',
        'ثبت مورد انضباطی' . ($studentName !== '' ? ' برای ' . $studentName : ''),
        $reason,
        $createdBy,
        null,
        isset($context['codecourse']) ? (int)$context['codecourse'] : null,
        $classId,
        $username,
        'admin/course/class-register.php?class=' . $classId,
        'register_class_discipline',
        $disciplineId
    );
    return true;
}

function getClassDisciplinaries(int $classId, int $adminId = 0, int $adminRole = 0): array {
    global $conn;

    $sql = "
        SELECT d.id, d.username, d.reason, d.created_at, d.created_by, a.username AS created_by_username
        FROM register_class_discipline d
        LEFT JOIN dbadmin a ON d.created_by = a.idadmin
        WHERE d.class_id = ?
    ";
    $params = [$classId];

    // Teacher (role 3) sees only their own
    if ($adminRole === 3 && $adminId > 0) {
        $sql .= " AND d.created_by = ?";
        $params[] = $adminId;
    }

    $sql .= " ORDER BY d.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteDisciplineEntry(int $id): bool {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM register_class_discipline WHERE id = ?");
    return $stmt->execute([$id]);
}


function updateDisciplineEntry(int $id, string $reason): bool {
    global $conn;
    $stmt = $conn->prepare("UPDATE register_class_discipline SET reason = ? WHERE id = ?");
    return $stmt->execute([$reason, $id]);
}


function getSingleDisciplineEntry(int $id): ?array {
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, reason, class_id, created_by, created_at FROM register_class_discipline WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* =====================================================================
 * گروه های آموزشی معلمان
 * هر معلم می تواند عضو چند گروه باشد و هر رویداد می تواند به چند گروه
 * محدود شود؛ در آن صورت فقط اعضای آن گروه ها رویداد را می بینند.
 * ===================================================================== */

function getTeacherGroups(): array
{
    global $conn;
    $st = $conn->query("SELECT id, title, description FROM teacher_groups ORDER BY title ASC");
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function createTeacherGroup(string $title, string $description = '', ?int $createdBy = null): bool
{
    global $conn;
    $title = trim($title);
    if ($title === '') return false;
    $st = $conn->prepare("INSERT INTO teacher_groups (title, description, created_by) VALUES (?, ?, ?)");
    try {
        return $st->execute([$title, ($description === '' ? null : $description), $createdBy]);
    } catch (PDOException $e) {
        return false; // عنوان تکراری
    }
}

function updateTeacherGroup(int $id, string $title, string $description = ''): bool
{
    global $conn;
    $title = trim($title);
    if ($id <= 0 || $title === '') return false;
    $st = $conn->prepare("UPDATE teacher_groups SET title = ?, description = ? WHERE id = ?");
    try {
        return $st->execute([$title, ($description === '' ? null : $description), $id]);
    } catch (PDOException $e) {
        return false;
    }
}

function deleteTeacherGroup(int $id): bool
{
    global $conn;
    if ($id <= 0) return false;
    // عضویت ها و هدف گذاری رویدادها با ON DELETE CASCADE پاک می شوند
    $st = $conn->prepare("DELETE FROM teacher_groups WHERE id = ?");
    return $st->execute([$id]);
}

/** شناسه گروه های یک معلم */
function getTeacherGroupIdsForAdmin(int $adminId): array
{
    global $conn;
    if ($adminId <= 0) return [];
    $st = $conn->prepare("SELECT group_id FROM teacher_group_members WHERE admin_id = ?");
    $st->execute([$adminId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

/** جایگزینی کامل گروه های یک معلم */
function setTeacherGroupsForAdmin(int $adminId, array $groupIds): bool
{
    global $conn;
    if ($adminId <= 0) return false;
    $conn->prepare("DELETE FROM teacher_group_members WHERE admin_id = ?")->execute([$adminId]);
    if (empty($groupIds)) return true;
    $st = $conn->prepare("INSERT IGNORE INTO teacher_group_members (group_id, admin_id) VALUES (?, ?)");
    foreach ($groupIds as $gid) {
        $gid = (int)$gid;
        if ($gid > 0) $st->execute([$gid, $adminId]);
    }
    return true;
}

/** گروه هایی که اجازه دیدن یک رویداد را دارند */
function getEventTeacherGroups(int $eventId): array
{
    global $conn;
    if ($eventId <= 0) return [];
    $st = $conn->prepare("SELECT group_id FROM calendar_event_teacher_groups WHERE event_id = ?");
    $st->execute([$eventId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

/** جایگزینی کامل گروه های مجاز یک رویداد */
function setEventTeacherGroups(int $eventId, array $groupIds): bool
{
    global $conn;
    if ($eventId <= 0) return false;
    $conn->prepare("DELETE FROM calendar_event_teacher_groups WHERE event_id = ?")->execute([$eventId]);
    if (empty($groupIds)) return true;
    $st = $conn->prepare("INSERT IGNORE INTO calendar_event_teacher_groups (event_id, group_id) VALUES (?, ?)");
    foreach ($groupIds as $gid) {
        $gid = (int)$gid;
        if ($gid > 0) $st->execute([$eventId, $gid]);
    }
    return true;
}

/*
 * Most admin pages execute their POST handlers before including admin/header.
 * Run the same route gate at the end of this common bootstrap as well, so a
 * direct POST cannot bypass the tree check. Non-admin pages and unmapped
 * endpoints are deliberately left alone.
 */
if (!empty($_SESSION['admin']) && isset($_SERVER['SCRIPT_NAME']) && str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), '/admin/')) {
    EnforceAdminPageTreePermission();
}

/*
 * پایه‌های پیش‌فرض تقویم: پایه اول تا دوازدهم با رنگ‌های ثابت، برای مدرسه و
 * مؤسسه یکسان. هر پنل فقط یک بار آن‌ها را می‌گیرد (نشانه در app_seeds)، پس
 * پایه‌ای که مدیر حذف کند دوباره ساخته نمی‌شود.
 *
 * پایه‌ها در همان جدول calendar_groups با is_grade = 1 از گروه‌های قدیمی جدا
 * می‌شوند. تقویم و «مدیریت پایه‌ها» فقط پایه‌ها را نشان می‌دهند؛ گروه‌های قدیمی
 * پاک نمی‌شوند و رویدادهایشان «بدون پایه» دیده می‌شوند.
 */
// عنوان => [رنگ، رنگ دوم]. رنگ دوم یعنی طیف: از رنگ اول (راست) به رنگ دوم (چپ).
// دهم تا دوازدهم هر کدام سه رشته دارند: رنگ پایه به رنگ رشته (انسانی سبز، ریاضی قرمز، تجربی آبی).
const CALENDAR_DEFAULT_GRADES = [
    'پایه اول'              => ['#18E877', null],
    'پایه دوم'              => ['#9AEB1A', null],
    'پایه سوم'              => ['#FFE900', null],
    'پایه چهارم'            => ['#FFB300', null],
    'پایه پنجم'             => ['#F57300', null],
    'پایه ششم'              => ['#F55656', null],
    'پایه هفتم'             => ['#FA1F4F', null],
    'پایه هشتم'             => ['#E82DF5', null],
    'پایه نهم'              => ['#962DF5', null],
    'پایه دهم انسانی'       => ['#2E43F6', '#128710'],
    'پایه دهم ریاضی'        => ['#2E43F6', '#E90013'],
    'پایه دهم تجربی'        => ['#2E43F6', '#0000FF'],
    'پایه یازدهم انسانی'    => ['#0FA4F4', '#128710'],
    'پایه یازدهم ریاضی'     => ['#0FA4F4', '#E90013'],
    'پایه یازدهم تجربی'     => ['#0FA4F4', '#0000FF'],
    'پایه دوازدهم انسانی'   => ['#2FF4E0', '#128710'],
    'پایه دوازدهم ریاضی'    => ['#2FF4E0', '#E90013'],
    'پایه دوازدهم تجربی'    => ['#2FF4E0', '#0000FF'],
];

// پایه های یکپارچه ی قبلی که جایشان را سه رشته گرفته است؛ پنهان می شوند، پاک نمی شوند
const CALENDAR_RETIRED_GRADES = ['پایه دهم', 'پایه یازدهم', 'پایه دوازدهم'];

/*
 * نشانه‌ی رویداد: دایره‌ی رنگی کنار هر رویداد در همه‌ی تقویم‌ها. رنگ‌ها عمداً
 * با رنگ پایه‌ها قاطی نمی‌شوند: قرمز و سبز تیره‌اند، آبی و صورتی روشن، و دور دایره
 * همیشه حلقه‌ی سفید دارد.
 */
const CALENDAR_EVENT_MARKERS = [
    'online'         => ['label' => 'کلاس آنلاین استودیویی',  'color' => '#1CBFFF'],
    'online_home'    => ['label' => 'کلاس آنلاین غیر استودیویی', 'color' => '#C2410C'],
    'offline'        => ['label' => 'کلاس آفلاین',           'color' => '#6B7280'],
    'animation'      => ['label' => 'کلاس پویانمایی',         'color' => '#B8860B'],
    'cancel_school'  => ['label' => 'کنسل توسط آموزشگاه',    'color' => '#B00020'],
    'cancel_teacher' => ['label' => 'کنسل توسط معلم',        'color' => '#FFB3D9'],
    'extra'          => ['label' => 'فوق برنامه',            'color' => '#111111'],
    'exam'           => ['label' => 'آزمون',                 'color' => '#0B6B2E'],
];

/** CSS background for a پایه: its colour, or a right-to-left gradient when it has a second colour. */
function calendarGradeBackground(string $color, ?string $color2): string
{
    return $color2 ? "linear-gradient(to left, $color, $color2)" : $color;
}

function calendarEventMarker(?string $key): ?string
{
    return ($key !== null && isset(CALENDAR_EVENT_MARKERS[$key])) ? $key : null;
}

function calendarSeedDefaultGrades(PDO $conn, int $createdBy): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS app_seeds (name VARCHAR(64) NOT NULL PRIMARY KEY, done_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $seeded = $conn->query("SELECT name FROM app_seeds WHERE name LIKE 'calendar_%'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('calendar_event_marker', $seeded, true)) {
            if (!$conn->query("SHOW COLUMNS FROM calendar_events LIKE 'marker'")->fetchColumn()) {
                $conn->exec("ALTER TABLE calendar_events ADD COLUMN marker VARCHAR(20) NULL DEFAULT NULL");
            }
            $conn->exec("INSERT IGNORE INTO app_seeds (name) VALUES ('calendar_event_marker')");
        }
        if (!in_array('calendar_grades_flag', $seeded, true)) {
            if (!$conn->query("SHOW COLUMNS FROM calendar_groups LIKE 'is_grade'")->fetchColumn()) {
                $conn->exec("ALTER TABLE calendar_groups ADD COLUMN is_grade TINYINT(1) NOT NULL DEFAULT 0");
            }
            $mark = $conn->prepare("UPDATE calendar_groups SET is_grade = 1 WHERE title = ?");
            foreach (array_keys(CALENDAR_DEFAULT_GRADES) as $title) $mark->execute([$title]);
            $conn->exec("INSERT IGNORE INTO app_seeds (name) VALUES ('calendar_grades_flag')");
        }
        if (!in_array('calendar_grade_color2', $seeded, true)) {
            if (!$conn->query("SHOW COLUMNS FROM calendar_groups LIKE 'color2'")->fetchColumn()) {
                $conn->exec("ALTER TABLE calendar_groups ADD COLUMN color2 VARCHAR(20) NULL DEFAULT NULL");
            }
            $conn->exec("INSERT IGNORE INTO app_seeds (name) VALUES ('calendar_grade_color2')");
        }
        $exists = $conn->prepare("SELECT 1 FROM calendar_groups WHERE title = ? LIMIT 1");
        $insert = $conn->prepare("INSERT INTO calendar_groups (title, color, color2, description, created_by, is_grade) VALUES (?, ?, ?, 'پایه پیش‌فرض', ?, 1)");
        if (!in_array('calendar_default_grades', $seeded, true)) {
            foreach (CALENDAR_DEFAULT_GRADES as $title => [$color, $color2]) {
                $exists->execute([$title]);
                if (!$exists->fetchColumn()) $insert->execute([$title, $color, $color2, $createdBy]);
            }
            $conn->exec("INSERT IGNORE INTO app_seeds (name) VALUES ('calendar_default_grades')");
            $conn->exec("INSERT IGNORE INTO app_seeds (name) VALUES ('calendar_grade_tracks')");
        }
        // پنل هایی که دهم/یازدهم/دوازدهمِ یکپارچه را گرفته بودند: سه رشته جایگزین می شود
        if (!in_array('calendar_grade_tracks', $seeded, true)) {
            $retire = $conn->prepare("UPDATE calendar_groups SET is_grade = 0 WHERE title = ? AND is_grade = 1");
            foreach (CALENDAR_RETIRED_GRADES as $title) $retire->execute([$title]);
            foreach (CALENDAR_DEFAULT_GRADES as $title => [$color, $color2]) {
                if ($color2 === null) continue;
                $exists->execute([$title]);
                if (!$exists->fetchColumn()) $insert->execute([$title, $color, $color2, $createdBy]);
            }
            $conn->exec("INSERT IGNORE INTO app_seeds (name) VALUES ('calendar_grade_tracks')");
        }
    } catch (PDOException $e) {
        error_log('calendar default grades: ' . $e->getMessage());
    }
}

/** ORDER BY for the پایه‌ها: پایه اول…دوازدهم in grade order, then any پایه the admin added. */
function calendarGroupsOrderSql(string $alias = ''): string
{
    $col = ($alias !== '' ? $alias . '.' : '') . 'title';
    $list = implode(',', array_map(fn($t) => "'" . $t . "'", array_keys(CALENDAR_DEFAULT_GRADES)));
    return "FIELD($col, $list) = 0, FIELD($col, $list), " . ($alias !== '' ? $alias . '.' : '') . 'id';
}

