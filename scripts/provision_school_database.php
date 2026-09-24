<?php
/**
 * Build one school's lms database from the template database.
 *
 * Run by hf's provisioning worker, never by the web server. Everything arrives
 * in the environment (LMS_* and HF_*), like hf's other panel installers. The
 * template holds the structure plus the reference rows complete_schema.sql
 * ships (provinces, cities, staff roles, institution levels, VIP types, the
 * settings row); this copies all of it, then seeds the school's first manager.
 * hf treats a database with a row in dbadmin as finished, so that row is
 * written last.
 */
if (PHP_SAPI !== 'cli') exit(1);

$sourceName = trim((string) getenv('LMS_TEMPLATE_DB'));
$targetName = trim((string) getenv('LMS_NEW_DB'));
$installationId = trim((string) getenv('HF_PANEL_ID'));
$username = trim((string) getenv('HF_ADMIN_USERNAME'));
$password = (string) getenv('HF_ADMIN_PASSWORD');
$schoolName = trim((string) getenv('HF_SCHOOL_NAME'));

// dbadmin.username is varchar(12). dbadmin.password is compared as stored by
// admin/login.php (CheckAdminAcount), so it is written the same way.
if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $sourceName)
    || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $targetName)
    || $sourceName === $targetName
    || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $installationId)
    || $username === '' || mb_strlen($username) > 12
    || strlen($password) < 8 || strlen($password) > 200
    || $schoolName === '' || mb_strlen($schoolName) > 150) {
    fwrite(STDERR, "Invalid school provisioning input\n");
    exit(2);
}

$host = trim((string) getenv('LMS_DB_HOST')) ?: '127.0.0.1';
$dbUser = trim((string) getenv('LMS_DB_USER')) ?: 'root';
$dbPassword = getenv('LMS_DB_PASS') !== false ? (string) getenv('LMS_DB_PASS') : '';
$server = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $dbUser, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$sourceExists = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$sourceExists->execute([$sourceName]);
if (!(int) $sourceExists->fetchColumn()) throw new RuntimeException('Template database does not exist');

// Some columns default to a zero date ('0000-00-00'), which NO_ZERO_DATE would
// refuse when the table is re-created; complete_schema.sql relaxes the mode the
// same way for its own session.
$server->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
$server->exec("CREATE DATABASE `$targetName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server->exec("USE `$targetName`");
$server->exec('SET FOREIGN_KEY_CHECKS = 0');

try {
    $tables = $server->query("SHOW FULL TABLES FROM `$sourceName` WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as [$table]) {
        $definition = $server->query("SHOW CREATE TABLE `$sourceName`.`$table`")->fetch(PDO::FETCH_NUM);
        $create = (string) ($definition[1] ?? '');
        if ($create === '') throw new RuntimeException('Missing template table definition');
        $server->exec(preg_replace('/\sAUTO_INCREMENT=\d+\b/i', '', $create));
    }

    // Whatever rows the template carries are reference data for every school.
    foreach ($tables as [$table]) {
        if ($table === 'dbadmin') continue;
        if ((int) $server->query("SELECT COUNT(*) FROM `$sourceName`.`$table`")->fetchColumn() > 0) {
            $server->exec("INSERT INTO `$table` SELECT * FROM `$sourceName`.`$table`");
        }
    }

    // SHOW CREATE TABLE leaves triggers behind.
    $triggers = $server->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = " . $server->quote($sourceName))->fetchAll(PDO::FETCH_COLUMN);
    foreach ($triggers as $trigger) {
        $row = $server->query("SHOW CREATE TRIGGER `$sourceName`.`$trigger`")->fetch(PDO::FETCH_NUM);
        $sql = (string) ($row[2] ?? '');
        if ($sql === '') throw new RuntimeException('Missing template trigger definition');
        $sql = preg_replace('/\bDEFINER\s*=\s*\S+\s+/i', '', $sql, 1);
        $server->exec(str_replace('`' . $sourceName . '`.', '', $sql));
    }
    $server->exec('SET FOREIGN_KEY_CHECKS = 1');

    // The school's first manager: administrator, the manager school role, and
    // every permission flag the table has - whatever columns the schema adds.
    $flags = $server->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = " . $server->quote($targetName) . " AND TABLE_NAME = 'dbadmin'
            AND DATA_TYPE = 'tinyint' AND COLUMN_TYPE LIKE 'tinyint(1)%'
            AND (COLUMN_NAME LIKE 'perm%' OR COLUMN_NAME LIKE '%\\_permission' OR COLUMN_NAME LIKE 'per\\_%'
                 OR COLUMN_NAME IN ('administrator', 'can_impersonate', 'autodialer', 'teacher_perm_courses'))"
    )->fetchAll(PDO::FETCH_COLUMN);
    $flags = array_values(array_diff($flags, ['permission_is_not_supporter', 'simple_operator']));
    $columns = array_merge(['idadmin', 'username', 'password', 'fname', 'lname', 'gender', 'adminactive', 'adminrole', 'school_role_id'], $flags);
    $values = array_merge([1, $username, $password, 'مدیر', 'سامانه', '', 1, 0, 1], array_fill(0, count($flags), 1));
    // Any other NOT NULL column without a default gets its type's zero value.
    $required = $server->query(
        "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = " . $server->quote($targetName) . " AND TABLE_NAME = 'dbadmin'
            AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL AND EXTRA NOT LIKE '%auto_increment%'"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($required as $column => $type) {
        if (in_array($column, $columns, true)) continue;
        $columns[] = $column;
        $values[] = in_array($type, ['tinyint', 'smallint', 'int', 'bigint', 'decimal', 'float', 'double'], true) ? 0 : '';
    }

    $server->beginTransaction();
    $server->exec('INSERT IGNORE INTO hf_control_state (id) VALUES (1)');
    $insert = $server->prepare('INSERT INTO dbadmin (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
    $insert->execute($values);
    $server->commit();
    echo $targetName . PHP_EOL;
} finally {
    $server->exec('SET FOREIGN_KEY_CHECKS = 1');
}
