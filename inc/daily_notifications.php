<?php
/** Organizational recipients for daily notices, independent of access levels. */
function DailyNotificationSchoolRoles(): array
{
    global $conn;
    return $conn->query('SELECT id, role_key, title FROM school_admin_roles WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
}

function DailyNotificationAudienceLabels(?array $schoolRoles = null): array
{
    if (inst_is('school')) {
        $labels = [];
        foreach ($schoolRoles ?? DailyNotificationSchoolRoles() as $role) {
            $labels['s' . (int)$role['id']] = (string)$role['title'];
        }
    } else {
        $labels = [
            '0' => 'مدیریت', '1' => 'پشتیبان', '2' => 'مدیرمیانی',
            '3' => t('role_teacher'), '6' => 'کارشناس فروش',
            '51' => 'معاونت اجرایی', '52' => 'معاونت آموزشی', '53' => 'معاونت فروش',
            '54' => 'معاونت فنی', '55' => 'معاونت مالی',
        ];
    }
    $labels['00'] = t('student_pl');
    $labels['000'] = 'همه کاربران';
    return $labels;
}

/** Accept only recipients offered by the current institution's form. */
function NormalizeDailyNotificationAudiences($raw, array $labels): array
{
    if (!is_array($raw)) return [];
    $audiences = [];
    foreach ($raw as $audience) {
        if (!is_string($audience) && !is_int($audience)) continue;
        $code = trim((string)$audience);
        if (!isset($labels[$code])) continue;
        if ($code === '000') return ['000'];
        if (!in_array($code, $audiences, true)) $audiences[] = $code;
    }
    return $audiences;
}

/** Keep notices saved with the former access-level recipients editable. */
function DailyNotificationExpandLegacyAudiences(array $audiences, array $schoolRoles): array
{
    if (!inst_is('school')) return $audiences;
    $legacyRoles = [
        '0' => ['manager'], '1' => ['counselor'], '3' => ['teacher'],
        '2' => ['cultural_disciplinary_deputy', 'disciplinary_deputy', 'cultural_deputy'],
        '51' => ['general_deputy', 'executive_deputy'], '52' => ['grade_deputy'],
        '54' => ['technical_deputy'], '55' => ['financial_deputy'],
    ];
    $expanded = [];
    foreach ($audiences as $audience) {
        $code = trim((string)$audience);
        $matches = [];
        foreach ($schoolRoles as $role) {
            if (in_array($role['role_key'], $legacyRoles[$code] ?? [], true)) $matches[] = 's' . (int)$role['id'];
        }
        foreach ($matches ?: [$code] as $match) {
            if (!in_array($match, $expanded, true)) $expanded[] = $match;
        }
    }
    return $expanded;
}

/** Prefix school role IDs so they cannot collide with institute access levels. */
function DailyNotificationViewerAudiences(?string $adminUsername = null): array
{
    if ($adminUsername === null) return ['00', '000'];
    if ($adminUsername === '') return [];
    global $conn;
    $columns = inst_is('school') ? 'adminrole, school_role_id' : 'adminrole';
    $stmt = $conn->prepare('SELECT ' . $columns . ' FROM dbadmin WHERE username = ? LIMIT 1');
    $stmt->execute([$adminUsername]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) return [];
    $audiences = ['000'];
    if (inst_is('school') && !empty($admin['school_role_id'])) $audiences[] = 's' . (int)$admin['school_role_id'];
    // Numeric codes are used only by existing notices and institute notices.
    // New school notices are saved with the organizational role ID above.
    if (isset($admin['adminrole']) && $admin['adminrole'] !== '') $audiences[] = (string)$admin['adminrole'];
    return array_values(array_unique($audiences));
}

function ActiveDailyNotifications(?string $adminUsername = null): array
{
    global $conn;
    $audiences = DailyNotificationViewerAudiences($adminUsername);
    if ($audiences === []) return [];
    $placeholders = implode(',', array_fill(0, count($audiences), '?'));
    $stmt = $conn->prepare("SELECT DISTINCT n.*
        FROM notif n INNER JOIN notif_audience na ON n.id = na.notif_id
        WHERE na.audience IN ($placeholders)
          AND (n.expiration_date IS NULL OR n.expiration_date >= CURRENT_TIMESTAMP
               OR n.expiration_date = '0000-00-00 00:00:00')
        ORDER BY n.id DESC");
    $stmt->execute($audiences);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
