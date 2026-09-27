<?php
// Run only against a disposable/local database: php tests/local-regression.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../inc/function.php';
$conn = new PDO(getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=myhonar;charset=utf8mb4',
    getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function checkResult(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException($label); }
    ++$checks;
    echo "PASS: $label\n";
}
$conn->beginTransaction();
try {
    $admins = $conn->query('SELECT idadmin, username FROM dbadmin ORDER BY idadmin LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
    if (count($admins) < 3) { throw new RuntimeException('Need three local admin fixtures.'); }
    [$sender, $recipient, $outsider] = $admins;
    // Exercise a role that previously could see direct letters but could not mark them read.
    $roleId = $conn->query("SELECT id FROM school_admin_roles WHERE role_key='grade_deputy' LIMIT 1")->fetchColumn();
    if (!$roleId) {
        $roleId = 1 + (int)$conn->query('SELECT COALESCE(MAX(id),0) FROM school_admin_roles')->fetchColumn();
        $conn->prepare('INSERT INTO school_admin_roles (id,role_key,title) VALUES (?, ?, ?)')
            ->execute([$roleId, 'grade_deputy', 'Local regression role']);
    }
    $conn->prepare('UPDATE dbadmin SET school_role_id=? WHERE idadmin=?')->execute([$roleId, $recipient['idadmin']]);
    $marker = 'local-regression-' . bin2hex(random_bytes(6));
    checkResult(CreateSchoolNotification('exam', $marker, 'test', (int)$sender['idadmin'],
        $sender['username'], null, null, null, null, 'regression', $marker, 'family', 'test'),
        'System notification creation');
    checkResult(CreateSchoolNotification('exam', $marker, 'test', (int)$sender['idadmin'],
        $sender['username'], null, null, null, null, 'regression', $marker), 'Duplicate notification accepted');
    $q = $conn->prepare('SELECT COUNT(*) FROM school_notifications WHERE source_id = ?');
    $q->execute([$marker]);
    checkResult((int)$q->fetchColumn() === 1, 'Duplicate notification suppressed');

    $insert = $conn->prepare("INSERT INTO school_notifications
        (event_type,title,body,actor_username,recipient_username,target_type,status)
        VALUES (?,?,'test',?,?,'user','published')");
    foreach (['announcement', 'circular', 'special_letter'] as $type) {
        $insert->execute([$type, $marker, $sender['username'], $recipient['username']]);
        $id = (int)$conn->lastInsertId();
        checkResult(!MarkSchoolNotificationRead($id, $outsider['username']), "$type hidden from unrelated user");
        checkResult(MarkSchoolNotificationRead($id, $recipient['username']), "$type recipient can mark read");
        if ($type !== 'special_letter') {
            $conn->prepare("UPDATE school_notifications SET status='draft' WHERE id=?")->execute([$id]);
            checkResult(!MarkSchoolNotificationRead($id, $recipient['username']), "$type draft hidden");
        }
    }

    $classId = (int)$conn->query('SELECT id FROM classes ORDER BY id LIMIT 1')->fetchColumn();
    $student = (string)$conn->query('SELECT codemeli FROM dbpersonal ORDER BY codemeli LIMIT 1')->fetchColumn();
    if (!$classId || !$student) { throw new RuntimeException('Need a local class and student fixture.'); }
    checkResult(createRegisterClassEvent($classId, (int)$sender['idadmin'], (int)$sender['idadmin'],
        $marker, null, 5, '', null, 'family_student'), 'Undated homework column creation');
    $eventId = (int)$conn->lastInsertId();
    checkResult(saveStudentRegisterClassEntry($classId, $eventId, $student, 18.5, null, null,
        (string)$sender['idadmin']), 'Homework grade save');
    $q = $conn->prepare('SELECT score FROM register_class_evaluation WHERE register_class_events_id=? AND username=?');
    $q->execute([$eventId, $student]);
    checkResult((float)$q->fetchColumn() === 18.5, 'Homework grade actually persisted');
    checkResult(saveStudentRegisterClassEntry($classId, $eventId, $student, 19, null, null,
        (string)$sender['idadmin'], 'local test note', 2), 'Grade, note and quality update');
    $q = $conn->prepare('SELECT score,note,quality FROM register_class_evaluation WHERE register_class_events_id=? AND username=?');
    $q->execute([$eventId, $student]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    checkResult((float)$row['score'] === 19.0 && $row['note'] === 'local test note' && (int)$row['quality'] === 2,
        'Updated evaluation values persisted');
    $q = $conn->prepare("SELECT target_type,target_value FROM school_notifications WHERE source_type='register_class_homework_grade' AND source_id=?");
    $q->execute([$eventId . ':' . $student]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    checkResult($row && $row['target_type'] === 'family_student' && $row['target_value'] === $student,
        'Homework notification preserves audience');
    echo "Completed $checks checks; rolling back test rows.\n";
} finally {
    if ($conn->inTransaction()) { $conn->rollBack(); }
}
