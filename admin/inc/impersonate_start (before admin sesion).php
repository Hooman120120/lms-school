<?php
// admin/inc/impersonate_start.php

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

/**
 * Permission check (session + DB)
 */
$canImpersonateSession = !empty($_SESSION['admin']['can_impersonate']) && (int)$_SESSION['admin']['can_impersonate'] === 1;
if (!$canImpersonateSession) {
    http_response_code(403);
    exit('Not allowed');
}

// Extra hardening: verify permission from DB (prevents session tampering)
$stmt = $conn->prepare("SELECT can_impersonate FROM dbadmin WHERE username = ? LIMIT 1");
$stmt->execute([$_SESSION['admin']['real_user'] ?? $_SESSION['admin']['user']]);
if ((int)$stmt->fetchColumn() !== 1) {
    http_response_code(403);
    exit('Not allowed');
}

/**
 * CSRF protection (requires header.php to send csrf field)
 */
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
 * Input
 */
$target = trim($_POST['user'] ?? '');
if ($target === '') {
    http_response_code(400);
    exit('Missing user');
}

/**
 * Validate target exists
 * (If you want to restrict to employees only, add WHERE ... AND is_employee=1 etc.)
 */
$stmt = $conn->prepare("SELECT username FROM dbadmin WHERE username = ? LIMIT 1");
$stmt->execute([$target]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    exit('User not found');
}

/**
 * Switch logic
 * - real_user is your real admin (set once)
 * - user becomes the acting username (John/Jack)
 */
if (empty($_SESSION['admin']['real_user'])) {
    $_SESSION['admin']['real_user'] = $_SESSION['admin']['user'];
}

// Now switch "current user" globally (so all your 150 pages follow it)
$_SESSION['admin']['user'] = $target;

// Optional: expire after 30 minutes
$_SESSION['admin']['acting_until'] = time() + (30 * 60);

/**
 * Redirect back
 */
$back = $_SERVER['HTTP_REFERER'] ?? url('admin/index.php');
header('location: ' . $back);
exit;
