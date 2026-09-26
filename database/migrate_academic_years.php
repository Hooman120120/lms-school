<?php
/** One-time, idempotent academic-year migration. Run: php database/migrate_academic_years.php */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require dirname(__DIR__) . '/dbconfig.php';
require_once dirname(__DIR__) . '/inc/function.php';
require_once dirname(__DIR__) . '/inc/jdf.php';

function ayColumnExists(PDO $conn, string $table, string $column): bool {
    $q = $conn->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table, $column]); return (bool)$q->fetchColumn();
}
function ayTableExists(PDO $conn, string $table): bool {
    $q = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]); return (bool)$q->fetchColumn();
}

try {
    $conn->exec('CREATE TABLE IF NOT EXISTS academic_years (id INT UNSIGNED NOT NULL AUTO_INCREMENT, base_jalali_year SMALLINT UNSIGNED NOT NULL, title VARCHAR(20) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, is_current TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_academic_years_base_year (base_jalali_year), KEY idx_academic_years_current (is_current)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $current = (int)getSchoolAcademicYearRange(time())['base_jalali_year'];
    $upsert = $conn->prepare('INSERT INTO academic_years (base_jalali_year,title,start_date,end_date,is_current) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),start_date=VALUES(start_date),end_date=VALUES(end_date),is_current=VALUES(is_current)');
    for ($year = 1400; $year <= $current; $year++) {
        $start = jalali_to_gregorian($year, 4, 1); $end = jalali_to_gregorian($year + 1, 4, 1);
        $upsert->execute([$year, $year . '–' . ($year + 1), sprintf('%04d-%02d-%02d', ...$start), sprintf('%04d-%02d-%02d', ...$end), $year === $current]);
    }
    $conn->prepare('UPDATE academic_years SET is_current=CASE WHEN base_jalali_year=? THEN 1 ELSE 0 END')->execute([$current]);
    // A base/course definition may be reused across years, so it is linked via
    // a pivot instead of putting a single year id on codtbl_course.
    $conn->exec('CREATE TABLE IF NOT EXISTS academic_year_courses (academic_year_id INT UNSIGNED NOT NULL, codecourse INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (academic_year_id,codecourse), KEY idx_academic_year_courses_course (codecourse)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    // Child records of classes inherit the year through classes.academic_year_id.
    $tables = ['classes','dbcourse','dbfactor','db_wallet','payments','payment_installments','dbquiz','homework_admin','teacher_lesson_plans','calendar_events','register_class_events','tbl_attendanced','dbattendance','stdhozor','student_status','student_suspension_requests','school_staff_courses','school_staff_insurance','school_staff_monthly_payments','school_assigned_tasks','course_status','dbschedules'];
    foreach ($tables as $table) {
        if (!ayTableExists($conn, $table) || ayColumnExists($conn, $table, 'academic_year_id')) continue;
        $conn->exec("ALTER TABLE `{$table}` ADD COLUMN academic_year_id INT UNSIGNED NULL");
        $conn->exec("ALTER TABLE `{$table}` ADD KEY `idx_{$table}_academic_year` (academic_year_id)");
    }

    // Backfill only from reliable dates. Ambiguous legacy rows deliberately stay NULL.
    foreach ([['dbcourse','created_at'],['dbfactor','datecreate'],['db_wallet','datecreted'],['payments','created_at'],['dbquiz','datestart'],['homework_admin','created_at'],['teacher_lesson_plans','plan_date'],['calendar_events','start_date'],['register_class_events','event_date'],['tbl_attendanced','datestart']] as [$table,$date]) {
        if (ayColumnExists($conn,$table,'academic_year_id') && ayColumnExists($conn,$table,$date)) $conn->exec("UPDATE `{$table}` r INNER JOIN academic_years ay ON DATE(r.`{$date}`)>=ay.start_date AND DATE(r.`{$date}`)<ay.end_date SET r.academic_year_id=ay.id WHERE r.academic_year_id IS NULL AND r.`{$date}` IS NOT NULL");
    }
    $conn->exec('UPDATE classes c INNER JOIN (SELECT class_id,MIN(academic_year_id) academic_year_id,COUNT(DISTINCT academic_year_id) years FROM dbcourse WHERE class_id IS NOT NULL AND academic_year_id IS NOT NULL GROUP BY class_id) x ON x.class_id=c.id AND x.years=1 SET c.academic_year_id=x.academic_year_id WHERE c.academic_year_id IS NULL');
    $conn->exec('INSERT IGNORE INTO academic_year_courses (academic_year_id,codecourse) SELECT DISTINCT academic_year_id,codecourse FROM classes WHERE academic_year_id IS NOT NULL');
    $conn->exec('INSERT IGNORE INTO academic_year_courses (academic_year_id,codecourse) SELECT DISTINCT academic_year_id,codecourse FROM dbcourse WHERE academic_year_id IS NOT NULL');
    $conn->exec('INSERT IGNORE INTO academic_year_courses (academic_year_id,codecourse) SELECT ay.id,c.codecourse FROM codtbl_course c INNER JOIN academic_years ay ON c.datestart>=ay.start_date AND c.datestart<ay.end_date WHERE c.datestart IS NOT NULL');
    echo "Academic-year migration completed. Legacy rows with NULL academic_year_id need review.\n";
} catch (Throwable $e) { fwrite(STDERR, "Migration failed: {$e->getMessage()}\n"); exit(1); }
