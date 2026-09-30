<?php
require_once __DIR__ . '/../inc/register_class_online_attendance.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE register_class_attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    register_class_events_id INTEGER NOT NULL,
    username TEXT NOT NULL,
    status TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (register_class_events_id, username, created_by)
)');
$db->exec('CREATE TABLE register_class_events (id INTEGER PRIMARY KEY, room_session_id TEXT UNIQUE)');
$db->exec("INSERT INTO register_class_events (id, room_session_id) VALUES (7, 'aaaaaaaaaaaaaaaaaaaaaaaa')");
check(!registerClassSessionIsAvailable($db, 'aaaaaaaaaaaaaaaaaaaaaaaa'), 'Used session must not be selectable');
check(registerClassSessionIsAvailable($db, 'aaaaaaaaaaaaaaaaaaaaaaaa', 7), 'Editing must retain its own session');
check(registerClassSessionIsAvailable($db, 'bbbbbbbbbbbbbbbbbbbbbbbb'), 'Unused session should be selectable');
$event = ['id' => 7, 'teacher_id' => 3];
$students = ['1111111111', '2222222222'];
registerClassStoreOnlineDurations($db, $event, $students, [
    '1111111111' => 1200, '2222222222' => 1201, 'outsider' => 3000,
]);
$rows = $db->query('SELECT username, status FROM register_class_attendance ORDER BY username')->fetchAll(PDO::FETCH_KEY_PAIR);
check($rows === ['1111111111' => '0', '2222222222' => '1'], 'Threshold or student scope failed');
registerClassStoreOnlineDurations($db, $event, $students, ['1111111111' => 1201]);
$rows = $db->query('SELECT username, status FROM register_class_attendance ORDER BY username')->fetchAll(PDO::FETCH_KEY_PAIR);
check($rows === ['1111111111' => '1', '2222222222' => '1'], 'Refresh did not update existing attendance');
registerClassStoreOnlineDurations($db, $event, $students, []);
check((int)$db->query('SELECT COUNT(*) FROM register_class_attendance')->fetchColumn() === 2, 'Missing report should not erase entries');
echo "LMS online attendance tests passed\n";
