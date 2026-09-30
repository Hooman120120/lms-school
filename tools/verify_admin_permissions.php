<?php
/*
 * Checks the permission tree against the code (php tools/verify_admin_permissions.php):
 *  - codes are unique, a child's code starts with its parent's, page paths exist;
 *  - every admin script is a page of the tree, open to every account, or an include;
 *  - every code the code checks (AdminCan, AdminCanSee, AdminRequire, AdminScope ...) exists;
 *  - every conversion rule names an existing node.
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
define('BASE_PATH', dirname(__DIR__));
function inst_type(): string { return 'school'; }
function inst_is(string $t): bool { return $t === 'school'; }
function inst_display_text(string $s): string { return $s; }
require BASE_PATH . '/inc/admin_permissions.php';

/* Admin files that are included by pages, never opened on their own. */
const INCLUDE_ONLY = [
    'admin/admin-menu.php', 'admin/ostad-menu.php', 'admin/menu.php', 'admin/header.php', 'admin/link.php',
    'admin/info-tooltip.php', 'admin/panel/calendar.php', 'admin/autodialer/autodialer_functions.php',
    'admin/Student/temporary-registration-form.php', 'admin/Doc/document-security.php',
];
/* Called by outside services without a staff session (the gate needs a session). */
const EXTERNAL_ENDPOINTS = [
    'admin/Tel/get_sms.php', 'admin/Bill/excelapi.php', 'admin/Bill/excelapi-demo.php',
    'admin/course/retry_failed_requests.php', 'admin/Bill/ajax/get_sms_detail.php',
];
/* Retired: deliberately outside the tree, so the gate refuses them. */
const RETIRED = ['admin/setting/setting.php', 'admin/setting/levels.php', 'admin/setting/blacklist.php', 'admin/tasks/assigned-tasks.php'];

$errors = [];
$index = [];
$paths = [];
$walk = function (array $nodes, ?string $parent) use (&$walk, &$index, &$paths, &$errors): void {
    foreach ($nodes as $node) {
        $code = $node['code'] ?? null;
        if (!$code || empty($node['label'])) { $errors[] = "node without code or label under $parent"; continue; }
        if (isset($index[$code])) $errors[] = "duplicate code $code";
        if ($parent !== null && !str_starts_with($code, $parent . '.')) $errors[] = "$code is not named under its parent $parent";
        $index[$code] = true;
        foreach (array_merge(isset($node['path']) ? [$node['path']] : [], $node['paths'] ?? []) as $path) {
            if (!is_file(BASE_PATH . '/' . $path)) $errors[] = "$code: missing page $path";
            $paths[$path][] = $code;
        }
        $walk($node['children'] ?? [], $code);
    }
};
$walk(AdminPermissionRegistry(), null);

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/admin', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $rel = 'admin/' . str_replace('\\', '/', substr($file->getPathname(), strlen(BASE_PATH . '/admin/')));
    if (isset($paths[$rel]) || in_array($rel, ADMIN_PAGES_FOR_EVERY_ACCOUNT, true) || in_array($rel, INCLUDE_ONLY, true)
        || in_array($rel, EXTERNAL_ENDPOINTS, true) || in_array($rel, RETIRED, true)) continue;
    $errors[] = "admin script outside the tree: $rel";
}

$sources = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS));
$checked = 0;
foreach ($sources as $file) {
    if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
    $source = file_get_contents($file->getPathname());
    preg_match_all("/\b(AdminCan|AdminCanSee|AdminCanAny|AdminRequire|AdminScope|AdminCanAccessFinanceFactor|AdminFilterFinanceFactors)\(([^;]*?)\)/", $source, $calls, PREG_SET_ORDER);
    foreach ($calls as [$all, $fn, $args]) {
        $args = preg_replace("/AdminNotificationCode\('[^']*'(\))?/", '', $args);
        preg_match_all("/'([a-z][a-z0-9_-]*(?:\.[a-z0-9_-]+)+)'/", $args, $codes);
        foreach ($codes[1] as $code) {
            $checked++;
            $scoped = in_array($fn, ['AdminScope', 'AdminCanAccessFinanceFactor', 'AdminFilterFinanceFactors'], true);
            $missing = $scoped ? !(isset($index[$code . '.own']) || isset($index[$code . '.all'])) : !isset($index[$code]);
            if ($missing) $errors[] = basename($file->getPathname()) . ": $fn('$code') is not in the tree";
        }
    }
    preg_match_all("/AdminNotificationCode\('([a-z0-9_.-]+)'\)/", $source, $notif);
    foreach ($notif[1] as $suffix) {
        $checked++;
        if (!isset($index['school-notifications.' . $suffix]) || !isset($index['institute-notifications.' . $suffix])) {
            $errors[] = basename($file->getPathname()) . ": notification code '$suffix' is not in the tree";
        }
    }
}

$rules = require BASE_PATH . '/config/admin_permission_conversion.php';
foreach (array_keys($rules) as $key) {
    $code = str_ends_with($key, '@open') ? substr($key, 0, -5) : $key;
    if (!isset($index[$code])) $errors[] = "conversion rule for unknown node $key";
}

foreach ($errors as $error) echo "ERROR $error\n";
printf("%d nodes, %d pages, %d checked codes, %d conversion rules: %s\n",
    count($index), count($paths), $checked, count($rules), $errors ? count($errors) . ' error(s)' : 'OK');
exit($errors ? 1 : 0);
