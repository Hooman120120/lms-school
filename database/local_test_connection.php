<?php
/** Shared fail-closed connection for local schema/regression tooling. No app bootstrap. */
function localTestConnection(): PDO
{
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI only');
    }
    $dsn = getenv('TEST_DB_DSN');
    if (!is_string($dsn) || !preg_match('/\Amysql:(.+)\z/D', $dsn, $match)) {
        throw new RuntimeException('Explicit TEST_DB_DSN is required (mysql:host=127.0.0.1;dbname=lmsgitea_merge_test;charset=utf8mb4).');
    }
    $parts = [];
    foreach (explode(';', $match[1]) as $part) {
        if (!preg_match('/\A(host|port|dbname|charset)=([^;\s\x00]+)\z/D', $part, $pair)
            || isset($parts[$pair[1]])) {
            throw new RuntimeException('Unsupported, duplicate, or malformed TEST_DB_DSN option.');
        }
        $parts[$pair[1]] = $pair[2];
    }
    if (!in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true)
        || ($parts['dbname'] ?? '') !== 'lmsgitea_merge_test'
        || (isset($parts['charset']) && $parts['charset'] !== 'utf8mb4')
        || (isset($parts['port']) && (!ctype_digit($parts['port']) || (int)$parts['port'] < 1 || (int)$parts['port'] > 65535))) {
        throw new RuntimeException('Only a local MySQL database named lmsgitea_merge_test is allowed.');
    }
    $safeDsn = 'mysql:host=' . $parts['host'] . ';dbname=lmsgitea_merge_test;charset=utf8mb4';
    if (isset($parts['port'])) {
        $safeDsn .= ';port=' . $parts['port'];
    }
    $pdo = new PDO($safeDsn, getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'lmsgitea_merge_test') {
        throw new RuntimeException('Connected database does not match the allowed test database.');
    }
    return $pdo;
}
