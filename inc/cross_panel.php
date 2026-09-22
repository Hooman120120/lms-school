<?php
/**
 * Cross-panel single sign-on between the three HF panels (lms, oes, chahard).
 *
 * Every panel shares one CROSS_PANEL_SECRET and exposes cross-panel-login.php.
 * A panel links to the *other* two; the target resolves the username against
 * its own admin table, so an admin only lands where it already has an account.
 */

const CROSS_PANEL_SELF = 'lms';

const CROSS_PANEL_LABELS = [
    'lms' => 'پنل آموزش',
    'oes' => 'پنل آزمون',
    'chahard' => 'پنل کلاس آنلاین',
];

function cross_panel_secret(): string
{
    return trim((string) getenv('CROSS_PANEL_SECRET'));
}

function cross_panel_url(string $type): string
{
    if (!isset(CROSS_PANEL_LABELS[$type])) {
        throw new InvalidArgumentException('نوع پنل معتبر نیست');
    }
    return rtrim(trim((string) getenv('CROSS_PANEL_' . strtoupper($type) . '_URL')), '/');
}

/** The other two panels, in menu order, that are actually configured. */
function cross_panel_targets(): array
{
    if (cross_panel_secret() === '') {
        return [];
    }
    $targets = [];
    foreach (CROSS_PANEL_LABELS as $type => $label) {
        if ($type === CROSS_PANEL_SELF) {
            continue;
        }
        if (cross_panel_url($type) !== '') {
            $targets[$type] = $label;
        }
    }
    return $targets;
}

function cross_panel_signature(string $action, string $username, int $expires, string $body = ''): string
{
    return hash_hmac('sha256', implode("\n", [$action, $username, (string) $expires, hash('sha256', $body)]), cross_panel_secret());
}

function cross_panel_login_url(string $type, string $username): string
{
    $expires = time() + 60;
    return cross_panel_url($type) . '/cross-panel-login.php?' . http_build_query([
        'username' => $username,
        'expires' => $expires,
        'signature' => cross_panel_signature('login', $username, $expires),
    ]);
}
function cross_panel_oes_url(): string
{
    return cross_panel_url('oes');
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

/** Send a short-lived, signed data synchronisation request to OES. */
function cross_panel_sync_oes(string $action, array $data): array
{
    $secret = cross_panel_secret();
    if ($secret === '') {
        return ['ok' => false, 'message' => 'کلید ارتباط بین پنل‌ها تنظیم نشده است'];
    }
    $username = (string) ($_SESSION['admin']['user'] ?? 'lms-system');
    $body = json_encode(['action' => $action, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return ['ok' => false, 'message' => 'دادهٔ همگام‌سازی معتبر نیست'];
    }
    $expires = time() + 60;
    $headers = [
        'Content-Type: application/json',
        'X-Cross-Username: ' . $username,
        'X-Cross-Expires: ' . $expires,
        'X-Cross-Signature: ' . cross_panel_signature('sync-data', $username, $expires, $body),
    ];
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents(cross_panel_oes_url() . '/cross-panel-sync.php', false, $context);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    return is_array($decoded) ? $decoded : ['ok' => false, 'message' => 'ارتباط با پنل آزمون برقرار نشد'];
}

/** Create or update the OES account that corresponds to an LMS student. */
function cross_panel_sync_oes_student(string $username, string $firstName, string $lastName, string $plainPassword, bool $active = true): array
{
    return cross_panel_sync_oes('student.upsert', [
        'username' => $username,
        // OES currently stores its login verifier as MD5; no raw password is sent.
        'password_hash' => md5($plainPassword),
        'active' => $active ? 1 : 0,
        'expiration_date' => '3021-03-21',
        'first_name' => $firstName,
        'last_name' => $lastName,
    ]);
}
