<?php
require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/inc/function_login.php';

header('Referrer-Policy: no-referrer');
$username = trim((string) ($_GET['username'] ?? ''));
$expires = (int) ($_GET['expires'] ?? 0);
$signature = (string) ($_GET['signature'] ?? '');
$expected = cross_panel_signature('login', $username, $expires);

if (cross_panel_secret() === '' || $username === '' || $expires < time() || $expires > time() + 90 || !hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('درخواست ورود بین پنل‌ها معتبر نیست');
}

$stmt = $conn->prepare('SELECT * FROM dbadmin WHERE username = ? AND adminactive = 1 LIMIT 1');
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    http_response_code(403);
    exit('برای این نام کاربری حساب فعالی در پنل آموزش وجود ندارد');
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
    'ip' => $ip,
    'os' => $os,
    'cross_panel' => true,
];
$login = $conn->prepare('INSERT INTO dbh_admin(username,datelogin,timelogin,session_id,ip,os) VALUES (?,?,?,?,?,?)');
$login->execute([$admin['username'], date('Y-m-d'), date('H:i:s'), $sessionId, $ip, $os]);
header('Location: admin/index.php');
exit;
