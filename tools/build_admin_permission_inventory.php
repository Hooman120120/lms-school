<?php
/**
 * Read-only inventory of admin UI actions. Run after adding/changing menu
 * pages, then register the reviewed nodes in config/admin_permission_registry.php.
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
$root = dirname(__DIR__);
$admin = $root . '/admin';
$result = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($admin, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (strtolower($file->getExtension()) !== 'php') continue;
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $source = file_get_contents($file->getPathname());
    $actions = [];
    preg_match_all('/href\s*=\s*["\']([^"\']+\.php[^"\']*)/i', $source, $links);
    foreach ($links[1] as $link) $actions[] = ['kind' => 'link', 'target' => $link];
    preg_match_all('/(?:name|id)\s*=\s*["\']([^"\']*(?:add|create|edit|delete|remove|approve|reject|export|import|save)[^"\']*)/i', $source, $controls);
    foreach ($controls[1] as $control) $actions[] = ['kind' => 'control', 'target' => $control];
    if ($actions) $result[$path] = $actions;
}
ksort($result);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
