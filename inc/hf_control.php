<?php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(404);
    exit;
}

function hf_control_env(string $name): string
{
    foreach ([$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

function hf_control_config(): ?array
{
    $url = rtrim(hf_control_env('HF_CONTROL_URL'), '/');
    $panelId = hf_control_env('HF_PANEL_ID');
    $panelKey = hf_control_env('HF_PANEL_KEY');
    $verifyTls = filter_var(hf_control_env('HF_CONTROL_VERIFY_TLS'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($url === '' || $panelId === '' || $panelKey === '') {
        return null;
    }
    return ['url' => $url, 'panel_id' => $panelId, 'panel_key' => $panelKey, 'verify_tls' => $verifyTls !== false];
}

function hf_control_configured(): bool
{
    return hf_control_config() !== null;
}

function hf_control_request(string $endpoint, string $method = 'GET', ?array $payload = null): array
{
    $config = hf_control_config();
    if (!$config) {
        throw new RuntimeException('اتصال این پنل به هنرفناوری تنظیم نشده است');
    }

    $handle = curl_init($config['url'] . '/' . ltrim($endpoint, '/'));
    $headers = [
        'Accept: application/json',
        'X-Panel-Id: ' . $config['panel_id'],
        'X-Panel-Key: ' . $config['panel_key'],
    ];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => $config['verify_tls'],
        CURLOPT_SSL_VERIFYHOST => $config['verify_tls'] ? 2 : 0,
    ];
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $options[CURLOPT_POSTFIELDS] = $body;
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($handle, $options);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($body === false || $error !== '') {
        throw new RuntimeException('ارتباط با هنرفناوری برقرار نشد');
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('پاسخ هنرفناوری معتبر نیست');
    }
    if ($status < 200 || $status >= 300 || ($decoded['ok'] ?? false) !== true) {
        throw new RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'درخواست هنرفناوری ناموفق بود'));
    }
    return $decoded;
}

function hf_control_store_error(PDO $conn, string $message): void
{
    $stmt = $conn->prepare('UPDATE hf_control_state SET last_error = ? WHERE id = 1');
    $stmt->execute([mb_substr($message, 0, 500)]);
}

function hf_control_store_license(PDO $conn, array $response): array
{
    $config = hf_control_config();
    $payload = [
        'generated_at' => $response['generated_at'] ?? null,
        'license' => $response['license'] ?? null,
    ];
    $expected = hash_hmac(
        'sha256',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $config['panel_key']
    );
    $signature = (string) ($response['signature'] ?? '');
    if ($signature === '' || !hash_equals($expected, $signature) || !is_array($payload['license'])) {
        throw new RuntimeException('امضای پاسخ هنرفناوری معتبر نیست');
    }

    $license = $payload['license'];
    $service = $license['service'] ?? [];
    $stmt = $conn->prepare(
        'UPDATE hf_control_state
            SET license_json = ?, license_signature = ?, allowed = ?, reason = ?, expires_at = ?,
                capacity_total = ?, capacity_used = ?, fetched_at = NOW(), last_error = NULL
          WHERE id = 1'
    );
    $stmt->execute([
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $signature,
        empty($license['allowed']) ? 0 : 1,
        (string) ($license['reason'] ?? ''),
        $service['expires_at'] ?? null,
        $service['capacity_total'] ?? null,
        (int) ($service['capacity_used'] ?? 0),
    ]);
    return $license;
}

function hf_control_fetch_license(PDO $conn): array
{
    try {
        return hf_control_store_license($conn, hf_control_request('panel_license.php'));
    } catch (Throwable $e) {
        hf_control_store_error($conn, $e->getMessage());
        throw $e;
    }
}

function hf_control_access_state(PDO $conn, bool $forceRefresh = false): array
{
    if (!hf_control_configured()) {
        return ['allowed' => true, 'reason' => 'not_configured'];
    }

    $stmt = $conn->query(
        'SELECT allowed, reason, expires_at, fetched_at
           FROM hf_control_state
          WHERE id = 1'
    );
    $state = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $fetchedAt = isset($state['fetched_at']) ? strtotime((string) $state['fetched_at']) : false;
    $needsRefresh = $forceRefresh || $fetchedAt === false || $fetchedAt < time() - 60;

    if ($needsRefresh) {
        try {
            hf_control_fetch_license($conn);
            $stmt = $conn->query(
                'SELECT allowed, reason, expires_at, fetched_at
                   FROM hf_control_state
                  WHERE id = 1'
            );
            $state = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (!$state || $fetchedAt === false) {
                return ['allowed' => false, 'reason' => 'hf_unavailable'];
            }
        }
    }

    $expiresAt = isset($state['expires_at']) ? strtotime((string) $state['expires_at']) : false;
    if ($expiresAt !== false && $expiresAt <= time()) {
        return ['allowed' => false, 'reason' => 'expired', 'expires_at' => $state['expires_at']];
    }
    if (!isset($state['allowed']) || (int) $state['allowed'] !== 1) {
        return ['allowed' => false, 'reason' => (string) ($state['reason'] ?? 'service_inactive')];
    }
    return ['allowed' => true, 'reason' => (string) ($state['reason'] ?? ''), 'expires_at' => $state['expires_at'] ?? null];
}

function hf_control_access_message(string $reason): string
{
    return match ($reason) {
        'expired', 'service_expired' => 'اشتراک این سامانه به پایان رسیده است. لطفاً از پنل هنرفناوری تمدید کنید.',
        'panel_suspended', 'service_inactive', 'customer_inactive' => 'دسترسی این سامانه از پنل هنرفناوری غیرفعال شده است.',
        'hf_unavailable' => 'در حال حاضر امکان بررسی وضعیت اشتراک از هنرفناوری وجود ندارد.',
        default => 'دسترسی به این سامانه توسط هنرفناوری متوقف شده است.',
    };
}

function hf_control_require_panel_access(PDO $conn, bool $forceRefresh = false): void
{
    $state = hf_control_access_state($conn, $forceRefresh);
    if (!empty($state['allowed'])) {
        return;
    }
    throw new RuntimeException(hf_control_access_message((string) ($state['reason'] ?? '')));
}

function hf_control_enforce_authenticated_request(PDO $conn): void
{
    if (!isset($_SESSION['auth']) && !isset($_SESSION['admin'])) {
        return;
    }
    try {
        hf_control_require_panel_access($conn);
    } catch (Throwable $e) {
        unset($_SESSION['auth'], $_SESSION['admin']);
        http_response_code(403);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if (str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'hf_access_denied', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>دسترسی متوقف شده</title><style>body{margin:0;background:#f4f6fb;font-family:Tahoma,Arial,sans-serif;color:#263238;display:grid;place-items:center;min-height:100vh}.box{max-width:560px;margin:24px;background:#fff;border-radius:14px;padding:36px;box-shadow:0 10px 35px rgba(31,45,61,.12);text-align:center}h1{font-size:24px;margin:0 0 18px;color:#b42318}p{line-height:2;margin:0}</style></head><body><main class="box"><h1>دسترسی به سامانه متوقف شده است</h1><p>' . $message . '</p></main></body></html>';
        exit;
    }
}

function hf_control_claim(PDO $conn, string $externalId, string $userType): array
{   
    if (!hf_control_configured()) {
        return ['allowed' => true, 'reason' => 'not_configured'];
    }
    try {
        $response = hf_control_request('panel_seat_claim.php', 'POST', [
            'external_user_id' => $externalId,
            'user_type' => $userType,
        ]);
        if (isset($response['license']['service'])) {
            $service = $response['license']['service'];
            $stmt = $conn->prepare('UPDATE hf_control_state SET allowed = ?, reason = ?, expires_at = ?, capacity_total = ?, capacity_used = ?, fetched_at = NOW(), last_error = NULL WHERE id = 1');
            $stmt->execute([
                empty($response['license']['allowed']) ? 0 : 1,
                (string) ($response['license']['reason'] ?? ''),
                $service['expires_at'] ?? null,
                $service['capacity_total'] ?? null,
                (int) ($service['capacity_used'] ?? 0),
            ]);
        }
        return $response;
    } catch (Throwable $e) {
        hf_control_store_error($conn, $e->getMessage());
        throw $e;
    }
}

function hf_control_release(PDO $conn, string $externalId, bool $suspended = false): array
{
    if (!hf_control_configured()) {
        return ['released' => false, 'reason' => 'not_configured'];
    }
    try {
        $response = hf_control_request('panel_seat_release.php', 'POST', [
            'external_user_id' => $externalId,
            'reason' => $suspended ? 'suspended' : 'released',
        ]);
        if (isset($response['license']['service'])) {
            $service = $response['license']['service'];
            $stmt = $conn->prepare('UPDATE hf_control_state SET capacity_total = ?, capacity_used = ?, fetched_at = NOW(), last_error = NULL WHERE id = 1');
            $stmt->execute([$service['capacity_total'] ?? null, (int) ($service['capacity_used'] ?? 0)]);
        }
        return $response;
    } catch (Throwable $e) {
        hf_control_store_error($conn, $e->getMessage());
        throw $e;
    }
}

function hf_control_login_id(string $username): string
{
    return 'login:' . $username;
}

function hf_control_admin_id(string $username): string
{
    return 'admin:' . $username;
}

function hf_control_login_type(int $role): string
{
    if ($role === 1) {
        return 'student';
    }
    if ($role === 2) {
        return 'colleague';
    }
    return 'user';
}

function hf_control_admin_type(?int $adminRole, ?string $schoolRole): string
{
    if ($adminRole === 3 || $schoolRole === 'teacher') {
        return 'teacher';
    }
    if ($adminRole === 0) {
        return 'admin';
    }
    return 'staff';
}

function hf_control_claim_login(PDO $conn, string $username): array
{
    $stmt = $conn->prepare(
        'SELECT l.role, l.activity, COALESCE(p.suspension_mode, 0) AS suspension_mode
           FROM dblogin l LEFT JOIN dbpersonal p ON p.codemeli = l.username
          WHERE l.username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('کاربر LMS یافت نشد');
    }
    if ((int) $user['role'] === 1 && (int) $user['suspension_mode']) {
        return hf_control_release($conn, hf_control_login_id($username), (bool) $user['suspension_mode']);
    }
    return hf_control_claim($conn, hf_control_login_id($username), hf_control_login_type((int) $user['role']));
}

function hf_control_claim_admin(PDO $conn, string $username): array
{
    $stmt = $conn->prepare(
        'SELECT a.adminactive, a.adminrole, r.role_key
           FROM dbadmin a LEFT JOIN school_admin_roles r ON r.id = a.school_role_id
          WHERE a.username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('کاربر مدیریتی LMS یافت نشد');
    }
    return hf_control_claim($conn, hf_control_admin_id($username), hf_control_admin_type(
        isset($user['adminrole']) ? (int) $user['adminrole'] : null,
        $user['role_key'] ?? null
    ));
}

function hf_control_register_login(PDO $conn, string $username): array
{
    return hf_control_claim_login($conn, $username);
}

function hf_control_register_admin(PDO $conn, string $username): array
{
    return hf_control_claim_admin($conn, $username);
}

function hf_control_denial_message(array $response): string
{
    return match ((string) ($response['reason'] ?? '')) {
        'capacity_full', 'capacity_exceeded' => 'ظرفیت کاربران این پنل تکمیل شده است',
        'expired', 'service_expired' => 'اشتراک این پنل به پایان رسیده است',
        'panel_suspended', 'service_inactive', 'customer_inactive' => 'این پنل در هنرفناوری غیرفعال است',
        default => (string) ($response['message'] ?? 'ایجاد کاربر توسط هنرفناوری تأیید نشد'),
    };
}

function hf_control_require_seat(PDO $conn, string $externalId, string $userType): void
{  
    try {
        $response = hf_control_claim($conn, $externalId, $userType);
    } catch (Throwable $e) { 
        http_response_code(503);
        exit('ارتباط با هنرفناوری برقرار نشد و کاربر ایجاد نشد');
    }
    if (isset($response['allowed']) && !$response['allowed']) {
        http_response_code(409);
        exit(hf_control_denial_message($response));
    }
}

function hf_control_activate_login(PDO $conn, string $username): array
{
    $stmt = $conn->prepare(
        'SELECT l.role, COALESCE(p.suspension_mode, 0) AS suspension_mode
           FROM dblogin l LEFT JOIN dbpersonal p ON p.codemeli = l.username
          WHERE l.username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('کاربر LMS یافت نشد');
    }
    if ((int) $user['suspension_mode']) {
        return ['allowed' => false, 'reason' => 'student_suspended'];
    }
    $response = hf_control_claim($conn, hf_control_login_id($username), hf_control_login_type((int) $user['role']));
    if (!isset($response['allowed']) || $response['allowed']) {
        $stmt = $conn->prepare('UPDATE dblogin SET activity = 1 WHERE username = ?');
        $stmt->execute([$username]);
    }
    return $response;
}

function hf_control_deactivate_login(PDO $conn, string $username): void
{
    $stmt = $conn->prepare('UPDATE dblogin SET activity = 0 WHERE username = ?');
    $stmt->execute([$username]);
}

function hf_control_activate_admin(PDO $conn, string $username): array
{
    $stmt = $conn->prepare(
        'SELECT a.adminrole, r.role_key
           FROM dbadmin a LEFT JOIN school_admin_roles r ON r.id = a.school_role_id
          WHERE a.username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('کاربر مدیریتی LMS یافت نشد');
    }
    $response = hf_control_claim($conn, hf_control_admin_id($username), hf_control_admin_type(
        isset($user['adminrole']) ? (int) $user['adminrole'] : null,
        $user['role_key'] ?? null
    ));
    if (!isset($response['allowed']) || $response['allowed']) {
        $stmt = $conn->prepare('UPDATE dbadmin SET adminactive = 1 WHERE username = ?');
        $stmt->execute([$username]);
    }
    return $response;
}

function hf_control_deactivate_admin(PDO $conn, string $username): void
{
    $stmt = $conn->prepare('UPDATE dbadmin SET adminactive = 0 WHERE username = ?');
    $stmt->execute([$username]);
}

function hf_control_suspend_student(PDO $conn, string $username): void
{
    $stmt = $conn->prepare('UPDATE dbpersonal SET suspension_mode = 1 WHERE codemeli = ?');
    $stmt->execute([$username]);
    try {
        hf_control_release($conn, hf_control_login_id($username), true);
    } catch (Throwable $e) {
    }
}

function hf_control_unsuspend_student(PDO $conn, string $username): array
{
    $stmt = $conn->prepare('SELECT role FROM dblogin WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('دانش‌آموز یافت نشد');
    }
    $response = hf_control_claim($conn, hf_control_login_id($username), hf_control_login_type((int) $user['role']));
    if (isset($response['allowed']) && !$response['allowed']) {
        return $response;
    }
    $stmt = $conn->prepare('UPDATE dbpersonal SET suspension_mode = 0, suspension_reason = NULL WHERE codemeli = ?');
    $stmt->execute([$username]);
    return $response;
}

function hf_control_collect_users(PDO $conn): array
{
    $users = [];
    $stmt = $conn->query(
        'SELECT l.username, l.role, l.activity, COALESCE(p.suspension_mode, 0) AS suspension_mode
           FROM dblogin l LEFT JOIN dbpersonal p ON p.codemeli = l.username'
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
        $suspended = (int) $user['role'] === 1 && (int) $user['suspension_mode'] === 1;
        $users[] = [
            'external_user_id' => hf_control_login_id($user['username']),
            'user_type' => hf_control_login_type((int) $user['role']),
            'status' => $suspended ? 'suspended' : 'active',
        ];
    }

    $stmt = $conn->query(
        'SELECT a.username, a.adminrole, a.adminactive, r.role_key
           FROM dbadmin a LEFT JOIN school_admin_roles r ON r.id = a.school_role_id'
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
        $users[] = [
            'external_user_id' => hf_control_admin_id($user['username']),
            'user_type' => hf_control_admin_type(
                isset($user['adminrole']) ? (int) $user['adminrole'] : null,
                $user['role_key'] ?? null
            ),
            'status' => 'active',
        ];
    }
    return $users;
}

function hf_control_sync(PDO $conn): array
{
    if (!hf_control_configured()) {
        return ['accepted' => false, 'reason' => 'not_configured'];
    }
    try {
        $response = hf_control_request('panel_seat_sync.php', 'POST', ['users' => hf_control_collect_users($conn)]);
        $license = $response['license'] ?? [];
        $service = $license['service'] ?? [];
        $stmt = $conn->prepare(
            'UPDATE hf_control_state
                SET allowed = ?, reason = ?, expires_at = ?, capacity_total = ?, capacity_used = ?,
                    fetched_at = NOW(), last_sync_at = NOW(), last_error = ?
              WHERE id = 1'
        );
        $accepted = !isset($response['accepted']) || $response['accepted'];
        $stmt->execute([
            empty($license['allowed']) ? 0 : 1,
            (string) ($license['reason'] ?? ''),
            $service['expires_at'] ?? null,
            $service['capacity_total'] ?? null,
            (int) ($accepted ? ($service['capacity_used'] ?? 0) : ($response['active_users'] ?? 0)),
            $accepted ? null : (string) ($response['reason'] ?? 'sync_rejected'),
        ]);
        return $response;
    } catch (Throwable $e) {
        hf_control_store_error($conn, $e->getMessage());
        throw $e;
    }
}
