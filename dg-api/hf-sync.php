<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../dbconfig.php';

try {
    $license = hf_control_fetch_license($conn);
    $sync = hf_control_sync($conn);
    echo json_encode(['ok' => true, 'license' => $license, 'sync' => $sync], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
