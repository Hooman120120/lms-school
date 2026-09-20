<?php

function cross_panel_secret(): string
{
    return trim((string) getenv('CROSS_PANEL_SECRET'));
}

function cross_panel_oes_url(): string
{
    $configured = trim((string) getenv('CROSS_PANEL_OES_URL'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    return rtrim(currentDomain(), '/') . '/oes-school';
}

function cross_panel_signature(string $action, string $username, int $expires, string $body = ''): string
{
    return hash_hmac('sha256', implode("\n", [$action, $username, (string) $expires, hash('sha256', $body)]), cross_panel_secret());
}

function cross_panel_login_url(string $username): string
{
    $expires = time() + 60;
    $signature = cross_panel_signature('login', $username, $expires);
    return cross_panel_oes_url() . '/cross-panel-login.php?' . http_build_query([
        'username' => $username,
        'expires' => $expires,
        'signature' => $signature,
    ]);
}

function cross_panel_oes_role(?int $adminRole, ?string $schoolRoleKey): int
{
    if ($adminRole === 0) {
        return 1;
    }
    if ($adminRole === 3) {
        return 3;
    }
    if ($schoolRoleKey === 'counselor' || $adminRole === 1) {
        return 4;
    }
    return 2;
}

function cross_panel_sync_oes_admin(array $admin): array
{
    $secret = cross_panel_secret();
    if ($secret === '') {
        return ['ok' => false, 'message' => 'کلید ارتباط بین پنل‌ها تنظیم نشده است'];
    }

    $body = json_encode($admin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $expires = time() + 60;
    $username = (string) ($admin['username'] ?? '');
    $headers = [
        'Content-Type: application/json',
        'X-Cross-Username: ' . $username,
        'X-Cross-Expires: ' . $expires,
        'X-Cross-Signature: ' . cross_panel_signature('sync-admin', $username, $expires, $body),
    ];
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'timeout' => 8,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents(cross_panel_oes_url() . '/cross-panel-sync-admin.php', false, $context);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    return is_array($decoded) ? $decoded : ['ok' => false, 'message' => 'ارتباط با پنل آزمون برقرار نشد'];
}
