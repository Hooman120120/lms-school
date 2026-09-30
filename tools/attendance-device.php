<?php
/**
 * Manage attendance devices from the command line (until the admin page exists).
 *
 *   php tools/attendance-device.php --db=lms_school list
 *   php tools/attendance-device.php --db=lms_school add --name="گیت ورودی" [--type=face|finger|card|other] [--location=gate|classroom]
 *   php tools/attendance-device.php --db=lms_school rotate --id=3
 *   php tools/attendance-device.php --db=lms_school disable --id=3
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../inc/attendance.php';

// --name=value options and one command word, in any order
$opt = [];
$cmd = 'list';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z]+)=(.*)$/s', $arg, $m)) $opt[$m[1]] = $m[2];
    else $cmd = $arg;
}
$db = (string) ($opt['db'] ?? '');
if ($db === '') {
    fwrite(STDERR, "usage: php tools/attendance-device.php --db=NAME [--user=root --pass= --host=localhost] list|add|rotate|disable\n");
    exit(2);
}
$pdo = new PDO('mysql:host=' . ($opt['host'] ?? 'localhost') . ";dbname=$db;charset=utf8mb4",
    (string) ($opt['user'] ?? 'root'), (string) ($opt['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

switch ($cmd) {
    case 'add':
        $name = trim((string) ($opt['name'] ?? ''));
        if ($name === '') {
            fwrite(STDERR, "--name is required\n");
            exit(2);
        }
        [$id, $key] = attendance_device_create($pdo, $name, (string) ($opt['type'] ?? 'other'),
            (string) ($opt['location'] ?? 'gate'), $opt['note'] ?? null, 'cli');
        echo "device $id created\nkey: $key\n(shown once; store it in the device settings)\n";
        break;
    case 'rotate':
        echo 'key: ' . attendance_device_rotate_key($pdo, (int) ($opt['id'] ?? 0)) . "\n";
        break;
    case 'disable':
        $pdo->prepare('UPDATE attendance_devices SET active = 0 WHERE id = ?')->execute([(int) ($opt['id'] ?? 0)]);
        echo "disabled\n";
        break;
    default:
        $rows = $pdo->query('SELECT d.id, d.name, d.type, d.location, d.active, d.key_hint, d.last_seen_at,
                (SELECT COUNT(*) FROM attendance_events e WHERE e.device_id = d.id) AS events
            FROM attendance_devices d ORDER BY d.id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            printf("%3d  %-20s %-6s %-9s %s  key %s…  last %s  events %d\n", $r['id'], $r['name'], $r['type'],
                $r['location'], $r['active'] ? 'active  ' : 'disabled', $r['key_hint'], $r['last_seen_at'] ?? '-', $r['events']);
        }
        if (!$rows) echo "no devices\n";
}
