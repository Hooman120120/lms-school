<?php
/**
 * Incremental personal-feature schema for an existing upstream test database.
 * Derived from source migrations 20260916_register_class_{column_kinds,note,
 * quality,optional_date,score_width,permission}, 20260917_school_notifications,
 * 20260923_unify_school_document_notifications and 20260924_register_class_*.
 * Never imports a dump, reassigns roles, or rewrites subject/year data.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/local_test_connection.php';

function pfColumn(PDO $conn, string $table, string $column): ?array
{
    $q = $conn->prepare('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table, $column]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

function pfAdd(PDO $conn, string $table, string $column, string $definition): void
{
    if (!pfColumn($conn, $table, $column)) {
        $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "Added $table.$column\n";
    }
}

function pfIndex(PDO $conn, string $table, string $name, array $columns, bool $unique = false): void
{
    $q = $conn->prepare('SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
    $q->execute([$table]);
    $indexes = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $indexes[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME'];
        $indexes[$row['INDEX_NAME']]['unique'] = !(bool)$row['NON_UNIQUE'];
    }
    foreach ($indexes as $index) {
        if ($index['columns'] === $columns && (!$unique || $index['unique'])) { return; }
    }
    if (isset($indexes[$name])) { throw new RuntimeException("Conflicting index $table.$name; review manually."); }
    $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
    $conn->exec("ALTER TABLE `$table` ADD $kind `$name` (`" . implode('`,`', $columns) . '`)' );
    echo "Added $table.$name\n";
}

try {
    $conn = localTestConnection();
    // These nullable subject links are part of the upstream-compatible class-register shape.
    // Add them before prerequisite checks so a clean upstream clone can be prepared incrementally.
    pfAdd($conn, 'register_class_events', 'dars_id', 'INT NULL');
    pfAdd($conn, 'register_class_discipline', 'dars_id', 'INT NULL');
    // Check baseline first, before any DDL. These are upstream-owned tables.
    foreach ([
        'school_notifications' => ['id', 'dedupe_key', 'event_type', 'created_at'],
        'school_notification_reads' => ['notification_id', 'admin_username'],
        'register_class_events' => ['id', 'class_id', 'teacher_id', 'dars_id', 'academic_year_id', 'type', 'event_date'],
        'register_class_discipline' => ['class_id', 'created_by', 'dars_id'],
        'register_class_evaluation' => ['score'],
        'dbadmin' => ['idadmin', 'school_role_id'],
        'school_staff_courses' => ['admin_username', 'codecourse', 'academic_year_id'],
        'classes' => ['id', 'academic_year_id'],
        'calendar_events' => ['id'],
    ] as $table => $columns) {
        foreach ($columns as $column) {
            if (!pfColumn($conn, $table, $column)) {
                throw new RuntimeException("Missing upstream prerequisite $table.$column. Prepare the upstream schema and academic-year migration first.");
            }
        }
    }
    foreach ([
        'recipient_username' => 'VARCHAR(15) NULL',
        'attachment' => 'VARCHAR(255) NULL',
        'priority' => 'VARCHAR(40) NULL',
        'target_type' => "VARCHAR(30) NOT NULL DEFAULT 'all'",
        'target_value' => 'VARCHAR(100) NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'published'",
        'published_at' => 'DATETIME NULL',
    ] as $column => $definition) { pfAdd($conn, 'school_notifications', $column, $definition); }
    pfIndex($conn, 'school_notifications', 'idx_school_notifications_recipient', ['recipient_username', 'event_type', 'created_at']);
    pfIndex($conn, 'school_notifications', 'uq_school_notifications_dedupe', ['dedupe_key'], true);
    pfAdd($conn, 'register_class_events', 'group_uid', 'CHAR(32) NULL');
    pfAdd($conn, 'register_class_events', 'homework_notification_target', "VARCHAR(20) NOT NULL DEFAULT 'family'");
    pfIndex($conn, 'register_class_events', 'idx_rce_group_uid', ['group_uid']);
    pfIndex($conn, 'register_class_events', 'idx_rce_class_teacher_dars', ['class_id', 'teacher_id', 'dars_id']);
    pfIndex($conn, 'register_class_discipline', 'idx_rcd_class_creator_dars', ['class_id', 'created_by', 'dars_id']);
    pfAdd($conn, 'register_class_evaluation', 'note', 'VARCHAR(500) NULL');
    pfAdd($conn, 'register_class_evaluation', 'quality', 'TINYINT UNSIGNED NULL');
    pfAdd($conn, 'dbadmin', 'register_class_permission', 'TINYINT(1) NOT NULL DEFAULT 0');
    pfAdd($conn, 'calendar_events', 'is_academic', 'TINYINT(1) NOT NULL DEFAULT 0');

    $type = pfColumn($conn, 'register_class_events', 'type');
    if (strtolower($type['DATA_TYPE']) !== 'enum') {
        throw new RuntimeException('Expected upstream register_class_events.type ENUM; review manually.');
    }
    // Append missing kinds; retain existing enum positions and any additional kinds.
    $enum = $type['COLUMN_TYPE'];
    foreach (range(0, 5) as $kind) {
        if (strpos($enum, "'$kind'") === false) { $enum = substr($enum, 0, -1) . ",'$kind')"; }
    }
    if ($enum !== $type['COLUMN_TYPE']) {
        $nullable = $type['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL';
        $default = $type['COLUMN_DEFAULT'];
        // MariaDB returns quoted defaults; MySQL returns unquoted defaults.
        $defaultSql = $default === null ? '' : ' DEFAULT ' . (strtolower($default) === 'null' ? 'NULL' : $conn->quote(trim($default, "'")));
        $conn->exec('ALTER TABLE register_class_events MODIFY COLUMN type ' . $enum . $nullable . $defaultSql . ' COMMENT ' . $conn->quote($type['COLUMN_COMMENT']));
    }
    $date = pfColumn($conn, 'register_class_events', 'event_date');
    if ($date['DATA_TYPE'] !== 'date') { throw new RuntimeException('Expected event_date DATE; review manually.'); }
    if ($date['IS_NULLABLE'] !== 'YES') {
        $conn->exec('ALTER TABLE register_class_events MODIFY COLUMN event_date DATE NULL DEFAULT NULL COMMENT ' . $conn->quote($date['COLUMN_COMMENT']));
    }
    $score = pfColumn($conn, 'register_class_evaluation', 'score');
    if ($score['DATA_TYPE'] !== 'decimal') { throw new RuntimeException('Expected score DECIMAL; review manually.'); }
    $scale = max(2, (int)$score['NUMERIC_SCALE']);
    $precision = max(6, (int)$score['NUMERIC_PRECISION'] - (int)$score['NUMERIC_SCALE']) + $scale;
    if ((int)$score['NUMERIC_PRECISION'] < $precision || (int)$score['NUMERIC_SCALE'] < $scale || $score['IS_NULLABLE'] !== 'YES') {
        $conn->exec("ALTER TABLE register_class_evaluation MODIFY COLUMN score DECIMAL($precision,$scale) NULL DEFAULT NULL COMMENT " . $conn->quote($score['COLUMN_COMMENT']));
    }
    echo "Personal-feature migration complete. Existing dars_id and academic_year_id values are untouched.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\nDDL may have committed; correct the cause and rerun.\n");
    exit(1);
}
