<?php
// CLI-only authenticated GET smoke test against the disposable local database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$allowed = ['admin/Doc/announcements.php', 'admin/Doc/announcement-create.php',
    'admin/Doc/employee-documents.php', 'admin/Doc/special-letters.php',
    'admin/Doc/searchdoc.php', 'admin/notifications/school_notifications.php'];
$page = $argv[1] ?? '';
if (!in_array($page, $allowed, true)) { fwrite(STDERR, "Unsupported test page\n"); exit(2); }
$root = dirname(__DIR__);
ini_set('session.save_path', $root . '/tmp');
putenv('LMS_DB_HOST=127.0.0.1');
putenv('LMS_DB_USER=root');
putenv('LMS_DB_PASS=');
putenv('LMS_DB_NAME=lmsgitea_merge_test');
putenv('APP_BASE_URL=http://localhost/lmsgitea');
foreach (['HF_CONTROL_URL','HF_PANEL_ID','HF_PANEL_KEY'] as $key) {
    putenv($key); unset($_SERVER[$key], $_ENV[$key]);
}
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Local merge regression';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/lmsgitea/' . $page;
$_SERVER['SCRIPT_FILENAME'] = $root . '/' . $page;
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['DOCUMENT_ROOT'] = dirname($root);
require_once $root . '/dbconfig.php';
require_once $root . '/inc/function_login.php';
$conn->beginTransaction();
$admin = $conn->query('SELECT * FROM dbadmin WHERE adminrole=1 ORDER BY idadmin LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$admin) { throw new RuntimeException('Missing local manager fixture'); }
$_SESSION['admin'] = ['idadmin'=>(int)$admin['idadmin'], 'user'=>$admin['username'],
    'name'=>$admin['fname'], 'famili'=>$admin['lname'], 'tel'=>$admin['mobile'],
    'dakheli'=>$admin['dakheli'], 'adminrole'=>$admin['adminrole'], 'can_impersonate'=>0,
    'ip'=>GetIp(), 'os'=>GetOs()];
$conn->prepare('INSERT INTO dbh_admin(username,datelogin,timelogin,session_id,ip,os) VALUES (?,?,?,?,?,?)')
    ->execute([$admin['username'],date('Y-m-d'),date('H:i:s'),session_id(),GetIp(),GetOs()]);
ob_start();
register_shutdown_function(static function () use ($conn, $page) {
    $html = ob_get_clean();
    if ($conn->inTransaction()) { $conn->rollBack(); }
    $_SESSION = [];
    session_destroy();
    $error = error_get_last();
    $failed = ($error && in_array($error['type'], [E_ERROR,E_PARSE,E_COMPILE_ERROR,E_USER_ERROR], true))
        || preg_match('/Fatal error|SQLSTATE|Warning:|Parse error/', $html)
        || strlen($html) < 1000;
    echo ($failed ? 'FAIL ' : 'PASS ') . $page . ' bytes=' . strlen($html) . "\n";
    if ($failed) {
        if ($error) { echo $error['message'] . "\n"; }
        preg_match_all('/(?:Warning:|Fatal error:|SQLSTATE)[^<\r\n]*/', strip_tags($html), $matches);
        echo implode("\n", array_unique($matches[0])) . "\n";
        exit(1);
    }
});
chdir(dirname($root . '/' . $page));
require $root . '/' . $page;
