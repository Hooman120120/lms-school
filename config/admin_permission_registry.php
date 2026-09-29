<?php
/*
 * The staff/teacher permission tree, one file per menu section in
 * config/admin_permissions/. Each file returns
 *     ['nodes' => [top-level node, ...], 'convert' => [code => rule, ...]]
 * A node: 'code', 'label', optional 'labels' (per institution type),
 * 'modes' (['school'] / ['institute']), 'path' and 'paths' (the admin pages
 * this node is), 'children'. See inc/admin_permissions.php.
 * Section files are read inside a function so their helper variables stay local.
 */
return (static function (): array {
    $registry = [];
    foreach (glob(__DIR__ . '/admin_permissions/*.php') as $file) {
        $section = (static fn(string $path): array => require $path)($file);
        foreach ($section['nodes'] as $node) $registry[] = $node;
    }
    return $registry;
})();
