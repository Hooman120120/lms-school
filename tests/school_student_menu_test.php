<?php
// Run each mode separately: php tests/school_student_menu_test.php school|institute
define('INST_TYPE_OVERRIDE', $argv[1] ?? 'school');
require __DIR__ . '/../config/institution.php';
require __DIR__ . '/../config/institution_display.php';
require __DIR__ . '/../inc/function.php';
function url($path) { return '/' . $path; }

class StudentMenuTestStatement
{
    private array $args = [];
    public function __construct(private string $sql) {}
    public function execute(array $args): bool { $this->args = $args; return true; }
    public function fetchColumn()
    {
        global $studentMenuGrants;
        foreach ($studentMenuGrants as $grant) {
            if ($this->args[1] === $grant || str_starts_with($this->args[1], $grant . '.')) return 1;
        }
        return false;
    }
    public function fetchAll($mode): array
    {
        global $studentMenuGrants;
        return str_contains($this->sql, 'LIKE CONCAT')
            ? array_values(array_filter($studentMenuGrants, fn($grant) => str_starts_with($grant, $this->args[1] . '.')))
            : $studentMenuGrants;
    }
    public function fetch(): array { return []; }
}
class StudentMenuTestConnection
{
    public function prepare(string $sql): StudentMenuTestStatement { return new StudentMenuTestStatement($sql); }
}
$conn = new StudentMenuTestConnection();
$_SESSION['admin'] = ['user' => 'student-menu-test', 'adminrole' => 1];
$studentMenuGrants = ['students'];
$school = inst_is('school');
$check = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};
$dom = static function ($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>');
    return new DOMXPath($doc);
};
$source = file_get_contents(__DIR__ . '/../admin/admin-menu.php');
$start = strpos($source, "<?php if (AdminMenuCan('students',");
$end = strpos($source, '$execMenuIsAdmin');
$end = strrpos(substr($source, 0, $end), '<?php');
$fragment = substr($source, $start, $end - $start);
$renderMenu = static function () use ($fragment) {
    $adminRole = 1;
    ob_start();
    eval('?>' . $fragment);
    return inst_display_html(ob_get_clean());
};
$output = $renderMenu();
$xpath = $dom('<ul id="test-menu">' . $output . '</ul>');
$hasLink = static fn($path) => $xpath->query('//a[@href="/' . $path . '"]')->length;
$check($hasLink('admin/Student/experimental-add.php') === ($school ? 0 : 1), 'Temporary registration visibility');
$check($hasLink('admin/Student/edit-experimental.php') === ($school ? 0 : 1), 'Temporary editing visibility');
$check($hasLink('admin/democourse/addstudent.php') === 1, 'Extra-curricular enrolment appears once');
$check($hasLink('admin/Student/registration-Course-experimental.php') === 1, 'Extra-curricular report appears once');
if ($school) {
    $text = $xpath->document->textContent;
    $check(!str_contains($text, 'قطعی') && !str_contains($text, 'موقت'), 'School student groups are removed');
    $links = [];
    foreach ($xpath->query('//ul[@id="test-menu"]/li/ul/li/a') as $link) $links[] = $link->getAttribute('href');
    foreach (['/admin/democourse/addstudent.php' => '/admin/Student/addcourse.php',
              '/admin/Student/registration-Course-experimental.php' => '/admin/Student/registration-Course-absolute.php'] as $path => $after) {
        $check(array_search($path, $links, true) === array_search($after, $links, true) + 1, 'School menu order: ' . $path);
    }
    $check($xpath->query('//a[@href="/admin/Student/registration-Course-experimental.php"]')->item(0)->textContent === 'گزارش تفکیکی فوق برنامه', 'School report label');
} else {
    $check(str_contains($xpath->document->textContent, m('menu.students_definitive')), 'Institute definitive heading');
    $check(str_contains($xpath->document->textContent, m('menu.students_temporary')), 'Institute temporary heading');
}
foreach (['students.temporary.register', 'students.temporary.edit'] as $code) {
    $check(AdminPermissionCodeAvailable($code) === !$school, 'Permission mode: ' . $code);
    $check(AdminHasMenuPermission($code) === !$school, 'Full student grant respects mode: ' . $code);
    foreach (AdminPermissionIndex() as $childCode => $meta) {
        if (str_starts_with($childCode, $code . '.')) $check(AdminPermissionCodeAvailable($childCode) === !$school, 'Inherited operation mode');
    }
}
$source = file_get_contents(__DIR__ . '/../admin/employ/addemploy.php');
$start = strpos($source, 'function employeeMenuPermissionChildren(');
$end = strpos($source, 'function renderMenuPermissionTree(', $start);
eval(substr($source, $start, $end - $start));
$students = array_values(array_filter(AdminPermissionRegistry(), fn($node) => $node['code'] === 'students'))[0];
$renderAccess = static function ($selected) use ($students) {
    ob_start();
    renderMenuPermissionNode($students, $selected);
    return ob_get_clean();
};
$access = $dom($renderAccess(['students']));
foreach (['students.definitive', 'students.temporary', 'students.temporary.register', 'students.temporary.edit'] as $code) {
    $check($access->query('//input[@value="' . $code . '"]')->length === ($school ? 0 : 1), 'Employee permission visibility: ' . $code);
}
if ($school) {
    $children = employeeMenuPermissionChildren($students, []);
    $codes = array_column($children, 'code');
    $check(array_search('students.temporary.enrolment', $codes, true) === array_search('students.definitive.enrolment', $codes, true) + 1, 'Employee enrolment order');
    $check(array_search('students.temporary.report', $codes, true) === array_search('students.definitive.course-report', $codes, true) + 1, 'Employee report order');
    $access = $dom($renderAccess(['students.temporary']));
    $check($access->query('//input[@value="students.temporary.enrolment"][@checked]')->length === 1, 'Old temporary group grant survives');
    $check($access->query('//input[@value="students.temporary.report"][@checked]')->length === 1, 'Old report group grant survives');
    $check($access->query('//input[@value="students.definitive.register"][@checked]')->length === 0, 'Old temporary grant does not grant ordinary registration');
}
foreach (['students.temporary.enrolment', 'students.temporary.report'] as $code) {
    $studentMenuGrants = [$code];
    $xpath = $dom($renderMenu());
    $check($xpath->query('//a[@href="/admin/Student/add.php"]')->length === 0, 'Extra-curricular grant does not grant ordinary registration');
    $path = $code === 'students.temporary.enrolment' ? 'admin/democourse/addstudent.php' : 'admin/Student/registration-Course-experimental.php';
    $check($xpath->query('//a[@href="/' . $path . '"]')->length === 1, 'Independent grant keeps its menu link visible');
    $check(!AdminHasMenuPermission($code === 'students.temporary.enrolment' ? 'students.temporary.report' : 'students.temporary.enrolment'), 'Extra-curricular sibling permissions stay independent');
}
echo inst_type() . ": student menu and employee permissions passed\n";
