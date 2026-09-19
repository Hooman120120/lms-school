<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../dbconfig.php';
require_once '../../inc/function_login.php';
require_once '../../inc/function.php';
require_once '../../inc/jdf.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['admin'])) {
    die('دسترسی غیرمجاز');
}

$username = $_SESSION['admin']['user'];
$stmt = $conn->prepare("SELECT idadmin, adminrole, permission_calendar FROM dbadmin WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$adminId = $admin['idadmin'];
$adminRole = $admin['adminrole'];

$permission_calendar = $admin['permission_calendar'];
// ----------------------------------------------------------------------
// Helper functions (shared across export types)
// ----------------------------------------------------------------------

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
        53 => 'معاونت فروش', 54 => 'معاونت فنی'
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

function jalaliToGregorianDate($jalaliDate) {
    if (empty($jalaliDate)) return null;
    $parts = explode('/', $jalaliDate);
    if (count($parts) != 3) return null;
    list($jy, $jm, $jd) = $parts;
    $greg = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
    return sprintf("%04d-%02d-%02d", $greg[0], $greg[1], $greg[2]);
}

// ----------------------------------------------------------------------
// Export type: calendar-history
// ----------------------------------------------------------------------
if (isset($_GET['calendar-history']) || isset($_POST['calendar-history'])) {

    // Receive POST data
    $startJalali = $_POST['start_date'] ?? '';
    $endJalali = $_POST['end_date'] ?? '';
    $eventType = $_POST['event_type'] ?? 'admin';
    $selectedRoles = json_decode($_POST['selected_roles'] ?? '[]', true);
    $selectedUsers = json_decode($_POST['selected_users'] ?? '[]', true);
    $selectedCourses = json_decode($_POST['selected_courses'] ?? '[]', true);
    $isAllAdmins = ($_POST['is_all_admins'] ?? '0') == '1';

    $startGreg = $startJalali ? jalaliToGregorianDate($startJalali) : null;
    $endGreg = $endJalali ? jalaliToGregorianDate($endJalali) : null;

    // Base query
    if ($eventType === 'both') {
        $sql = "SELECT e.* FROM calendar_events e WHERE e.target_type IN ('admin', 'user','both')";
        $params = [];
    } else {
        $sql = "SELECT e.* FROM calendar_events e WHERE e.target_type = ?";
        $params = [$eventType];
    }

    // Date range filters
    if ($startGreg) {
        $sql .= " AND e.start_date >= ?";
        $params[] = $startGreg . " 00:00:00";
    }
    if ($endGreg) {
        $sql .= " AND e.start_date <= ?";
        $params[] = $endGreg . " 23:59:59";
    }

    // Apply event‑specific filters (no permission check)
    if ($eventType === 'admin') {
        // Audience filter (if roles or specific admins are selected)
        if (!$isAllAdmins && (count($selectedRoles) > 0 || count($selectedUsers) > 0)) {
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
    } else { // user events
        if (!empty($selectedCourses)) {
            $courseConditions = [];
            foreach ($selectedCourses as $cid) {
                $courseConditions[] = "EXISTS (SELECT 1 FROM calendar_course_targets ct WHERE ct.event_id = e.id AND ct.course_id = ?)";
                $params[] = $cid;
            }
            $sql .= " AND (" . implode(" OR ", $courseConditions) . ")";
        }
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);

    // Headers (including new columns)
    $headers = ['عنوان', 'توضیحات', 'تاریخ شروع (شمسی)', 'تاریخ پایان (شمسی)', 'مخاطبان', 'نوع رویداد', 'نمایش برای دانش‌آموزان'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $col++;
    }

    $row = 2;
    foreach ($events as $event) {
        $start_j = gregorian_to_jalali(substr($event['start_date'],0,4), substr($event['start_date'],5,2), substr($event['start_date'],8,2));
        $jalali_start = sprintf("%04d/%02d/%02d %s", $start_j[0], $start_j[1], $start_j[2], date('H:i', strtotime($event['start_date'])));
        $jalali_end = '';
        if ($event['end_date']) {
            $end_j = gregorian_to_jalali(substr($event['end_date'],0,4), substr($event['end_date'],5,2), substr($event['end_date'],8,2));
            $jalali_end = sprintf("%04d/%02d/%02d %s", $end_j[0], $end_j[1], $end_j[2], date('H:i', strtotime($event['end_date'])));
        }

        // Audience summary
        if ($event['target_type'] === 'admin') {
            $target_summary = getEventTargetSummary($event['id'], $conn);
        } else {
            $courseStmt = $conn->prepare("SELECT course_id FROM calendar_course_targets WHERE event_id = ?");
            $courseStmt->execute([$event['id']]);
            $courseIds = $courseStmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($courseIds)) {
                $target_summary = 'همه کاربران';
            } else {
                $in = str_repeat('?,', count($courseIds) - 1) . '?';
                $nameStmt = $conn->prepare("SELECT coursename FROM codtbl_course WHERE idcourse IN ($in)");
                $nameStmt->execute($courseIds);
                $courseNames = $nameStmt->fetchAll(PDO::FETCH_COLUMN);
                $target_summary = 'پایه‌ها: ' . implode('، ', $courseNames);
            }
        }

        // New columns
        $visibilityText = ($event['visibility'] ?? 0) == 1 ? 'مخفی' : 'قابل مشاهده';
        $eventTypeText = ($event['event_type'] ?? 0) == 1 ? 'عملی' : 'عادی';

        $sheet->setCellValue('A' . $row, $event['title']);
        $sheet->setCellValue('B' . $row, $event['description']);
        $sheet->setCellValue('C' . $row, $jalali_start);
        $sheet->setCellValue('D' . $row, $jalali_end);
        $sheet->setCellValue('E' . $row, $target_summary);
        $sheet->setCellValue('F' . $row, $eventTypeText);
        $sheet->setCellValue('G' . $row, $visibilityText);
        $row++;
    }

    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    $filename = 'calendar-history-' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

// ----------------------------------------------------------------------
// Future export types (copy the above block and change the condition)
// ----------------------------------------------------------------------
/*
if (isset($_GET['calendar-archive'])) {
    // different logic here
}
if (isset($_GET['calendar-summary'])) {
    // different logic here
}
*/

// If no valid export type, show error
die('نوع خروجی نامعتبر است.');
?>