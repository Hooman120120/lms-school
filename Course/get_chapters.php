<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    die();
}
if (!isset($_GET['code']) and $_GET['code'] = '') {
    header('location: ' . url('Course/allcourse.php'));
    die();
}

$code = $_GET['code'] ?? null;
$dars = $_GET['dars'] ?? 'all';
$lesson = $_GET['lesson'] ?? 'all';

if ($lesson == 'all') {
    if ($dars == 'all') {
        $sql = 'SELECT subchapters.id AS subId, subchapters.name AS subName FROM subchapters INNER JOIN dblesson ON subchapters.lesson_id = dblesson.id WHERE dblesson.codecourse = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$code]);
    } else {
        $sql = 'SELECT subchapters.id AS subId, subchapters.name AS subName FROM subchapters INNER JOIN dblesson ON subchapters.lesson_id = dblesson.id WHERE dblesson.codecourse = ? AND dblesson.dars_id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$code, $dars]);
    }
} else {
    $sql = 'SELECT subchapters.id AS subId, subchapters.name AS subName FROM subchapters INNER JOIN dblesson ON subchapters.lesson_id = dblesson.id WHERE subchapters.lesson_id = ? AND dblesson.codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$lesson, $code]);
}

$chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($chapters);
