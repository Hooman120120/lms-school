        <?php
        require_once __DIR__ . '/dbconfig.php';
        require_once __DIR__ . '/inc/function_login.php';

        header('Referrer-Policy: no-referrer');

        $config = hf_control_config();
        $installationId = trim((string) ($_GET['installation_id'] ?? ''));
        $username = trim((string) ($_GET['username'] ?? ''));
        $expires = (int) ($_GET['expires'] ?? 0);
        $signature = (string) ($_GET['signature'] ?? '');
        $expected = $config ? hash_hmac('sha256', implode("\n", [$installationId, $username, (string) $expires]), $config['panel_key']) : '';

        if (!$config || $installationId !== $config['panel_id'] || $username === '' || $expires < time() || $expires > time() + 90 || !hash_equals($expected, $signature)) {
            http_response_code(403);
            exit('دسترسی پشتیبانی معتبر نیست');
        }

        try {
            hf_control_require_panel_access($conn, true);
            $stmt = $conn->prepare('SELECT * FROM dbadmin WHERE username = ? AND adminactive = 1 LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$admin) {
                throw new RuntimeException('حساب مدیر این پنل هنوز ایجاد نشده است');
            }
            session_regenerate_id(true);
            $sessionId = session_id();
            $ip = GetIp();
            $os = GetOs();
            $_SESSION['admin'] = [
                'idadmin' => $admin['idadmin'],
                'name' => $admin['fname'],
                'famili' => $admin['lname'],
                'tel' => $admin['mobile'],
                'dakheli' => $admin['dakheli'],
                'user' => $admin['username'],
                'adminrole' => $admin['adminrole'],
                'can_impersonate' => (int) ($admin['can_impersonate'] ?? 0),
                'ip' => $ip,
                'os' => $os,
                'hf_support' => true,
            ];
            date_default_timezone_set('Iran');
            $login = $conn->prepare('INSERT INTO dbh_admin(username,datelogin,timelogin,session_id,ip,os) VALUES (?,?,?,?,?,?)');
            $login->execute([$admin['username'], date('Y-m-d'), date('H:i:s'), $sessionId, $ip, $os]);
            header('Location: admin/index.php');
            exit;
        } catch (Throwable $e) {
            http_response_code(403);
            exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
