<?php
//   FEwO}xS+Id*%
session_set_cookie_params([
    'lifetime' => 86400,        // عمر کوکی سشن به ثانیه (24 ساعت)
    'path' => '/',              // کوکی در کل دامنه قابل دسترس است
    'domain' => '',             // به صورت خودکار دامنه فعلی را تشخیص می‌دهد. اگر مشکل داشت، دامنه اصلی را اینجا قرار دهید.
    'secure' => true,           // **مهم:** چون سایت شما HTTPS است، این باید true باشد
    'httponly' => true,         // کوکی از طریق JavaScript قابل دسترسی نیست (امنیت بیشتر)
    'samesite' => 'None',       // **کلید حل مشکل درگاه:** برای درخواست‌های cross-site (مانند بازگشت از بانک) کوکی ارسال می‌شود
]);

session_start();
define('BASE_PATH', __DIR__);
define('CURRENT_DOMAIN', currentDomain() . '/');
define('DISPLAY_ERROR', true);
define('PERMISION_FILE', '0600'); // سطح دسترسی فایل های بارگزاری شده
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'myhona_myhonar');

//include_once 'language/fa_IR.php'; // فایل زبان انتخاب شود

try {
    // Added charset=utf8mb4 to the connection string
    $conn = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Changed UTF8 to utf8mb4
    $conn->query("SET NAMES utf8mb4");

} catch (PDOException $e) {
     echo $e->getMessage();
    echo 'خطای غیر منتظره رخ داده است';
}

// نوع مؤسسه (مدرسه / آموزشگاه) + واژه‌نامه و توابع t() و m()
// باید بعد از ساخته شدن $conn لود شود، چون نوع مؤسسه از setting_public خوانده می‌شود.
require_once BASE_PATH . '/config/institution.php';
function protocol()
{
    return 'http://';
}

function currentDomain()
{

    return protocol() . $_SERVER['HTTP_HOST'];
}

function assest($src)
{

    $domain = trim(CURRENT_DOMAIN, '/ ');
    $src = $domain . '/lms-school/' . trim($src, '/');
    return $src;
}

function url($url)
{

    $domain = trim(CURRENT_DOMAIN, '/ ');
    $url = $domain . '/lms-school/' . trim($url, '/');
    return $url;
}
function dd($value)
{
    echo '<PRE>';
    var_dump($value);
    exit;
}
function currentUrl()
{
    return currentDomain() . $_SERVER['REQUEST_URI'];
}

function methodField()
{

    return $_SERVER['REQUEST_METHOD'];
}

function displayError($displayerror)
{
    if ($displayerror) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(0);
    }
}

displayError(DISPLAY_ERROR);


global $flashMessage;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
function flash($name, $value = NULL)
{
    if ($value === NULL) {
        global $flashMessage;
        $message = isset($flashMessage[$name]) ? $flashMessage[$name] : '';
        return $message;
    } else {
        $_SESSION['flash_message'][$name] = $value;
    }
}
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('location: ' . url('login.php'));
    die();
}
if (isset($_GET['logoutadmin'])) {
    session_unset();
    session_destroy();
    header('location: ' . url('admin/login.php'));
    die();
}
//Ticket Topic
define('__MOSHAVER', 'واحد مشاوره و خرید پایه');
define('__LOGINROOM', ' مشکلات ورود به کلاس آنلاین');
define('__OFFICIAL', 'واحد مالی و اداری');
define('__TECHER', t('teacher_alt') . ' و مربیان');
define('__QUIZ', 'حوزه آزمون های الکترونیکی');
define('__MAINTENANCE', 'واحد فنی و توسعه زیرساخت');
define('__BAZRESI', 'واحد بازرسی ، ثبت شکایت');
define('__BOSS', 'حوزه مدیریت موسسه');

define('__TITLE', 'موسسه هنرآموزش');

//SMS Authentication
define('__USERSMS', '09127945631');
define('__PASSSMS', '&Amoozesh!1404%');

// Online Gateway BehPardakht Mellat
define('__TERMINAL_BPM', 7877055);
define('__USERNAME_BPM', "7877055");
define('__PASSWORD_BPM', "98706289");
//simotel variables
define('SIMOTEL_SERVER', '93.114.106.208:9880');
define('SIMOTEL_API_KEY', 'D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC');
define('SIMOTEL_BASIC_AUTH', 'SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=');

//API SKY ROOM
define('__SKY', 'https://www.skyroom.online/skyroom/api/apikey-27083573-4-c58d2f7b1fce66a4e35e42d564c29f40');
define('__DGAPIKEY', '1df8A528c6e1fa4ac2e4dd2519a3cE9770f9aD7b0bd5356598D6593ba931d80');

