<?php
/**
 * Apply the attendance rules to every class register (for cron, e.g. every
 * 15 minutes on school days). Does nothing until the school enables the rules.
 *
 *   php tools/attendance-fill.php --db=lms_school [--user=root --pass= --host=localhost] [--days=2]
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../inc/attendance.php';

$opt = getopt('', ['db:', 'user:', 'pass:', 'host:', 'days:']);
$db = (string) ($opt['db'] ?? '');
if ($db === '') {
    fwrite(STDERR, "usage: php tools/attendance-fill.php --db=NAME [--user=root --pass= --host=localhost] [--days=2]\n");
    exit(2);
}
$pdo = new PDO('mysql:host=' . ($opt['host'] ?? 'localhost') . ";dbname=$db;charset=utf8mb4",
    (string) ($opt['user'] ?? 'root'), (string) ($opt['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$days = max(1, min(90, (int) ($opt['days'] ?? 2)));
$from = attendance_now()->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
$s = attendance_fill_register($pdo, null, $from);
printf("%s present %d, absent %d, updated %d, teacher-set kept %d\n", $db, $s['present'], $s['absent'], $s['updated'], $s['skipped_manual']);
