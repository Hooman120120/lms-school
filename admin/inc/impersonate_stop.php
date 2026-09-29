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

// Returning to one's own account needs no permission.

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

/**
 * Restore real values if we were impersonating
 * (matches the impersonate_start.php that stores real_* keys)
 */
if (!empty($_SESSION['admin']['real_user'])) {
    $_SESSION['admin']['user'] = $_SESSION['admin']['real_user'];

    if (array_key_exists('real_adminrole', $_SESSION['admin'])) {
        $_SESSION['admin']['adminrole'] = $_SESSION['admin']['real_adminrole'];
    }
    if (array_key_exists('real_role', $_SESSION['admin'])) {
        $_SESSION['admin']['role'] = $_SESSION['admin']['real_role']; // legacy compatibility
    }
    if (array_key_exists('real_name', $_SESSION['admin'])) {
        $_SESSION['admin']['name'] = $_SESSION['admin']['real_name'];
    }
    if (array_key_exists('real_famili', $_SESSION['admin'])) {
        $_SESSION['admin']['famili'] = $_SESSION['admin']['real_famili'];
    }
}

// Clear impersonation state
unset(
    $_SESSION['admin']['real_user'],
    $_SESSION['admin']['real_adminrole'],
    $_SESSION['admin']['real_role'],
    $_SESSION['admin']['real_name'],
    $_SESSION['admin']['real_famili'],
    $_SESSION['admin']['acting_until']
);

// Redirect back
$back = $_SERVER['HTTP_REFERER'] ?? url('admin/index.php');
header('location: ' . $back);
exit;
