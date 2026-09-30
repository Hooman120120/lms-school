<?php
// Render the existing history with its self-view policy; no administrator session is used.
define('STUDENT_HISTORY_SELF', true);
chdir(__DIR__ . '/../admin/Student');
require 'student-history.php';
