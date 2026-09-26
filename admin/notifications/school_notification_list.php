<?php
// Dedicated destination for one notification category.  The shared screen
// supplies access checks, read/archive state, and the notification renderer.
$_GET['list'] = '1';
require __DIR__ . '/school_notifications.php';
