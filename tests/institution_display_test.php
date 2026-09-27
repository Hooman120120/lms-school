<?php
define('INST_TYPE_OVERRIDE', $argv[1] ?? 'school');
require __DIR__ . '/../config/institution.php';
require __DIR__ . '/../config/institution_display.php';
$institute = inst_is('institute');
$expected = $institute ? 'فراگیران دوره‌ها' : 'دانش‌آموزان پایه‌ها';
$source = $institute ? 'دانش آموزان پایه‌ها' : 'فراگیران دوره‌ها';
$check = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};
$check(inst_display_text($source) === $expected, 'Plural vocabulary');
$check(inst_display_text($expected) === $expected, 'Idempotent vocabulary');
$check(inst_display_text('پایه تحصیلی') === 'پایه تحصیلی', 'Academic grade stays fixed');
$check(inst_display_text('دوره تحصیلی') === 'پایه تحصیلی', 'Legacy academic grade corrected');
$check(inst_display_text("دوره\n    تحصیلی") === "پایه\n    تحصیلی", 'Multiline academic grade corrected');
$gradeHtml = '<label class="form-label">پایه تحصیلی <span>*</span></label>';
$check(inst_display_html($gradeHtml) === $gradeHtml, 'Grade label survives output normalization');
$mixedText = 'پایه تحصیلی و دوره‌های فراگیران';
$check(inst_display_text($mixedText) === ($institute ? $mixedText : 'پایه تحصیلی و پایه‌های دانش‌آموزان'), 'Other vocabulary still follows institution type');

$html = '<div title="' . $source . '" data-code="پایه">' . $source
    . '<select><option value="پایه">' . $source . '</option></select>'
    . '<script>const key="پایه";</script><style>.پایه{}</style>'
    . '<textarea>پایه</textarea><!-- پایه --></div>';
$result = inst_display_html($html);
$check(str_contains($result, 'title="' . $expected . '"'), 'Tooltip translated');
$check(str_contains($result, '>' . $expected . '<select>'), 'Visible text translated');
$check(str_contains($result, 'value="پایه"'), 'Stored option value preserved');
$check(str_contains($result, 'data-code="پایه"'), 'Data attributes preserved');
$check(str_contains($result, '<script>const key="پایه";</script><style>.پایه{}</style>'), 'Code preserved');
$check(str_contains($result, '<textarea>پایه</textarea><!-- پایه -->'), 'Editable data preserved');
$check(inst_display_html('<div title="' . $source . ' > 0">' . $source . '</div>') === '<div title="' . $expected . ' > 0">' . $expected . '</div>', 'Quoted angle bracket');
$json = '{"label":"' . $source . '"}';
$check(inst_display_html($json) === $json, 'JSON preserved');
$check(inst_display_html("PK\x03\x04" . $source) === "PK\x03\x04" . $source, 'Download preserved');
$check(m('menu.active_courses') === ($institute ? 'دوره‌های فعال' : 'پایه‌های در حال اجرا'), 'Active menu');
if ($institute) {
    $check(m('menu.active_democourses') === 'دوره‌های موقت', 'Temporary menu');
    $check(m('menu.course_create') === 'ثبت دوره فعال', 'Create menu');
}
echo inst_type() . ": all checks passed\n";
