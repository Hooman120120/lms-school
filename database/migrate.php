<?php
/**
 * Runs database/migrations/*.sql in filename order, each file once per
 * database. Applied files are recorded in `schema_migrations`, so files that
 * change data (role remaps, permission resets) never run a second time.
 *
 *   php database/migrate.php --db=lms_school [--user=root] [--pass=] [--host=localhost]
 *   php database/migrate.php --db=lms_school --status
 *   php database/migrate.php --db=lms_school --dry-run
 *   php database/migrate.php --db=lms_school --baseline=20260921_school_announcements.sql
 *
 * --baseline marks every file sorting before the given one as applied without
 * running it (for databases that already have those changes).
 * A failing statement stops the run; that file is not recorded.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$opt = getopt('', ['db:', 'user:', 'pass:', 'host:', 'status', 'dry-run', 'baseline:']);
$db = (string) ($opt['db'] ?? '');
if ($db === '') {
    fwrite(STDERR, "usage: php database/migrate.php --db=NAME [--user=root] [--pass=] [--host=localhost] [--status|--dry-run|--baseline=FILE]\n");
    exit(2);
}

$pdo = new PDO(
    'mysql:host=' . ($opt['host'] ?? 'localhost') . ';dbname=' . $db . ';charset=utf8mb4',
    (string) ($opt['user'] ?? 'root'),
    (string) ($opt['pass'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(190) NOT NULL PRIMARY KEY,
    checksum CHAR(64) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    baseline TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files, SORT_STRING);
$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);
$record = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum, baseline) VALUES (?, ?, ?)');

if (isset($opt['baseline'])) {
    $until = basename((string) $opt['baseline']);
    if (!is_file(__DIR__ . '/migrations/' . $until)) {
        fwrite(STDERR, "baseline file not found: $until\n");
        exit(2);
    }
    foreach ($files as $file) {
        $name = basename($file);
        if (strcmp($name, $until) >= 0) break;
        if (isset($applied[$name])) continue;
        $record->execute([$name, hash_file('sha256', $file), 1]);
        echo "baseline  $name\n";
    }
    exit(0);
}

$pending = array_values(array_filter($files, static fn($f) => !isset($applied[basename($f)])));
if (isset($opt['status']) || isset($opt['dry-run'])) {
    foreach ($files as $file) {
        echo (isset($applied[basename($file)]) ? 'applied   ' : 'pending   ') . basename($file) . "\n";
    }
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    echo "running   $name\n";
    foreach (migrate_split_sql((string) file_get_contents($file)) as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, "FAILED    $name\n  " . $e->getMessage() . "\n  in: " . mb_substr(preg_replace('/\s+/', ' ', $statement), 0, 200) . "\n");
            exit(1);
        }
    }
    $record->execute([$name, hash_file('sha256', $file), 0]);
}
echo count($pending) ? "done\n" : "nothing to run\n";

/** Split a SQL file into statements on `;`, ignoring quotes and comments. */
function migrate_split_sql(string $sql): array
{
    $statements = [];
    $current = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $next = $sql[$i + 1] ?? '';
        if ($c === '-' && $next === '-' || $c === '#') {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $len : $end;
            $current .= "\n";
            continue;
        }
        if ($c === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $j = $i + 1;
            while ($j < $len && ($sql[$j] !== $c || $sql[$j - 1] === '\\')) $j++;
            $current .= substr($sql, $i, $j - $i + 1);
            $i = $j;
            continue;
        }
        if ($c === ';') {
            if (trim($current) !== '') $statements[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $c;
    }
    if (trim($current) !== '') $statements[] = trim($current);
    return $statements;
}
