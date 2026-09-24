<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit('نشست کاربری معتبر نیست.');
}
if ($_SESSION['admin']['ip'] !== GetIp() && $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    http_response_code(401);
    exit('نشست کاربری معتبر نیست.');
}
$session = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($session['session_id'] ?? null)) {
    session_unset();
    http_response_code(401);
    exit('نشست کاربری معتبر نیست.');
}
if (!Permision('permision_managecall')) {
    http_response_code(403);
    exit('شما مجوز مشاهده گزارش تماس اپراتورها را ندارید.');
}

function operatorCallStatusLabel(int $status): string
{
    $labels = [
        0 => 'تماس گرفته نشده',
        1 => 'نیاز به تماس مجدد',
        2 => 'تماس پایان یافته',
        3 => 'شماره اشتباه',
        4 => 'مخاطب تکراری',
        5 => 'عدم پاسخ',
    ];
    return $labels[$status] ?? 'نامشخص';
}

function operatorCallJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = (string)($_GET['action'] ?? 'summary');
$dakheli = trim((string)($_GET['dakheli'] ?? ''));
if ($dakheli === '' || !preg_match('/^[0-9]{1,6}$/', $dakheli)) {
    if ($action === 'export') {
        http_response_code(400);
        exit('داخلی اپراتور نامعتبر است.');
    }
    operatorCallJson(['success' => false, 'message' => 'داخلی اپراتور نامعتبر است.'], 400);
}

$operatorStmt = $conn->prepare('SELECT fname, lname, dakheli FROM dbadmin WHERE dakheli = ? LIMIT 1');
$operatorStmt->execute([$dakheli]);
$operator = $operatorStmt->fetch(PDO::FETCH_ASSOC);
if (!$operator) {
    if ($action === 'export') {
        http_response_code(404);
        exit('اپراتور یافت نشد.');
    }
    operatorCallJson(['success' => false, 'message' => 'اپراتور یافت نشد.'], 404);
}
$operatorName = trim((string)$operator['fname'] . ' ' . (string)$operator['lname']);

if ($action === 'summary') {
    $stmt = $conn->prepare('
        SELECT
            g.id AS group_id,
            g.namegroup AS group_name,
            COUNT(t.id) AS total_count,
            SUM(t.act = 0) AS uncalled_count,
            SUM(t.act = 5) AS unanswered_count,
            SUM(t.act = 1) AS recall_count,
            SUM(t.act = 2) AS finished_count,
            SUM(t.act = 4) AS duplicate_count,
            SUM(t.act = 3) AS wrong_count
        FROM tel_db t
        INNER JOIN tel_group g ON g.id = t.`group`
        WHERE t.dakheli = ?
        GROUP BY g.id, g.namegroup
        ORDER BY g.created_at DESC, g.id DESC
    ');
    $stmt->execute([$dakheli]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($groups as &$group) {
        foreach (['group_id', 'total_count', 'uncalled_count', 'unanswered_count', 'recall_count', 'finished_count', 'duplicate_count', 'wrong_count'] as $numericField) {
            $group[$numericField] = (int)$group[$numericField];
        }
    }
    unset($group);
    operatorCallJson(['success' => true, 'operator_name' => $operatorName, 'dakheli' => $dakheli, 'groups' => $groups]);
}

$groupId = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);
if (!$groupId || $groupId < 1) {
    if ($action === 'export') {
        http_response_code(400);
        exit('گروه تماس نامعتبر است.');
    }
    operatorCallJson(['success' => false, 'message' => 'گروه تماس نامعتبر است.'], 400);
}

$groupStmt = $conn->prepare('SELECT namegroup FROM tel_group WHERE id = ? LIMIT 1');
$groupStmt->execute([$groupId]);
$groupName = $groupStmt->fetchColumn();
if ($groupName === false) {
    if ($action === 'export') {
        http_response_code(404);
        exit('گروه تماس یافت نشد.');
    }
    operatorCallJson(['success' => false, 'message' => 'گروه تماس یافت نشد.'], 404);
}

if ($action === 'export') {
    $stmt = $conn->prepare('
        SELECT t.id, t.fname, t.lname, t.guidance, t.state, t.city,
               t.mob1, t.mob2, t.mob3, t.tel1, t.tel2, t.tel3,
               t.act, t.nextcall, t.dateupdate, t.timeupdate
        FROM tel_db t
        WHERE t.dakheli = ? AND t.`group` = ?
        ORDER BY t.id DESC
    ');
    $stmt->execute([$dakheli, $groupId]);

    $safeDakheli = preg_replace('/[^0-9]/', '', $dakheli);
    $filename = 'operator-group-calls-' . $safeDakheli . '-' . (int)$groupId . '-' . date('Y-m-d-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ردیف', 'اپراتور', 'داخلی', 'گروه تماس', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر', 'موبایل ۱', 'موبایل ۲', 'موبایل ۳', 'تلفن ۱', 'تلفن ۲', 'تلفن ۳', 'وضعیت', 'تماس بعدی', 'تاریخ آخرین تغییر', 'زمان آخرین تغییر']);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'], $operatorName, $dakheli, $groupName, $row['fname'], $row['lname'],
            $row['guidance'], $row['state'], $row['city'], $row['mob1'], $row['mob2'], $row['mob3'],
            $row['tel1'], $row['tel2'], $row['tel3'], operatorCallStatusLabel((int)$row['act']),
            $row['nextcall'], $row['dateupdate'], $row['timeupdate'],
        ]);
    }
    fclose($output);
    exit;
}

if ($action !== 'list') {
    operatorCallJson(['success' => false, 'message' => 'عملیات درخواستی معتبر نیست.'], 400);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$countStmt = $conn->prepare('SELECT COUNT(*) FROM tel_db WHERE dakheli = ? AND `group` = ?');
$countStmt->execute([$dakheli, $groupId]);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare('
    SELECT id, fname, lname, guidance, state, city, mob1, act, nextcall, dateupdate, timeupdate
    FROM tel_db
    WHERE dakheli = ? AND `group` = ?
    ORDER BY id DESC
    LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$stmt->execute([$dakheli, $groupId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as &$row) {
    $row['id'] = (int)$row['id'];
    $row['status_label'] = operatorCallStatusLabel((int)$row['act']);
    unset($row['act']);
}
unset($row);

operatorCallJson([
    'success' => true,
    'operator_name' => $operatorName,
    'dakheli' => $dakheli,
    'group_id' => (int)$groupId,
    'group_name' => (string)$groupName,
    'rows' => $rows,
    'pagination' => ['page' => $page, 'total_pages' => $totalPages, 'total' => $total, 'per_page' => $perPage],
]);
