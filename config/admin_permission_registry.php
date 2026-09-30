<?php

return (static function (): array {
    $registry = [];

    foreach (glob(__DIR__ . '/admin_permissions/*.php') as $file) {
        $section = (static fn(string $path): array => require $path)($file);

        foreach ($section['nodes'] as $node) {
            $registry[] = $node;
        }
    }

    return $registry;
})();
