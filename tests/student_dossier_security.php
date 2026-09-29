<?php
// Isolated endpoint tests; never connect to or modify the application database.
if ($argc === 1) {
    $expected = [
        'locked'=>[403,0], 'creator-lock'=>[403,0], 'race-lock'=>[403,0], 'csrf'=>[403,0],
        'school-bank'=>[400,0], 'forged-owner'=>[302,1], 'admin-denied'=>[403,0],
        'history-own'=>[200,0], 'history-post'=>[403,0], 'history-delete'=>[403,0],
    ];
    foreach ($expected as $scenario => [$status,$writes]) {
        $proc = proc_open([PHP_BINARY, __FILE__, $scenario], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($proc);
        $result = json_decode(substr($out, strrpos($out, "\nRESULT:") + 8), true);
        if ($exit || !$result || $result['status'] !== $status || $result['writes'] !== $writes || $result['owner'] !== '0012345678') {
            fwrite(STDERR, "FAIL $scenario: $out $err\n"); exit(1);
        }
        echo "PASS $scenario\n";
    }
    $proc = proc_open([PHP_BINARY, __FILE__, 'pdf'], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
    $pdf = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($proc);
    if ($exit || !str_starts_with($pdf, '%PDF-') || strlen($pdf) < 5000) { fwrite(STDERR, "FAIL PDF: $err\n"); exit(1); }
    echo 'PASS Persian PDF generation (' . strlen($pdf) . " bytes)\n";
    exit;
}
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/inc/jdf.php';
require BASE_PATH . '/inc/student_dossier.php';
$scenario = $argv[1];
function inst_type() { return 'school'; }
function url($path) { return 'http://localhost/lmsgitea/' . $path; }
function typeUser($code) { return 1; }
function checkTellWithoume($tel, $code) { return false; }
function retPerson($code) {
    global $scenario, $owner;
    $owner = $code;
    return (object)['codemeli'=>$code,'fname'=>'آزمایش','lname'=>'دانش‌آموز','tel1'=>'09123456789','birthdate'=>'2010-03-21','updator'=>$scenario === 'locked' ? 'admin' : null,'creator'=>$scenario === 'creator-lock' ? 'admin' : null];
}
function AdminRequire($permission) { http_response_code(403); exit('Forbidden'); }
function CheckBlacklist_ip($ip) { return false; }
function GetIp() { return '127.0.0.1'; }
function GetOs() { return 'test'; }
function sessionid($user) { return ['session_id'=>session_id()]; }
class DossierTestConnection {
    public $transaction = false; public $writes = [];
    function beginTransaction() { $this->transaction = true; }
    function inTransaction() { return $this->transaction; }
    function rollBack() { $this->transaction = false; }
    function commit() { $this->transaction = false; }
    function prepare($sql) { return new DossierTestStatement($this, $sql); }
}
class DossierTestStatement {
    function __construct(public $conn, public $sql) {}
    function execute($args) { if (str_starts_with($this->sql, 'UPDATE')) $this->conn->writes[] = $args; }
    function fetchColumn() { return true; }
    function fetch($mode) {
        global $scenario;
        $person = retPerson('0012345678');
        if ($scenario === 'race-lock') $person->updator = 'admin';
        return $person;
    }
}
$conn = new DossierTestConnection();
$_SESSION = ['auth'=>['user'=>'0012345678'], 'dossier_token'=>'test-token'];
$_SESSION['auth'] += ['role'=>1,'ip'=>'127.0.0.1','os'=>'test'];
$owner = '0012345678';
$_POST = ['section'=>'account','token'=>'test-token','fname'=>'نام','lname'=>'خانوادگی','tel1'=>'09123456789','Id'=>'other-student','codemeli'=>'other-student','updator'=>'admin'];
$_GET = ['Id'=>'other-student'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$dossierAdmin = false;
if ($scenario === 'csrf') $_POST['token'] = 'forged';
if ($scenario === 'school-bank') $_POST['section'] = 'bank';
if ($scenario === 'admin-denied') { $dossierAdmin = true; $_GET['Id'] = '0012345678'; $_SESSION['admin'] = ['user'=>'admin']; }
if ($scenario === 'pdf') { $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['pdf'=>1]; }
else register_shutdown_function(function () use ($conn) {
    global $owner;
    $status = http_response_code();
    if ($conn->writes && end($conn->writes[0]) !== '0012345678') $owner = 'WRONG UPDATE TARGET';
    echo "\nRESULT:" . json_encode(['status'=>$status ?: 200,'writes'=>count($conn->writes),'owner'=>$owner]);
});
if (isset(StudentDossierSections()['bank'])) throw new RuntimeException('School bank section leaked');
if (str_starts_with($scenario, 'history-')) {
    define('STUDENT_HISTORY_SELF', true);
    $_SERVER['REQUEST_METHOD'] = $scenario === 'history-post' ? 'POST' : 'GET';
    if ($scenario === 'history-delete') $_GET['delm'] = '1';
    $source = file_get_contents(BASE_PATH . '/admin/Student/student-history.php');
    $source = substr($source, 0, strpos($source, '$apiUrl ='));
    $source = preg_replace('/^include_once .*;\r?\n/m', '', $source);
    eval(substr($source, 5));
    $owner = $_GET['Id'];
    exit;
}
require BASE_PATH . '/inc/student_dossier_page.php';
