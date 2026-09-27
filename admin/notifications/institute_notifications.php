<?php
// Use the established notification screen, retaining its institution and role rules.
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
$notificationInstitution = 'institute';
require __DIR__ . '/school_notifications.php';
