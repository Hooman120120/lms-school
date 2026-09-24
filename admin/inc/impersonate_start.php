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
$permissionUser = $_SESSION['admin']['real_user'] ?? $_SESSION['admin']['user'];
$stmt = $conn->prepare("SELECT can_impersonate FROM dbadmin WHERE username = ? LIMIT 1");
$stmt->execute([$permissionUser]);
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
 * Fetch target user info (username + role + names)
 */
$stmt = $conn->prepare("SELECT username, adminrole, fname, lname FROM dbadmin WHERE username = ? LIMIT 1");
$stmt->execute([$target]);
$targetRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetRow) {
    http_response_code(404);
    exit('User not found');
}

/**
 * Save your real identity ONCE (so Stop can restore)
 */
if (empty($_SESSION['admin']['real_user'])) {
    $_SESSION['admin']['real_user']      = $_SESSION['admin']['user'];
    $_SESSION['admin']['real_adminrole'] = $_SESSION['admin']['adminrole'] ?? null;
    $_SESSION['admin']['real_name']      = $_SESSION['admin']['name'] ?? null;
    $_SESSION['admin']['real_famili']    = $_SESSION['admin']['famili'] ?? null;
}

/**
 * Switch session to target identity (what the site reads)
 * IMPORTANT: do NOT change can_impersonate (keep your own power to stop/switch)
 */
$_SESSION['admin']['user']      = $targetRow['username'];
$_SESSION['admin']['adminrole'] = (int)$targetRow['adminrole'];
$_SESSION['admin']['name']      = $targetRow['fname'];
$_SESSION['admin']['famili']    = $targetRow['lname'];

// Optional: expire after 30 minutes
$_SESSION['admin']['acting_until'] = time() + (30 * 60);

/**
 * Redirect back
 */
$back = $_SERVER['HTTP_REFERER'] ?? url('admin/index.php');
header('location: ' . $back);
exit;
