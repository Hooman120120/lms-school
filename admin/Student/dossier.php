<?php
require_once __DIR__ . '/../../dbconfig.php';
require_once BASE_PATH . '/inc/function_login.php';
require_once BASE_PATH . '/inc/function.php';
require_once BASE_PATH . '/inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) { include BASE_PATH . '/blackList.html'; exit; }
if (empty($_SESSION['admin'])) { header('Location: ' . url('admin/login.php')); exit; }
$re = sessionAdmin($_SESSION['admin']['user']);
if (($_SESSION['admin']['ip'] !== GetIp() && $_SESSION['admin']['os'] != GetOs()) || session_id() != $re['session_id']) {
    session_unset(); header('Location: ' . url('admin/login.php')); exit;
}
if (!AdminCanSee('students.definitive.edit')) AdminDeny();
$dossierAdmin = true;
require BASE_PATH . '/inc/student_dossier_page.php';
