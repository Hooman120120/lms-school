<?php
define('INST_TYPE_OVERRIDE', $argv[1] ?? 'school');
require __DIR__ . '/../config/institution.php';
$institute = inst_is('institute');
$check = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};
$registry = require __DIR__ . '/../config/admin_permission_registry.php';
$node = null;
$walk = function ($nodes) use (&$walk, &$node) {
    foreach ($nodes as $item) {
        if ($item['code'] === 'students.definitive.my-courses') $node = $item;
        $walk($item['children'] ?? []);
    }
};
$walk($registry);
$check($node && $node['modes'] === ['institute'], 'Per-course access is institute-only');
foreach (['desktop.php', 'desktop-undercover.php', 'manager.php'] as $file) {
    $source = file_get_contents(__DIR__ . '/../admin/panel/' . $file);
    $start = strpos($source, "<?php if (inst_is('institute')): ?>(<?php echo $");
    $end = strpos($source, '</span>', $start);
    $fragment = substr($source, $start, $end - $start);
    $final_course_regs = 5;
    $rec = (object)['coreg' => 5];
    $id = 2;
    ob_start();
    eval('?>' . $fragment);
    $output = trim(ob_get_clean());
    $check($output === ($institute ? '(5)2' : '2'), 'Dashboard count: ' . $file);
}
$source = file_get_contents(__DIR__ . '/../admin/panel/lacking_support.php');
$start = strpos($source, "if (inst_is('school')) {", strpos($source, '$rows = $stmtList'));
$end = strpos($source, "include_once '../../temp.html';", $start);
$rows = [
    ['username' => 'student1', 'codecourse' => 10],
    ['username' => 'student1', 'codecourse' => 11],
    ['username' => 'student2', 'codecourse' => 12],
];
eval(substr($source, $start, $end - $start));
$check(count($rows) === ($institute ? 3 : 2), 'School list counts each student once');
echo inst_type() . ": person-count checks passed\n";
