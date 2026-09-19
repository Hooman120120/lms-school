<?php
// admin/inc/impersonate_stop.php

include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header('location: ' . url('admin/login.php'));
    exit;
}

// Permission check: only accounts allowed to impersonate can stop (prevents random users calling it)
$canImpersonateSession = !empty($_SESSION['admin']['can_impersonate']) && (int)$_SESSION['admin']['can_impersonate'] === 1;
if (!$canImpersonateSession) {
    http_response_code(403);
    exit('Not allowed');
}

// CSRF protection
$csrf = $_POST['csrf'] ?? '';
if (
    empty($_SESSION['admin']['impersonate_token']) ||
    empty($csrf) ||
    !hash_equals($_SESSION['admin']['impersonate_token'], $csrf)
) {
    http_response_code(403);
    exit('Bad CSRF');
}

// If we are impersonating, restore the real admin
if (!empty($_SESSION['admin']['real_user'])) {
    $_SESSION['admin']['user'] = $_SESSION['admin']['real_user'];
}

// Clear impersonation state
unset($_SESSION['admin']['real_user'], $_SESSION['admin']['acting_until']);

// Optional: if you cached user list and want it refreshed sometimes, you can also clear it
// unset($_SESSION['admin']['impersonate_user_list']);

$back = $_SERVER['HTTP_REFERER'] ?? url('admin/index.php');
header('location: ' . $back);
exit;
