<?php
// In-memory database only: php tests/daily_notification_audiences_test.php school|institute
define('INST_TYPE_OVERRIDE', $argv[1] ?? 'school');
require __DIR__ . '/../config/institution.php';
require __DIR__ . '/../config/institution_display.php';
require __DIR__ . '/../inc/daily_notifications.php';
$conn = new PDO('sqlite::memory:');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec('CREATE TABLE school_admin_roles (id INTEGER PRIMARY KEY, role_key TEXT, title TEXT, is_active INTEGER, sort_order INTEGER)');
$conn->exec('CREATE TABLE dbadmin (username TEXT PRIMARY KEY, adminrole TEXT, school_role_id INTEGER)');
$conn->exec('CREATE TABLE notif (id INTEGER PRIMARY KEY AUTOINCREMENT, text TEXT, creator TEXT, image_path TEXT, expiration_date TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$conn->exec('CREATE TABLE notif_audience (notif_id INTEGER, audience TEXT)');
$roles = [
    [17, 'manager', 'مدیر مدرسه', 1, 1], [9, 'general_deputy', 'معاون کل', 1, 2],
    [13, 'executive_deputy', 'معاون اجرایی', 1, 3], [20, 'grade_deputy', 'معاون پایه', 1, 4],
    [25, 'counselor', 'مشاور', 1, 5], [8, 'teacher', 'معلم', 1, 6],
    [54, 'technical_deputy', 'معاون فنی', 1, 7], [55, 'financial_deputy', 'معاون مالی', 1, 8],
    [33, 'cultural_disciplinary_deputy', 'معاون فرهنگی و انضباطی', 1, 9],
    [72, 'custom_school_post', 'سمت جدید مدرسه', 1, 10], [99, 'inactive_post', 'سمت غیرفعال', 0, 11],
];
$stmt = $conn->prepare('INSERT INTO school_admin_roles VALUES (?, ?, ?, ?, ?)');
foreach ($roles as $role) $stmt->execute($role);
$stmt = $conn->prepare('INSERT INTO dbadmin VALUES (?, ?, ?)');
foreach ([['manager', '0', 17], ['general', '51', 9], ['executive', '51', 13], ['grade', '52', 20],
          ['counselor', '1', 25], ['teacher', '3', 8], ['technical', '54', 54], ['financial', '55', 55],
          ['cultural', '2', 33], ['custom', '2', 72], ['unassigned', '1', null]] as $staff) $stmt->execute($staff);
$check = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};
$school = inst_is('school');
$_SESSION['admin'] = ['user' => 'manager'];
$source = file_get_contents(__DIR__ . '/../admin/notifications/daily_notifications.php');
$schoolAudienceRoles = $school ? DailyNotificationSchoolRoles() : [];
$audienceLabels = DailyNotificationAudienceLabels($schoolAudienceRoles);
if ($school) {
    foreach ($roles as $role) $check(isset($audienceLabels['s' . $role[0]]) === (bool)$role[3], 'Organizational role eligibility: ' . $role[1]);
    $check($audienceLabels['s8'] === 'معلم' && $audienceLabels['s25'] === 'مشاور', 'Teacher and counselor labels come from the database');
    foreach (['0', '1', '2', '3', '6', '51', '52', '53', '54', '55'] as $code) $check(!isset($audienceLabels[$code]), 'Legacy access levels are not offered at school');
    $check(NormalizeDailyNotificationAudiences(['s99', '0', '51', 'not-a-role'], $audienceLabels) === [], 'Inactive and institute roles cannot be submitted');
    $check(NormalizeDailyNotificationAudiences(['s25', 's25', ['s8'], 's999'], $audienceLabels) === ['s25'], 'Malformed, duplicate, and unknown recipients');
    $check(DailyNotificationExpandLegacyAudiences(['51', '1', '00'], $schoolAudienceRoles) === ['s9', 's13', 's25', '00'], 'Legacy audiences remain editable using school roles');
} else {
    $check(isset($audienceLabels[51]) && $audienceLabels[1] === 'پشتیبان' && !isset($audienceLabels['s25']), 'Institute recipient map is preserved');
    $check(NormalizeDailyNotificationAudiences(['s25'], $audienceLabels) === [], 'Institute cannot save school-specific recipients');
}
$check(NormalizeDailyNotificationAudiences('000', $audienceLabels) === [], 'Non-array submission rejected');
$check(NormalizeDailyNotificationAudiences(['00', '000'], $audienceLabels) === ['000'], 'All users replaces individual groups');

// Numeric manager audience '0' must not disappear when reading existing notices.
$start = strpos($source, '$current_audiences = isset($notif->audience_list)');
$end = strpos($source, '?>', $start);
$notif = (object)['audience_list' => '0'];
eval(substr($source, $start, $end - $start));
$check($current_audiences === ($school ? ['s17'] : ['0']), 'Stored manager recipient is preserved');

// Render the real create and edit recipient lists, including edit selection state.
$renderPills = static function ($editing, $selected = []) use ($source, $audienceLabels) {
    $needle = '<?php foreach ($audienceLabels as $key => $label):';
    $start = strpos($source, $needle);
    if ($editing) $start = strpos($source, $needle, $start + 1);
    $end = strpos($source, '<?php endforeach; ?>', $start) + strlen('<?php endforeach; ?>');
    $current_audiences = $selected;
    ob_start();
    eval('?>' . substr($source, $start, $end - $start));
    $html = inst_display_html('<div>' . ob_get_clean() . '</div>');
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>');
    return new DOMXPath($doc);
};
$target = $school ? 's25' : '1';
foreach ([false, true] as $editing) {
    $xpath = $renderPills($editing, [$target]);
    $check($xpath->query('//input[@name="audience[]"]')->length === count($audienceLabels), 'Both forms offer the full current recipient list');
    $check($xpath->query('//input[@value="' . $target . '"]')->length === 1, 'Recipient value rendered in both forms');
    if ($editing) $check($xpath->query('//input[@value="' . $target . '"][@checked]')->length === 1, 'Edit form restores recipients');
}
$check(substr_count($source, "NormalizeDailyNotificationAudiences(\$_POST['audience'] ?? [], \$audienceLabels)") === 2, 'Create and update both validate recipients');

// Execute the actual create/update SQL and recipient loops from the page.
$start = strpos($source, '$sql_insert =');
$createSql = substr($source, $start, strpos($source, '$conn->commit();', $start) - $start);
$start = strpos($source, '$sql_update =');
$updateSql = substr($source, $start, strpos($source, '$conn->commit();', $start) - $start);
$create = static function ($raw, $message, $expiry = null) use ($conn, $audienceLabels, $createSql) {
    $audiences = NormalizeDailyNotificationAudiences($raw, $audienceLabels);
    if ($audiences === []) return null;
    $text = $message;
    $expiration_date = $expiry;
    $image_path = null;
    $conn->beginTransaction();
    eval($createSql);
    $conn->commit();
    return (int)$notif_id;
};
$visible = static fn($user) => array_column(ActiveDailyNotifications($user), 'text');
$targetCode = $school ? 's25' : '1';
$teacherCode = $school ? 's8' : '3';
$create([$targetCode], 'counselor-no-expiry');
$create([$targetCode], 'counselor-future', '2099-01-01 00:00:00');
$create([$targetCode], 'counselor-zero-expiry', '0000-00-00 00:00:00');
$create([$targetCode], 'counselor-expired', '2000-01-01 00:00:00');
$create([$teacherCode], 'teachers');
$create(['00'], 'students');
$create(['000'], 'everyone');
$create([$targetCode, $teacherCode], 'multiple-groups');
$counselor = $visible('counselor');
foreach (['counselor-no-expiry', 'counselor-future', 'counselor-zero-expiry', 'everyone', 'multiple-groups'] as $message) $check(in_array($message, $counselor, true), 'Counselor delivery: ' . $message);
foreach (['counselor-expired', 'teachers', 'students'] as $message) $check(!in_array($message, $counselor, true), 'Counselor excludes other or expired notices: ' . $message);
$check(in_array('teachers', $visible('teacher'), true) && !in_array('teachers', $visible('manager'), true), 'Teacher notice only reaches teachers');
$check(in_array('students', $visible(null), true) && !in_array('teachers', $visible(null), true), 'Student dashboard delivery stays separate');
$check(ActiveDailyNotifications('missing-account') === [], 'Missing admin cannot receive notices');
if ($school) {
    $create(['s9'], 'general-only');
    $create(['s13'], 'executive-only');
    $check(in_array('general-only', $visible('general'), true) && !in_array('general-only', $visible('executive'), true), 'Organizational posts with the same legacy role remain separate');
    $check(in_array('executive-only', $visible('executive'), true) && !in_array('executive-only', $visible('general'), true), 'Executive deputy is independently targeted');
    $create(['s72'], 'custom-post');
    $check(in_array('custom-post', $visible('custom'), true) && !in_array('custom-post', $visible('cultural'), true), 'New database-defined posts are independently targeted');
    $check(!in_array('counselor-no-expiry', $visible('unassigned'), true), 'A legacy access level does not grant a new organizational notice');
    $conn->exec("INSERT INTO notif (text) VALUES ('legacy-teacher')");
    $conn->prepare('INSERT INTO notif_audience VALUES (?, ?)')->execute([$conn->lastInsertId(), '3']);
    $check(in_array('legacy-teacher', $visible('teacher'), true), 'Existing numeric recipient notices still reach their former audience');
}
$notif_id = $create([$targetCode], 'before-edit');
$audiences = NormalizeDailyNotificationAudiences([$teacherCode], $audienceLabels);
$text = 'after-edit';
$new_image = null;
$expiration_date = null;
$conn->beginTransaction();
eval($updateSql);
$conn->commit();
$check(!in_array('before-edit', $visible('counselor'), true) && !in_array('after-edit', $visible('counselor'), true), 'Edit removes the former recipient');
$check(in_array('after-edit', $visible('teacher'), true), 'Edit delivers to the new recipient');
$check($conn->query('SELECT COUNT(*) FROM notif_audience WHERE notif_id = ' . (int)$notif_id)->fetchColumn() == 1, 'Edit replaces recipient rows');
$check(count(array_filter($counselor, static fn($message) => $message === 'multiple-groups')) === 1, 'Multi-recipient notice appears once');
foreach (['admin/index.php' => 'ActiveDailyNotifications((string)$username)', 'index.php' => 'ActiveDailyNotifications()'] as $file => $call) {
    $check(str_contains(file_get_contents(__DIR__ . '/../' . $file), $call), 'Dashboard uses shared recipient policy: ' . $file);
}
echo inst_type() . ": recipient UI, create/update, delivery, and expiry checks passed\n";
