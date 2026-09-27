<?php
// No live database or messages: php tests/temporary_student_registration_test.php school|institute
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message) { throw new RuntimeException($message); });
define('INST_TYPE_OVERRIDE', $argv[1] ?? 'school');
require __DIR__ . '/../config/institution.php';
require __DIR__ . '/../config/institution_display.php';
require __DIR__ . '/../inc/temporary_student_registration.php';
$conn = new PDO('sqlite::memory:');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec('CREATE TABLE state (id INTEGER, state_id INTEGER, name TEXT)');
$conn->exec('CREATE TABLE dbpersonal (codemeli TEXT PRIMARY KEY, fname TEXT, lname TEXT, gender TEXT, tel1 TEXT, paye TEXT, reshtestudent TEXT, datecreated TEXT, timecreated TEXT, ipcreated TEXT, oscreated TEXT, creator TEXT)');
$conn->exec('CREATE TABLE dblogin (username TEXT PRIMARY KEY, password TEXT, role INTEGER)');
$registrationAccess = true;
$externalCalls = [];
function Permision($code): bool { global $registrationAccess; return $registrationAccess; }
function AdminCanUsePermissionScope($code, $legacy): bool { global $registrationAccess; return $registrationAccess; }
function GetIp(): string { return '127.0.0.1'; }
function GetOs(): string { return 'test'; }
function url($path): string { return '/' . $path; }
function checkTell2($phone): bool {
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) FROM dbpersonal WHERE codemeli = ?');
    $stmt->execute([$phone]);
    return (int)$stmt->fetchColumn() > 0;
}
function hf_control_login_id($username): string { return 'lms_' . $username; }
function hf_control_require_seat($conn, $id, $role): void { global $externalCalls; $externalCalls[] = ['seat', $id, $role]; }
function cross_panel_sync_oes_student(...$args): array { global $externalCalls; $externalCalls[] = ['oes', $args]; return ['ok' => true]; }
function SendSms(...$args): void { global $externalCalls; $externalCalls[] = ['sms', $args]; }
$assert = static function ($condition, $message): void { if (!$condition) throw new RuntimeException($message); };
$render = static function ($state): string {
    ob_start(); RenderTemporaryStudentRegistration($state); return inst_display_html(ob_get_clean());
};
$embedded = inst_is('school');
$_SESSION = ['admin' => ['user' => 'test-admin'], 'token' => 'existing-enrolment-token'];
$_GET = $_POST = [];
$state = HandleTemporaryStudentRegistration($embedded);
$sessionKey = $state['temporarySessionKey'];
$tokenKey = $state['temporaryTokenKey'];
$html = $render($state);
$assert(str_contains($html, $embedded ? 'ثبت‌نام موقت دانش‌آموز برای فوق برنامه' : 'افزودن فراگیر موقت'), 'Mode-specific registration title');
$assert(str_contains($html, 'name="temporary_registration"') === $embedded, 'Only school embeds the registration marker');
$assert($_SESSION['token'] === 'existing-enrolment-token', 'Rendering registration must not replace the enrolment token');
$assert(str_contains($html, 'name="search-tel"'), 'Initial phone form');
$marker = $embedded ? ['temporary_registration' => 'school-extracurricular'] : [];

$registrationAccess = false;
$_POST = $marker + ['search-tel' => '', 'tel' => '09123456789', 'token' => $_SESSION[$tokenKey]];
$denied = HandleTemporaryStudentRegistration($embedded);
$assert(!empty($denied['forbidden']) && !isset($_SESSION[$sessionKey]), 'Missing permission blocks the backend');
$assert($render($denied) === '', 'Missing permission hides the form');
$registrationAccess = true;
$_POST['token'] = 'invalid';
$invalid = HandleTemporaryStudentRegistration($embedded);
$assert(($invalid['erortoken'] ?? 0) === 1 && !isset($_SESSION[$sessionKey]), 'Invalid token cannot start registration');
$_POST['token'] = $_SESSION[$tokenKey];
$state = HandleTemporaryStudentRegistration($embedded);
$assert(($_SESSION[$sessionKey]['tel'] ?? '') === '09123456789', 'Phone step saves temporary state');
$assert((int)$conn->query('SELECT COUNT(*) FROM dbpersonal')->fetchColumn() === 0, 'Phone step must not create an incomplete account');
$html = $render($state);
$assert(str_contains($html, 'name="fname-student"') && str_contains($html, 'name="submit-student"'), 'Details step is rendered');
$assert(!str_contains($html, 'name="search-tel"'), 'Phone form is replaced by details form');
$assert(str_contains($html, 'پایه تحصیلی'), 'Academic grade remains a grade');
$assert($_SESSION['token'] === 'existing-enrolment-token', 'Details token is separate from enrolment');
$_POST = $marker + [
    'submit-student' => '', 'token' => $_SESSION[$tokenKey],
    'fname-student' => 'نمونه', 'lname-student' => 'آزمایشی',
    'paye-student' => $embedded ? '1' : '6', 'reshte-student' => '0', 'gender-student' => 'آقا',
];
$state = HandleTemporaryStudentRegistration($embedded);
$assert(($state['verify'] ?? 0) === 100, 'Temporary registration completes');
$person = $conn->query('SELECT * FROM dbpersonal')->fetch(PDO::FETCH_ASSOC);
$assert($person['codemeli'] === '09123456789' && $person['tel1'] === '09123456789', 'Existing phone-based identity is preserved');
$assert($person['paye'] === ($embedded ? '1' : '6') && $person['reshtestudent'] === 'ندارد', 'Grade and field are stored');
$login = $conn->query('SELECT * FROM dblogin')->fetch(PDO::FETCH_ASSOC);
$assert($login['username'] === $person['codemeli'] && (int)$login['role'] === 1, 'Student login is created');
$assert(!isset($_SESSION[$sessionKey]), 'Completed registration state is cleared');
$assert((isset($_SESSION['add-course2']) && $_SESSION['add-course2']['tel'] === $person['codemeli']) === $embedded, 'School account is ready for extra-curricular enrolment; institute keeps its own workflow');
$assert(count($externalCalls) === 3, 'Seat, exam account synchronization and SMS hooks remain in the workflow');
$assert($externalCalls[2][1][0] === '09123456789', 'Account information is sent to the registered phone');
$assert($_SESSION['token'] === 'existing-enrolment-token', 'Completed registration does not replace enrolment token');

// Duplicate phone requires the same explicit checkbox as the established form.
$_POST = [];
$render(HandleTemporaryStudentRegistration($embedded));
$_POST = $marker + ['search-tel' => '', 'token' => $_SESSION[$tokenKey], 'tel' => '09123456789'];
$state = HandleTemporaryStudentRegistration($embedded);
$assert(($state['havetel'] ?? 0) === 1 && !isset($_SESSION[$sessionKey]), 'Duplicate phone rejected without opt-in');
$_POST['tel-trust'] = 'on';
$state = HandleTemporaryStudentRegistration($embedded);
$assert(isset($_SESSION[$sessionKey]['tel-trust']), 'Duplicate opt-in is preserved');
$render($state);
$_POST = $marker + ['submit-student' => '', 'token' => $_SESSION[$tokenKey], 'fname-student' => 'دوم', 'lname-student' => 'آزمایشی', 'paye-student' => '10', 'reshte-student' => '1', 'gender-student' => 'خانم'];
$state = HandleTemporaryStudentRegistration($embedded);
$assert(($state['verify'] ?? 0) === 100, 'Duplicate phone with opt-in creates a separate account');
$assert((int)$conn->query('SELECT COUNT(*) FROM dbpersonal')->fetchColumn() === 2, 'Existing account is not overwritten');
$assert((int)$conn->query("SELECT COUNT(*) FROM dbpersonal WHERE codemeli = '90123456789' AND reshtestudent = 'تجربی'")->fetchColumn() === 1, 'Established alternate identity and study field are preserved');
if ($embedded) {
    $selected = $_SESSION['add-course2'];
    $_POST = ['search-tel' => '', 'tel' => '09123456789', 'token' => $_SESSION['token']];
    HandleTemporaryStudentRegistration(true);
    $assert(!isset($_SESSION[$sessionKey]) && $_SESSION['add-course2'] === $selected, 'Searching existing students does not start a temporary registration');
    $_POST = [];
    $state = HandleTemporaryStudentRegistration(true);
    $render($state);
    $_POST = $marker + ['search-tel' => '', 'tel' => '09121111111', 'token' => $_SESSION[$tokenKey]];
    $state = HandleTemporaryStudentRegistration(true);
    $render($state);
    $_POST = $marker + ['submit-student' => '', 'token' => 'invalid', 'fname-student' => 'خطا'];
    $state = HandleTemporaryStudentRegistration(true);
    $assert(($state['erortoken'] ?? 0) === 1 && (int)$conn->query('SELECT COUNT(*) FROM dbpersonal')->fetchColumn() === 2, 'Invalid details token cannot create an account');
}
$_POST = [];
$temporaryRegistration = $embedded ? HandleTemporaryStudentRegistration(true) : null;
$page = file_get_contents(__DIR__ . '/../admin/democourse/addstudent.php');
$start = strpos($page, '<div class="inner-body">') + strlen('<div class="inner-body">');
$end = strpos($page, '<!-- Row -->', $start);
ob_start(); eval('?>' . substr($page, $start, $end - $start)); $prefix = inst_display_html(ob_get_clean());
$assert(str_contains($prefix, 'ثبت‌نام موقت دانش‌آموز برای فوق برنامه') === $embedded, 'School registration appears above the existing enrolment card only in school mode');
$assert(!$embedded || (str_contains($prefix, 'قبلاً') && str_contains($prefix, 'دانش‌آموز جدید')), 'The two registration choices are explained');
if ($embedded) {
    $conn->exec('CREATE TABLE dbcourse_demo (username TEXT, codecourse TEXT, useradder TEXT)');
    $stmt = $conn->prepare('INSERT INTO dbpersonal (codemeli, fname, lname, tel1) VALUES (?, ?, ?, ?)');
    $login = $conn->prepare('INSERT INTO dblogin (username, role) VALUES (?, ?)');
    foreach ([['0012345678', '09331111111', 1], ['0012345679', '09332222222', 1], ['0012345680', '09332222222', 1], ['0012345681', '09333333333', 3]] as [$id, $phone, $role]) {
        $stmt->execute([$id, 'قدیمی', 'آزمایشی', $phone]);
        $login->execute([$id, $role]);
    }
    $assert(ResolveExtraProgramStudent('0012345678')['username'] === '0012345678', 'Existing student can be found by national ID');
    $assert(ResolveExtraProgramStudent('09331111111')['username'] === '0012345678', 'Existing student can be found by phone and resolves to the original identity');
    $assert(ResolveExtraProgramStudent('09332222222')['status'] === 'ambiguous', 'Shared phone asks for a precise identity');
    $assert(ResolveExtraProgramStudent('0012345681')['status'] === 'not-student', 'Staff cannot be enrolled as a student');
    $assert(ResolveExtraProgramStudent('09333333333')['status'] === 'missing', 'Staff phone does not resolve to a student');
    $searchStart = strpos($page, "if (isset(\$_POST['search-tel'])");
    $salesStart = strpos($page, "if (isset(\$_POST['sales-course']))");
    $salesEnd = strpos($page, '$bulk_records', $salesStart);
    $_POST = ['search-tel' => '', 'tel' => '09331111111', 'token' => $_SESSION['token']];
    eval(substr($page, $searchStart, $salesStart - $searchStart));
    $assert($_SESSION['add-course2']['tel'] === '0012345678', 'School search selects the existing student without creating another account');
    $_POST = ['sales-course' => '', 'course' => ['extra-101', 'extra-102'], 'token' => $_SESSION['token']];
    eval(substr($page, $salesStart, $salesEnd - $salesStart));
    $enrolments = $conn->query('SELECT * FROM dbcourse_demo')->fetchAll(PDO::FETCH_ASSOC);
    $assert(count($enrolments) === 2 && $enrolments[0]['username'] === '0012345678' && $enrolments[1]['username'] === '0012345678', 'Existing students use the established extra-curricular enrolment mechanism');
}
echo inst_type() . ": temporary account creation, duplicate phone, permission guard, tokens and embedded form passed\n";
