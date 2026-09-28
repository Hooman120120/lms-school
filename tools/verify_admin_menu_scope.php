<?php
/** Regression check: old dbadmin columns must not reopen sibling tree branches. */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
define('BASE_PATH', dirname(__DIR__));
define('INST_TYPE_OVERRIDE', 'institute');
require BASE_PATH . '/config/institution.php';
require BASE_PATH . '/inc/function.php';

final class PermissionScopeStatement
{
    private array $params = [];
    public function __construct(private string $sql) {}
    public function execute(array $params = []): bool { $this->params = $params; return true; }
    public function fetchAll(int $mode = PDO::FETCH_BOTH): array
    {
        $grants = ['students.temporary'];
        if (str_contains($this->sql, 'CONCAT(?, ".")')) {
            return array_values(array_filter($grants, fn($code) => str_starts_with($code, $this->params[1] . '.')));
        }
        return $grants;
    }
    public function fetchColumn(): mixed
    {
        if (count($this->params) === 1) return 1; // tree-account check: this account has grants
        foreach (['students.temporary'] as $grant) {
            if ($grant === ($this->params[1] ?? null) || str_starts_with((string)($this->params[1] ?? ''), $grant . '.')) return 1;
        }
        return false;
    }
}
final class PermissionScopeDb
{
    public function prepare(string $sql): PermissionScopeStatement { return new PermissionScopeStatement($sql); }
}
$conn = new PermissionScopeDb();
$_SESSION['admin']['user'] = 'permission-scope-test';

foreach (['students.definitive', 'executive', 'courses', 'dashboard.summary.cards'] as $denied) {
    if (AdminMenuCan($denied, true)) throw new RuntimeException("Legacy flag reopened denied branch: $denied");
}
if (!AdminMenuCan('students.temporary', false)) throw new RuntimeException('Granted experimental branch is hidden.');
if (Permision('administrator')) throw new RuntimeException('Old administrator flag bypassed tree grants.');
echo "OK: temporary student grant does not reveal definitive, executive, courses, or cards.\n";
