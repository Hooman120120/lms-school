<?php
/*
 * Private OES -> LMS calendar import (one way: OES events appear in the LMS
 * calendar of the same school; nothing goes back). Signed with the shared
 * CROSS_PANEL_SECRET, the same short-lived HMAC envelope as cross-panel-login.
 *
 * Body: {"events": [event, ...], "deleted": [oes event id, ...]}
 * An imported event is an ordinary LMS calendar event marked with
 * sync_source = 'oes' and sync_source_id = the OES id, so a later push updates
 * or deletes that same row. Audience is mapped:
 *   OES roles        -> LMS adminrole values (inverse of cross_panel_oes_role)
 *   OES admins       -> LMS admins with the same username
 *   OES student groups -> LMS courses linked to them (codtbl_course.ugid)
 *   OES پایه         -> LMS پایه with the same title
 * A student part whose groups have no LMS course is dropped instead of
 * widening the event to every student.
 */
require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/inc/function.php'; // calendarSeedDefaultGrades, calendarEventMarker

header('Content-Type: application/json; charset=utf-8');

function calendarImportReply(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    calendarImportReply(405, ['ok' => false, 'message' => 'فقط POST مجاز است']);
}
$body = (string) file_get_contents('php://input');
$payload = json_decode($body, true);
$username = trim((string) ($_SERVER['HTTP_X_CROSS_USERNAME'] ?? ''));
$expires = (int) ($_SERVER['HTTP_X_CROSS_EXPIRES'] ?? 0);
$signature = (string) ($_SERVER['HTTP_X_CROSS_SIGNATURE'] ?? '');
if (cross_panel_secret() === '' || !is_array($payload) || $username === '' || $expires < time() || $expires > time() + 90
    || !hash_equals(cross_panel_signature('sync-calendar', $username, $expires, $body), $signature)) {
    calendarImportReply(403, ['ok' => false, 'message' => 'امضای درخواست معتبر نیست']);
}

/** OES school role -> LMS adminrole values (see cross_panel_oes_role for the other way). */
const OES_ROLE_TO_LMS = [
    1 => [0],                    // مدیر -> مدیریت
    2 => [2, 51, 52, 53, 54, 55], // معاونت -> مدیرمیانی و معاونت‌ها
    3 => [3],                    // معلم
    4 => [1],                    // مشاور -> پشتیبان
];

try {
    calendarSeedDefaultGrades($conn, 0); // marker / is_grade columns
    $has = $conn->query("SHOW COLUMNS FROM calendar_events LIKE 'sync_source'")->fetch();
    if (!$has) {
        $conn->exec("ALTER TABLE calendar_events ADD COLUMN sync_source VARCHAR(16) NULL DEFAULT NULL, ADD COLUMN sync_source_id INT NULL DEFAULT NULL, ADD UNIQUE KEY calendar_sync_source (sync_source, sync_source_id)");
    }

    $adminIdByUser = $conn->prepare('SELECT idadmin FROM dbadmin WHERE username = ? LIMIT 1');
    $fallbackCreator = (int) $conn->query('SELECT idadmin FROM dbadmin ORDER BY idadmin LIMIT 1')->fetchColumn();
    $gradeByTitle = $conn->prepare('SELECT id FROM calendar_groups WHERE title = ? AND is_grade = 1 LIMIT 1');
    $findRow = $conn->prepare("SELECT id FROM calendar_events WHERE sync_source = 'oes' AND sync_source_id = ? LIMIT 1");

    $saved = 0;
    $deleted = 0;
    foreach (array_slice((array) ($payload['events'] ?? []), 0, 2000) as $ev) {
        $sourceId = (int) ($ev['id'] ?? 0);
        $title = trim((string) ($ev['title'] ?? ''));
        $start = (string) ($ev['start_date'] ?? '');
        if ($sourceId <= 0 || $title === '' || !strtotime($start)) continue;
        $end = !empty($ev['end_date']) && strtotime((string) $ev['end_date']) ? (string) $ev['end_date'] : null;

        // audience
        $roles = [];
        foreach ((array) ($ev['admin_roles'] ?? []) as $r) {
            foreach (OES_ROLE_TO_LMS[(int) $r] ?? [] as $lmsRole) $roles[$lmsRole] = true;
        }
        $admins = [];
        foreach ((array) ($ev['admin_usernames'] ?? []) as $u) {
            $adminIdByUser->execute([(string) $u]);
            if ($id = (int) $adminIdByUser->fetchColumn()) $admins[$id] = true;
        }
        $courses = [];
        $ugids = array_values(array_filter(array_map('intval', (array) ($ev['ugids'] ?? []))));
        if ($ugids) {
            $in = implode(',', array_fill(0, count($ugids), '?'));
            $st = $conn->prepare("SELECT idcourse FROM codtbl_course WHERE ugid IN ($in)");
            $st->execute($ugids);
            $courses = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        }
        $targetType = in_array($ev['target_type'] ?? '', ['admin', 'user', 'both'], true) ? $ev['target_type'] : 'admin';
        if ($ugids && !$courses && $targetType !== 'admin') {
            // those student groups have no course here: keep the staff part only
            if ($targetType === 'user') continue;
            $targetType = 'admin';
        }
        $groupId = null;
        if (!empty($ev['group_title'])) {
            $gradeByTitle->execute([(string) $ev['group_title']]);
            $groupId = (int) $gradeByTitle->fetchColumn() ?: null;
        }
        $creator = $fallbackCreator;
        if (!empty($ev['creator_username'])) {
            $adminIdByUser->execute([(string) $ev['creator_username']]);
            $creator = (int) $adminIdByUser->fetchColumn() ?: $fallbackCreator;
        }
        $marker = calendarEventMarker(isset($ev['marker']) ? (string) $ev['marker'] : null);
        $fields = [$title, (string) ($ev['description'] ?? ''), $start, $end, $groupId, $targetType,
                   (int) ($ev['event_type'] ?? 0), (int) ($ev['visibility'] ?? 0), (int) ($ev['overlap'] ?? 0),
                   (int) ($ev['is_academic'] ?? 0), $marker];

        $conn->beginTransaction();
        $findRow->execute([$sourceId]);
        $eventId = (int) $findRow->fetchColumn();
        if ($eventId) {
            $conn->prepare('UPDATE calendar_events SET title = ?, description = ?, start_date = ?, end_date = ?, calendar_groups_id = ?, target_type = ?,
                event_type = ?, visibility = ?, overlap = ?, is_academic = ?, marker = ? WHERE id = ?')
                ->execute(array_merge($fields, [$eventId]));
        } else {
            $conn->prepare("INSERT INTO calendar_events (title, description, start_date, end_date, calendar_groups_id, target_type,
                event_type, visibility, overlap, is_academic, marker, creator_id, sync_source, sync_source_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'oes', ?)")
                ->execute(array_merge($fields, [$creator, $sourceId]));
            $eventId = (int) $conn->lastInsertId();
        }
        foreach (['calendar_admin_role_targets', 'calendar_admin_user_targets', 'calendar_course_targets', 'active_event_options'] as $t) {
            $conn->prepare("DELETE FROM $t WHERE event_id = ?")->execute([$eventId]);
        }
        $ins = $conn->prepare('INSERT INTO calendar_admin_role_targets (event_id, adminrole) VALUES (?, ?)');
        foreach (array_keys($roles) as $r) $ins->execute([$eventId, $r]);
        $ins = $conn->prepare('INSERT INTO calendar_admin_user_targets (event_id, admin_id) VALUES (?, ?)');
        foreach (array_keys($admins) as $a) $ins->execute([$eventId, $a]);
        if ($targetType !== 'admin') {
            $ins = $conn->prepare('INSERT INTO calendar_course_targets (event_id, course_id) VALUES (?, ?)');
            foreach (array_unique($courses) as $c) $ins->execute([$eventId, $c]);
        }
        $ins = $conn->prepare('INSERT INTO active_event_options (event_id, events_option, needs_reason) VALUES (?, ?, ?)');
        foreach ((array) ($ev['options'] ?? []) as $o) {
            $text = trim((string) ($o['option'] ?? ''));
            if ($text !== '') $ins->execute([$eventId, $text, !empty($o['needs_reason']) ? 1 : 0]);
        }
        $conn->commit();
        $saved++;
    }

    $del = $conn->prepare("SELECT id FROM calendar_events WHERE sync_source = 'oes' AND sync_source_id = ?");
    foreach (array_slice((array) ($payload['deleted'] ?? []), 0, 5000) as $sourceId) {
        $del->execute([(int) $sourceId]);
        $eventId = (int) $del->fetchColumn();
        if (!$eventId) continue;
        foreach (['calendar_admin_role_targets', 'calendar_admin_user_targets', 'calendar_course_targets', 'active_event_options', 'calendar_event_teacher_groups'] as $t) {
            $conn->prepare("DELETE FROM $t WHERE event_id = ?")->execute([$eventId]);
        }
        $conn->prepare('DELETE FROM calendar_events WHERE id = ?')->execute([$eventId]);
        $deleted++;
    }
    calendarImportReply(200, ['ok' => true, 'saved' => $saved, 'deleted' => $deleted]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('cross-panel-calendar: ' . $e->getMessage());
    calendarImportReply(500, ['ok' => false, 'message' => 'ذخیرهٔ رویدادها در پنل آموزش ناموفق بود']);
}
