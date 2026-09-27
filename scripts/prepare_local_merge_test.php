<?php
// Create an isolated, bounded local fixture database. Never connects to a remote host.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$source = 'myhonar';
$target = 'lmsgitea_merge_test';
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');
$exists->execute([$target]);
if ($exists->fetchColumn()) { throw new RuntimeException('Test database already exists; refusing to overwrite it.'); }
$pdo->exec("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$target`");
$pdo->exec("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
// Logs, messages and operational queues intentionally remain empty.
$fixtures = ['dbadmin', 'school_admin_roles', 'setting_public', 'classes', 'codtbl_course',
    'dbpersonal', 'dblogin', 'dars', 'dbdars', 'class_dars', 'course_teacher_dars', 'school_staff_course_access',
    'institution_levels', 'institution_level_groups'];
$count = 0;
foreach ($pdo->query("SHOW FULL TABLES FROM `$source` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
    $quoted = str_replace('`', '``', $table);
    $ddl = $pdo->query("SHOW CREATE TABLE `$source`.`$quoted`")->fetch(PDO::FETCH_NUM)[1];
    $pdo->exec($ddl);
    if (in_array($table, $fixtures, true)) {
        $pdo->exec("INSERT INTO `$target`.`$quoted` SELECT * FROM `$source`.`$quoted` LIMIT 100");
    }
    ++$count;
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo "Created $target with $count table definitions and bounded local fixtures.\n";
