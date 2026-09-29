<?php
/* Conversion rules of every section file; see inc/admin_permissions_convert.php. */
return (static function (): array {
    $rules = [];
    foreach (glob(__DIR__ . '/admin_permissions/*.php') as $file) {
        $section = (static fn(string $path): array => require $path)($file);
        $rules += $section['convert'] ?? [];
    }
    return $rules;
})();
