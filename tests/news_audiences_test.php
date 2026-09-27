<?php
// Run without the application database: php tests/news_audiences_test.php school|institute
// Reuse the recipient fixtures and retain the daily-notice regression checks.
require __DIR__ . '/daily_notification_audiences_test.php';
$conn->exec('CREATE TABLE news (id INTEGER PRIMARY KEY AUTOINCREMENT, text TEXT, status INTEGER, creator TEXT, image_path TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT)');
$conn->exec('CREATE TABLE news_audience (news_id INTEGER, audience TEXT)');
$newsSource = file_get_contents(__DIR__ . '/../admin/notifications/news_panel.php');
$check(str_contains($newsSource, '$audienceLabels = DailyNotificationAudienceLabels($schoolAudienceRoles);'), 'News reads the shared role catalogue');
$check(str_contains($newsSource, "NormalizeDailyNotificationAudiences(\$_POST['audience'] ?? [], \$audienceLabels)"), 'News validates submitted recipients');
$renderNewsPills = static function ($selected) use ($newsSource, $audienceLabels) {
    $start = strpos($newsSource, '<?php foreach ($audienceLabels as $key => $label):');
    $end = strpos($newsSource, '<?php endforeach; ?>', $start) + strlen('<?php endforeach; ?>');
    $edit_selected_audiences = $selected;
    ob_start();
    eval('?>' . substr($newsSource, $start, $end - $start));
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . inst_display_html('<div>' . ob_get_clean() . '</div>') . '</body></html>');
    return new DOMXPath($doc);
};
$recipient = $school ? 's25' : '1';
foreach ([[], [$recipient]] as $selected) {
    $xpath = $renderNewsPills($selected);
    $check($xpath->query('//input[@name="audience[]"]')->length === count($audienceLabels), 'Create and edit forms show the current institution roles');
    $check($xpath->query('//input[@value="' . $recipient . '"][@checked]')->length === count($selected), 'Edit restores recipient selection');
    if ($school) {
        $check($xpath->query('//input[@value="s8"]')->length === 1 && $xpath->query('//input[@value="s25"]')->length === 1, 'School teacher and counselor are selectable');
        $check(!str_contains($xpath->document->textContent, 'کارشناس فروش') && !str_contains($xpath->document->textContent, 'پشتیبان'), 'School form excludes institute positions');
    } else {
        $check($xpath->query('//input[@value="6"]')->length === 1 && $xpath->query('//input[@value="s8"]')->length === 0, 'Institute form offers institute access levels');
    }
}

// Execute the real recipient read/expansion used by the edit form.
$stmt = $conn->prepare('INSERT INTO news_audience VALUES (?, ?)');
$stmt->execute([999, '51']);
$stmt->execute([999, '0']);
$edit_id = 999;
$start = strpos($newsSource, '$stmt_aud = $conn->prepare("SELECT audience FROM news_audience');
$end = strpos($newsSource, '$schoolAudienceRoles);', $start) + strlen('$schoolAudienceRoles);');
eval(substr($newsSource, $start, $end - $start));
$check($edit_selected_audiences === ($school ? ['s9', 's13', 's17'] : ['51', '0']), 'Legacy news remains editable with correct recipient names');
$start = strpos($newsSource, '$item_audiences =');
$end = strpos($newsSource, '$thumb =', $start);
$news = (object)['id' => 999];
$news_audiences = [999 => ['51', '0']];
eval(substr($newsSource, $start, $end - $start));
if ($school) {
    foreach (['معاون کل', 'معاون اجرایی', 'مدیر مدرسه'] as $title) $check(str_contains($aud_html, $title), 'List displays organizational recipients: ' . $title);
} else {
    $check(str_contains($aud_html, 'معاونت اجرایی') && str_contains($aud_html, 'مدیریت'), 'Institute list keeps its recipient labels');
}
$conn->exec('DELETE FROM news_audience');

// Execute the real create/update transaction body; strip only success redirects.
$start = strpos($newsSource, 'if ($news_id > 0)', strpos($newsSource, '$conn->beginTransaction();', strpos($newsSource, "isset(\$_POST['submit_news'])")));
$end = strpos($newsSource, '} catch (Exception $e)', $start);
$newsSaveSql = substr($newsSource, $start, $end - $start);
$newsSaveSql = preg_replace('/header\(.*?\);\s*die\(\);/s', '', $newsSaveSql);
$saveNews = static function ($raw, $message, $statusValue = 1, $id = 0) use ($conn, $audienceLabels, $newsSaveSql) {
    $audiences = NormalizeDailyNotificationAudiences($raw, $audienceLabels);
    if ($audiences === []) return null;
    $news_id = $id;
    $text = $message;
    $status = $statusValue;
    $uploaded_image = null;
    $conn->beginTransaction();
    eval($newsSaveSql);
    return $id ?: (int)$inserted_id;
};
$newsVisible = static fn($user) => array_column(ActivePanelNews($user), 'text');
$teacherRecipient = $school ? 's8' : '3';
$saveNews([$recipient], 'counselors');
$saveNews([$teacherRecipient], 'teachers');
$saveNews(['00'], 'students');
$saveNews(['000'], 'everyone');
$saveNews([$recipient, $teacherRecipient, '000'], 'multiple-recipients');
$inactiveId = $saveNews(['000'], 'inactive', 0);
$check(in_array('counselors', $newsVisible('counselor'), true) && !in_array('counselors', $newsVisible('teacher'), true), 'Recipient delivery is isolated');
$check(in_array('teachers', $newsVisible('teacher'), true) && !in_array('teachers', $newsVisible('counselor'), true), 'Teacher receives its own news');
$check(in_array('students', $newsVisible(null), true) && !in_array('teachers', $newsVisible(null), true), 'Student panel news stays isolated');
$check(in_array('everyone', $newsVisible('manager'), true) && in_array('everyone', $newsVisible(null), true), 'Everyone reaches both panels');
foreach (['manager', 'teacher', null] as $viewer) $check(!in_array('inactive', $newsVisible($viewer), true), 'Inactive news is not delivered');
$check(ActivePanelNews('missing-account') === [], 'Missing staff account is not a recipient');
if ($school) {
    $saveNews(['s9'], 'general-only');
    $saveNews(['s13'], 'executive-only');
    $saveNews(['s72'], 'custom-post');
    $check(in_array('general-only', $newsVisible('general'), true) && !in_array('general-only', $newsVisible('executive'), true), 'Posts sharing a system access level remain independent');
    $check(in_array('executive-only', $newsVisible('executive'), true) && !in_array('executive-only', $newsVisible('general'), true), 'Executive post is targeted separately');
    $check(in_array('custom-post', $newsVisible('custom'), true) && !in_array('custom-post', $newsVisible('cultural'), true), 'Database-defined organizational posts receive news');
    $check(!in_array('counselors', $newsVisible('unassigned'), true), 'Legacy access level cannot receive new organizational news');
    $check($saveNews(['0', '51', 's99', 'invalid'], 'invalid-recipient') === null, 'Invalid school recipient cannot be saved');
    $conn->exec("INSERT INTO news (text, status) VALUES ('legacy-teacher', 1)");
    $conn->prepare('INSERT INTO news_audience VALUES (?, ?)')->execute([$conn->lastInsertId(), '3']);
    $check(in_array('legacy-teacher', $newsVisible('teacher'), true), 'Existing legacy news still reaches its previous recipient');
}
$editedId = $saveNews([$recipient], 'before-edit');
$saveNews([$teacherRecipient], 'after-edit', 1, $editedId);
$check(!in_array('before-edit', $newsVisible('counselor'), true) && !in_array('after-edit', $newsVisible('counselor'), true), 'Edit removes the old audience');
$check(in_array('after-edit', $newsVisible('teacher'), true), 'Edit adds the new audience');
$check($conn->query('SELECT COUNT(*) FROM news_audience WHERE news_id = ' . $editedId)->fetchColumn() == 1, 'Edit replaces recipient rows');

// Exercise the actual quick status toggle SQL in SQLite too.
$conn->sqliteCreateFunction('IF', static fn($condition, $yes, $no) => $condition ? $yes : $no, 3);
$toggle_id = $inactiveId;
$start = strpos($newsSource, '$stmt = $conn->prepare("UPDATE news SET status = IF');
$end = strpos($newsSource, "\$stmt->execute([':id' => \$toggle_id]);", $start) + strlen("\$stmt->execute([':id' => \$toggle_id]);");
eval(substr($newsSource, $start, $end - $start));
$check(in_array('inactive', $newsVisible('teacher'), true), 'Publishing makes news visible to its selected audience');
$check(count(array_filter($newsVisible('teacher'), static fn($message) => $message === 'multiple-recipients')) === 1, 'News matching multiple audiences appears once');
foreach (['admin/index.php' => 'ActivePanelNews((string)$username)', 'index.php' => 'ActivePanelNews()'] as $file => $call) {
    $check(str_contains(file_get_contents(__DIR__ . '/../' . $file), $call), 'Dashboard uses the shared news recipient query: ' . $file);
}
echo inst_type() . ": news UI, create/edit, organizational delivery, and status checks passed\n";
