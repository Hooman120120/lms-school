<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once '../../dbconfig.php';
require_once '../../inc/function_login.php';
require_once '../../inc/function.php';
require_once '../../inc/jdf.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit('دسترسی غیرمجاز');
}

$sessionRow = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($sessionRow['session_id'] ?? null)) {
    session_unset();
    http_response_code(401);
    exit('نشست کاربری معتبر نیست.');
}

$username = $_SESSION['admin']['user'];
$stmt = $conn->prepare("SELECT idadmin, adminrole FROM dbadmin WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    http_response_code(403);
    die('دسترسی غیرمجاز');
}

function normalizeDigits($value) {
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace(array_merge($fa, $ar), array_merge($en, $en), (string) $value);
}

function decodeIdList($raw) {
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? array_values(array_filter($decoded, static function ($v) {
        return $v !== '' && $v !== null;
    })) : [];
}

function getEventTargetSummary($eventId, $conn) {
    $roles = [];
    $roleStmt = $conn->prepare("SELECT adminrole FROM calendar_admin_role_targets WHERE event_id = ?");
    $roleStmt->execute([$eventId]);
    while ($row = $roleStmt->fetch(PDO::FETCH_ASSOC)) {
        $roles[] = $row['adminrole'];
    }

    $admins = [];
    $adminStmt = $conn->prepare("SELECT admin_id FROM calendar_admin_user_targets WHERE event_id = ?");
    $adminStmt->execute([$eventId]);
    while ($row = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
        $admins[] = $row['admin_id'];
    }

    if (empty($roles) && empty($admins)) {
        return 'همه ادمین‌ها';
    }

    $roleNames = [
        0 => 'مدیریت', 1 => 'پشتیبان', 2 => 'مدیرمیانی', 3 => t('role_teacher'),
        6 => 'کارشناس فروش', 51 => 'معاونت اجرایی', 52 => 'معاونت آموزشی',
        53 => 'معاونت فروش', 54 => 'معاونت فنی', 55 => 'معاونت مالی'
    ];
    $roleText = !empty($roles) ? 'نقش‌ها: ' . implode(', ', array_map(function($r) use ($roleNames) {
            return $roleNames[$r] ?? $r;
        }, $roles)) : '';

    $adminNames = [];
    if (!empty($admins)) {
        $in = str_repeat('?,', count($admins) - 1) . '?';
        $adminNameStmt = $conn->prepare("SELECT idadmin, fname, lname FROM dbadmin WHERE idadmin IN ($in)");
        $adminNameStmt->execute($admins);
        while ($a = $adminNameStmt->fetch(PDO::FETCH_OBJ)) {
            $adminNames[] = $a->fname . ' ' . $a->lname;
        }
    }
    $adminText = !empty($adminNames) ? 'ادمین‌ها: ' . implode(', ', $adminNames) : '';

    return trim($roleText . ($roleText && $adminText ? ' | ' : '') . $adminText);
}

function getEventCourseSummary($eventId, $conn) {
    $courseStmt = $conn->prepare("SELECT course_id FROM calendar_course_targets WHERE event_id = ?");
    $courseStmt->execute([$eventId]);
    $courseIds = $courseStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($courseIds)) {
        return 'همه کاربران';
    }
    $in = str_repeat('?,', count($courseIds) - 1) . '?';
    $nameStmt = $conn->prepare("SELECT coursename FROM codtbl_course WHERE idcourse IN ($in)");
    $nameStmt->execute($courseIds);
    $courseNames = $nameStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($courseNames)) {
        return 'همه کاربران';
    }
    return t('grade_pl') . ': ' . implode('، ', $courseNames);
}

function jalaliToGregorianDate($jalaliDate) {
    $jalaliDate = trim(normalizeDigits($jalaliDate));
    if ($jalaliDate === '') return null;
    $parts = preg_split('#[/\-\.]#', $jalaliDate);
    if (count($parts) != 3) return null;
    list($jy, $jm, $jd) = array_map('intval', $parts);
    if ($jy < 1 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) return null;
    $greg = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf("%04d-%02d-%02d", $greg[0], $greg[1], $greg[2]);
}

if (isset($_GET['calendar-history']) || isset($_POST['calendar-history'])) {

    $startJalali = $_POST['start_date'] ?? '';
    $endJalali = $_POST['end_date'] ?? '';
    $eventType = $_POST['event_type'] ?? 'admin';
    if (!in_array($eventType, ['admin', 'user', 'both'], true)) {
        $eventType = 'admin';
    }
    $selectedRoles = decodeIdList($_POST['selected_roles'] ?? '[]');
    $selectedUsers = decodeIdList($_POST['selected_users'] ?? '[]');
    $selectedCourses = decodeIdList($_POST['selected_courses'] ?? '[]');
    $isAllAdmins = ($_POST['is_all_admins'] ?? '0') == '1';

    $startGreg = jalaliToGregorianDate($startJalali);
    $endGreg = jalaliToGregorianDate($endJalali);
    if ($startGreg && $endGreg && $startGreg > $endGreg) {
        $swap = $startGreg;
        $startGreg = $endGreg;
        $endGreg = $swap;
    }

    if ($eventType === 'both') {
        $targetTypes = ['admin', 'user', 'both'];
    } elseif ($eventType === 'user') {
        $targetTypes = ['user', 'both'];
    } else {
        $targetTypes = ['admin', 'both'];
    }
    $placeholders = implode(',', array_fill(0, count($targetTypes), '?'));
    $sql = "SELECT e.* FROM calendar_events e WHERE e.target_type IN ($placeholders)";
    $params = $targetTypes;

    if ($startGreg) {
        $sql .= " AND e.start_date >= ?";
        $params[] = $startGreg . " 00:00:00";
    }
    if ($endGreg) {
        $sql .= " AND e.start_date <= ?";
        $params[] = $endGreg . " 23:59:59";
    }

    if ($eventType !== 'user' && !$isAllAdmins && (count($selectedRoles) > 0 || count($selectedUsers) > 0)) {
        $conditions = [];
        foreach ($selectedRoles as $role) {
            $conditions[] = "EXISTS (SELECT 1 FROM calendar_admin_role_targets rt WHERE rt.event_id = e.id AND rt.adminrole = ?)";
            $params[] = $role;
        }
        foreach ($selectedUsers as $uid) {
            $conditions[] = "EXISTS (SELECT 1 FROM calendar_admin_user_targets ut WHERE ut.event_id = e.id AND ut.admin_id = ?)";
            $params[] = $uid;
        }
        $sql .= " AND (" . implode(" OR ", $conditions) . ")";
    }

    if ($eventType !== 'admin' && count($selectedCourses) > 0) {
        $courseConditions = [];
        foreach ($selectedCourses as $cid) {
            $courseConditions[] = "EXISTS (SELECT 1 FROM calendar_course_targets ct WHERE ct.event_id = e.id AND ct.course_id = ?)";
            $params[] = $cid;
        }
        $sql .= " AND (" . implode(" OR ", $courseConditions) . ")";
    }

    $sql .= " ORDER BY e.start_date ASC, e.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);

    $headers = ['عنوان', 'توضیحات', 'تاریخ شروع (شمسی)', 'تاریخ پایان (شمسی)', 'مخاطبان', 'نوع رویداد', m('excel.student_visibility')];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $col++;
    }

    $jalaliStamp = function ($value) {
        if (empty($value) || strpos($value, '0000') === 0) {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }
        $j = gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        return sprintf("%04d/%02d/%02d %s", $j[0], $j[1], $j[2], date('H:i', $ts));
    };

    $row = 2;
    foreach ($events as $event) {
        $jalali_start = $jalaliStamp($event['start_date']);
        $jalali_end = $jalaliStamp($event['end_date']);

        if ($event['target_type'] === 'admin') {
            $target_summary = getEventTargetSummary($event['id'], $conn);
        } elseif ($event['target_type'] === 'user') {
            $target_summary = getEventCourseSummary($event['id'], $conn);
        } else {
            $target_summary = getEventTargetSummary($event['id'], $conn)
                . ' | ' . getEventCourseSummary($event['id'], $conn);
        }

        $visibilityText = ($event['visibility'] ?? 0) == 1 ? 'مخفی' : 'قابل مشاهده';
        $eventTypeText = ($event['event_type'] ?? 0) == 1 ? 'عملی' : 'عادی';

        $sheet->setCellValueExplicit('A' . $row, (string) $event['title'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B' . $row, trim(strip_tags((string) $event['description'])), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C' . $row, $jalali_start, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D' . $row, $jalali_end, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E' . $row, (string) $target_summary, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('F' . $row, $eventTypeText);
        $sheet->setCellValue('G' . $row, $visibilityText);
        $row++;
    }

    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->freezePane('A2');

    $writer = new Xlsx($spreadsheet);
    $filename = 'calendar-history-' . date('Ymd_His') . '.xlsx';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('X-Export-Rows: ' . count($events));
    $writer->save('php://output');
    exit;
}

http_response_code(400);
die('نوع خروجی نامعتبر است.');
?>