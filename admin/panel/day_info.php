<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

// Admin permission & identity
$stmt = $conn->prepare("SELECT idadmin, adminrole, permission_calendar FROM dbadmin WHERE username = ?");
$stmt->execute([$_SESSION['admin']['user']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$adminId      = $admin['idadmin'];
$adminRole    = $admin['adminrole'];
$permCalendar = $admin['permission_calendar'];

// Get Gregorian date from popup (YYYY-MM-DD)
$date_greg = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_greg)) {
    die('تاریخ نامعتبر');
}

// ---------- POST: Delete event (popup self-contained) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event' && $permCalendar == 1) {
    $eventId = $_POST['event_id'] ?? 0;
    if ($eventId) {
        // Clean up child records (same as main page)
        $conn->prepare("DELETE FROM calendar_course_targets WHERE event_id = ?")->execute([$eventId]);
        $conn->prepare("DELETE FROM calendar_admin_role_targets WHERE event_id = ?")->execute([$eventId]);
        $conn->prepare("DELETE FROM calendar_admin_user_targets WHERE event_id = ?")->execute([$eventId]);
        $conn->prepare("DELETE FROM calendar_events WHERE id = ?")->execute([$eventId]);
    }
    // Reload the same day view
    $redirect = strtok($_SERVER['REQUEST_URI'], '?') . '?date=' . urlencode($date_greg);
    header('Location: ' . $redirect);
    exit;
}

// Convert to Jalali for display
$jalali = gregorian_to_jalali((int)substr($date_greg,0,4), (int)substr($date_greg,5,2), (int)substr($date_greg,8,2));
$jalali_date_str = sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);

// Query events based on permission
if ($permCalendar == 1) {
    $sql = "SELECT e.*, COALESCE(g.color, '#B5B5B5') AS event_color
        FROM calendar_events e
        LEFT JOIN calendar_groups g ON e.calendar_groups_id = g.id
        WHERE DATE(e.start_date) = :day
           OR (e.end_date IS NOT NULL AND DATE(e.start_date) <= :day2 AND DATE(e.end_date) >= :day3)
        ORDER BY e.start_date";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':day' => $date_greg, ':day2' => $date_greg, ':day3' => $date_greg]);
} else {
    $sql = "SELECT DISTINCT e.*, COALESCE(g.color, '#B5B5B5') AS event_color
        FROM calendar_events e
        LEFT JOIN calendar_groups g ON e.calendar_groups_id = g.id
        LEFT JOIN calendar_admin_role_targets rt ON e.id = rt.event_id
        LEFT JOIN calendar_admin_user_targets ut ON e.id = ut.event_id
        WHERE e.target_type IN ('admin', 'both')
          AND (
              rt.adminrole = :role
              OR ut.admin_id = :uid
              OR (NOT EXISTS (SELECT 1 FROM calendar_admin_role_targets WHERE event_id = e.id)
                  AND NOT EXISTS (SELECT 1 FROM calendar_admin_user_targets WHERE event_id = e.id))
          )
          AND (DATE(e.start_date) = :day
               OR (e.end_date IS NOT NULL AND DATE(e.start_date) <= :day2 AND DATE(e.end_date) >= :day3))
        ORDER BY e.start_date";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':role' => $adminRole,
        ':uid'  => $adminId,
        ':day'  => $date_greg,
        ':day2' => $date_greg,
        ':day3' => $date_greg
    ]);
}
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: Get detailed targets (Admins & Courses)
function getEventTargetDetails($eventId, $targetType, $conn) {
    $adminSummary = '';
    $courseSummary = '';

    // 1. Admin Targets
    if ($targetType === 'admin' || $targetType === 'both') {
        $roles = [];
        $rStmt = $conn->prepare("SELECT adminrole FROM calendar_admin_role_targets WHERE event_id = ?");
        $rStmt->execute([$eventId]);
        while ($r = $rStmt->fetch(PDO::FETCH_ASSOC)) $roles[] = $r['adminrole'];

        $admins = [];
        $aStmt = $conn->prepare("SELECT admin_id FROM calendar_admin_user_targets WHERE event_id = ?");
        $aStmt->execute([$eventId]);
        while ($a = $aStmt->fetch(PDO::FETCH_ASSOC)) $admins[] = $a['admin_id'];

        if (empty($roles) && empty($admins)) {
            $adminSummary = 'همه ادمین‌ها';
        } else {
            $roleNames = [
                0 => 'مدیریت', 1 => 'پشتیبان', 2 => 'مدیرمیانی', 3 => 'استاد',
                6 => 'کارشناس فروش', 51 => 'معاونت اجرایی', 52 => 'معاونت آموزشی',
                53 => 'معاونت فروش', 54 => 'معاونت فنی'
            ];
            $roleText = !empty($roles) ? 'نقش‌ها: ' . implode('، ', array_map(function($r) use ($roleNames) {
                    return $roleNames[$r] ?? $r;
                }, $roles)) : '';

            $adminNames = [];
            if (!empty($admins)) {
                $in = str_repeat('?,', count($admins) - 1) . '?';
                $nStmt = $conn->prepare("SELECT fname, lname FROM dbadmin WHERE idadmin IN ($in)");
                $nStmt->execute($admins);
                while ($n = $nStmt->fetch(PDO::FETCH_OBJ)) $adminNames[] = $n->fname . ' ' . $n->lname;
            }
            $adminText = !empty($adminNames) ? 'ادمین‌ها: ' . implode('، ', $adminNames) : '';
            $adminSummary = trim($roleText . ($roleText && $adminText ? ' | ' : '') . $adminText);
        }
    }

    // 2. User/Course Targets
    if ($targetType === 'user' || $targetType === 'both') {
        $courses = [];
        $cStmt = $conn->prepare("SELECT course_id FROM calendar_course_targets WHERE event_id = ?");
        $cStmt->execute([$eventId]);
        while ($c = $cStmt->fetch(PDO::FETCH_ASSOC)) $courses[] = $c['course_id'];

        if (empty($courses)) {
            $courseSummary = 'همه کاربران';
        } else {
            // Using retCourse(1) to map course IDs to Names
            $allCourses = function_exists('retCourse') ? retCourse(1) : [];
            $courseNames = [];
            if ($allCourses) {
                foreach ($allCourses as $c) {
                    if (in_array($c->idcourse, $courses)) {
                        $courseNames[] = $c->coursename;
                    }
                }
            }
            $courseSummary = !empty($courseNames) ? implode('، ', $courseNames) : (count($courses) . ' پایه انتخابی');
        }
    }

    return [
        'admin' => $adminSummary,
        'course' => $courseSummary
    ];
}

// Helper: fetch active event options
function getEventOptions($eventId, $conn) {
    $opts = [];
    $stmt = $conn->prepare("SELECT events_option, needs_reason FROM active_event_options WHERE event_id = ?");
    $stmt->execute([$eventId]);
    while ($o = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $opts[] = $o;
    }
    return $opts;
}

// Build display array
$displayEvents = [];
foreach ($events as $event) {
    $startDT = new DateTime($event['start_date']);
    $endDT   = $event['end_date'] ? new DateTime($event['end_date']) : null;

    // Jalali Start - Separated
    $js = gregorian_to_jalali((int)$startDT->format('Y'), (int)$startDT->format('m'), (int)$startDT->format('d'));
    $startDateJalali = sprintf('%04d/%02d/%02d', $js[0], $js[1], $js[2]);
    $startTime = $startDT->format('H:i');

    // Jalali End - Separated
    if ($endDT) {
        $je = gregorian_to_jalali((int)$endDT->format('Y'), (int)$endDT->format('m'), (int)$endDT->format('d'));
        $endDateJalali = sprintf('%04d/%02d/%02d', $je[0], $je[1], $je[2]);
        $endTime = $endDT->format('H:i');
    } else {
        $endDateJalali = null;
        $endTime = null;
    }

    // Fetch Targets (Roles, Users AND Courses)
    $targetType = $event['target_type'] ?? 'admin';
    $targetDetails = ($permCalendar == 1) ? getEventTargetDetails($event['id'], $targetType, $conn) : ['admin' => '', 'course' => ''];

    // Short summary for the card badge
    $shortSummary = 'نامشخص';
    if ($permCalendar == 1) {
        if ($targetType === 'user') $shortSummary = 'کاربران (پایه‌ها)';
        elseif ($targetType === 'both') $shortSummary = 'ترکیبی (ادمین + پایه)';
        else $shortSummary = 'ادمین‌ها';
    }

    // Active options if event_type = 1
    $options = ($event['event_type'] == 1) ? getEventOptions($event['id'], $conn) : [];

    $displayEvents[] = [
        'id'                => $event['id'],
        'title'             => $event['title'],
        'description'       => $event['description'] ?? '',
        'personal_note'     => $event['personal_note'] ?? '',
        'color'             => $event['event_color'] ?? '#B5B5B5',
        'start_date_jalali' => $startDateJalali,
        'start_time'        => $startTime,
        'end_date_jalali'   => $endDateJalali,
        'end_time'          => $endTime,
        'target_type'       => $targetType,
        'admin_targets'     => $targetDetails['admin'],
        'course_targets'    => $targetDetails['course'],
        'short_summary'     => $shortSummary,
        'visibility'        => $event['visibility'] ?? 0,
        'event_type'        => $event['event_type'] ?? 0,
        'options'           => $options,
        'full_start'        => $startDT,
        'full_end'          => $endDT,
        'can_edit'          => ($permCalendar == 1)
    ];
}

// Sort by start time
usort($displayEvents, function($a, $b) {
    return $a['full_start'] <=> $b['full_start'];
});

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>جزئیات روز | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>

    <style>
        :root {
            --primary-color: #3b82f6;
            --bg-light: #f8fafc;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body.main-body {
            background-color: var(--bg-light);
            color: var(--text-main);
            font-family: system-ui, -apple-system, sans-serif;
        }

        .page-header-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-main);
            border-right: 4px solid var(--primary-color);
            padding-right: 12px;
        }

        /* استایل کارت‌های رویداد */
        .event-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.25s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.04);
            border-color: #cbd5e1;
        }

        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 5px;
            background-color: var(--event-color, var(--primary-color));
        }

        /* باکس تفکیک شده زمان و تاریخ */
        .datetime-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
        }

        .datetime-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        .datetime-label {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .datetime-values {
            display: flex;
            gap: 12px;
            color: #475569;
        }

        .datetime-values span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .audience-badge {
            background-color: #eff6ff;
            color: #1e40af;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 6px;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }

        /* کنترل‌های فیلتر و جستجو */
        .search-wrapper .form-control {
            border-radius: 10px;
            padding: 10px 40px 10px 12px;
            border-color: var(--border-color);
        }

        .search-wrapper .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .custom-select-modern {
            border-radius: 10px;
            border-color: var(--border-color);
            padding: 10px;
        }

        /* کشوی سایدبار جزئیات */
        .offcanvas-modern {
            border-top-left-radius: 20px;
            border-bottom-left-radius: 20px;
            box-shadow: -10px 0 30px rgba(0,0,0,0.05);
        }

        .detail-item {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #f1f5f9;
        }

        .detail-item-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: block;
        }

        .detail-item-value {
            font-weight: 600;
            color: var(--text-main);
        }

        /* پجینیشن مینیمال */
        .pagination .page-link {
            border: none;
            color: var(--text-main);
            margin: 0 3px;
            border-radius: 8px;
            font-weight: 500;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <div class="main-content pt-4">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <h4 class="page-header-title mb-0">رویدادهای روز <span class="text-primary"><?php echo $jalali_date_str; ?></span></h4>
                <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm">تعداد کل: <?php echo count($displayEvents); ?></span>
            </div>

            <?php if (empty($displayEvents)): ?>
                <div class="text-center py-5">
                    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' fill='%2394a3b8' class='bi bi-calendar-x' viewBox='0 0 16 16'><path d='M6.146 7.146a.5.5 0 0 1 .708 0L8 8.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 9l1.147 1.146a.5.5 0 0 1-.708.708L8 9.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 9 6.146 7.854a.5.5 0 0 1 0-.708z'/><path d='M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z'/></svg>" class="mb-3 opacity-75" alt="No events">
                    <h5 class="text-muted">هیچ رویدادی برای این روز ثبت نشده است.</h5>
                </div>
            <?php else: ?>

                <div class="row g-3 mb-4 align-items-center">
                    <div class="col-md-5 col-lg-4 search-wrapper position-relative">
                        <i class="fa fa-search search-icon p-2"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="جستجو در عنوان، زمان یا مخاطبان...">
                    </div>
                    <div class="col-auto ms-auto d-flex align-items-center gap-2 col-2">
                        <label for="rowsPerPage" class="text-muted small json-keep text-nowrap mb-0">تعداد نمایش:</label>
                        <select id="rowsPerPage" class="form-select custom-select-modern">
                            <option value="10" selected>10</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3" id="eventsGrid">
                    <?php foreach ($displayEvents as $idx => $ev): ?>
                        <div class="col-md-6 col-lg-4 event-card-wrapper" data-event-id="<?= $ev['id'] ?>" data-json='<?= json_encode($ev, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS); ?>'>
                            <div class="event-card p-3 h-100 d-flex flex-column justify-content-between" style="--event-color: <?= htmlspecialchars($ev['color']) ?>;">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title text-truncate mb-0 fs-6 fw-bold pe-2"><?= htmlspecialchars($ev['title']) ?></h5>
                                        <span class="badge rounded-pill <?= $ev['event_type'] == 1 ? 'bg-danger' : 'bg-secondary' ?> bg-opacity-10 text-<?= $ev['event_type'] == 1 ? 'danger' : 'secondary' ?> small px-2 py-1">
                                            <?= $ev['event_type'] == 1 ? 'عملی' : 'عادی' ?>
                                        </span>
                                    </div>
                                    <p class="text-muted small text-truncate-2 mb-3"><?= htmlspecialchars($ev['description'] ?: 'بدون توضیحات اضافی...') ?></p>
                                </div>

                                <div>
                                    <div class="datetime-box mb-2">
                                        <div class="datetime-row <?= $ev['end_date_jalali'] ? 'mb-2 pb-2 border-bottom' : '' ?>">
                                            <span class="datetime-label text-success"><i class="fa fa-play-circle"></i> شروع:</span>
                                            <div class="datetime-values">
                                                <span><i class="fa fa-calendar-alt text-muted"></i> <?= htmlspecialchars($ev['start_date_jalali']) ?></span>
                                                <span><i class="fa fa-clock text-muted"></i> <?= htmlspecialchars($ev['start_time']) ?></span>
                                            </div>
                                        </div>

                                        <?php if ($ev['end_date_jalali']): ?>
                                            <div class="datetime-row">
                                                <span class="datetime-label text-danger"><i class="fa fa-stop-circle"></i> پایان:</span>
                                                <div class="datetime-values">
                                                    <span><i class="fa fa-calendar-check text-muted"></i> <?= htmlspecialchars($ev['end_date_jalali']) ?></span>
                                                    <span><i class="fa fa-clock text-muted"></i> <?= htmlspecialchars($ev['end_time']) ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($permCalendar == 1): ?>
                                        <div class="audience-badge w-100" title="مشاهده جزئیات در پنل">
                                            <?php
                                            $icon = 'fa-user-tie'; // Default for Admin
                                            if ($ev['target_type'] == 'user') $icon = 'fa-graduation-cap';
                                            elseif ($ev['target_type'] == 'both') $icon = 'fa-users-cog';
                                            ?>
                                            <i class="fa <?= $icon ?> me-1 opacity-70"></i>
                                            <?= htmlspecialchars($ev['short_summary']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top">
                    <div id="paginationInfo" class="text-muted small"></div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>

<div class="offcanvas offcanvas-end offcanvas-modern" tabindex="-1" id="eventDetailDrawer" aria-labelledby="eventDetailDrawerLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="eventDetailDrawerLabel">جزئیات کامل رویداد</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column justify-content-between" id="drawerBody">
    </div>
</div>
<form id="deleteEventForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_event">
    <input type="hidden" name="event_id" id="deleteEventId">
</form>
<script>
    function editFromPopup(eventId) {
        if (window.opener && !window.opener.closed && typeof window.opener.editEvent === 'function') {
            window.opener.editEvent(eventId);
            window.close();
        } else {
            alert('امکان ویرایش از این پنجره وجود ندارد. لطفاً از تقویم اصلی استفاده کنید.');
        }
    }

    function deleteFromPopup(eventId) {
        if (!confirm('آیا از حذف این رویداد اطمینان دارید؟')) return;
        document.getElementById('deleteEventId').value = eventId;
        document.getElementById('deleteEventForm').submit();
    }
    (function() {
        const gridContainer = document.getElementById('eventsGrid');
        if (!gridContainer) return;

        const cardWrappers = Array.from(gridContainer.querySelectorAll('.event-card-wrapper'));
        const searchInput = document.getElementById('searchInput');
        const rowsPerPageSelect = document.getElementById('rowsPerPage');
        const paginationUl = document.getElementById('pagination');
        const paginationInfo = document.getElementById('paginationInfo');
        const detailDrawerEl = document.getElementById('eventDetailDrawer');
        const bsOffcanvas = new bootstrap.Offcanvas(detailDrawerEl);

        let filteredCards = [...cardWrappers];
        let currentPage = 1;
        let rowsPerPage = parseInt(rowsPerPageSelect.value);

        // آپدیت تابع فیلتر برای جستجو در داده‌های پایه‌ها و ادمین‌ها
        function filterEvents() {
            const query = searchInput.value.trim().toLowerCase();

            filteredCards = cardWrappers.filter(wrapper => {
                const data = JSON.parse(wrapper.dataset.json);
                const title = (data.title || '').toLowerCase();
                const desc = (data.description || '').toLowerCase();
                const note = (data.personal_note || '').toLowerCase();
                const adminAudience = (data.admin_targets || '').toLowerCase();
                const courseAudience = (data.course_targets || '').toLowerCase();
                const startDate = (data.start_date_jalali || '').toLowerCase();
                const startTime = (data.start_time || '').toLowerCase();

                return title.includes(query) || desc.includes(query) || note.includes(query) ||
                    adminAudience.includes(query) || courseAudience.includes(query) ||
                    startDate.includes(query) || startTime.includes(query);
            });

            currentPage = 1;
            renderPage();
        }

        function renderPage() {
            const totalPages = Math.ceil(filteredCards.length / rowsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;

            cardWrappers.forEach(el => el.style.display = 'none');

            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const pageCards = filteredCards.slice(start, end);

            pageCards.forEach(el => el.style.display = 'block');

            renderPagination(totalPages);
            paginationInfo.textContent = ` نمایش ${pageCards.length} از ${filteredCards.length} رویداد یافته شده (صفحه ${currentPage} از ${totalPages})`;
        }

        function renderPagination(totalPages) {
            paginationUl.innerHTML = '';
            if (totalPages <= 1) return;

            const addPageItem = (page, text, isActive, isDisabled) => {
                const li = document.createElement('li');
                li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;
                const a = document.createElement('a');
                a.className = 'page-link shadow-sm';
                a.href = '#';
                a.innerHTML = text || page;
                if (!isDisabled) {
                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (page === 'prev') currentPage--;
                        else if (page === 'next') currentPage++;
                        else currentPage = page;
                        renderPage();
                    });
                }
                li.appendChild(a);
                return li;
            };

            paginationUl.appendChild(addPageItem('prev', '<i class="fa fa-angle-right"></i>', false, currentPage === 1));
            for (let p = 1; p <= totalPages; p++) {
                if (p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)) {
                    paginationUl.appendChild(addPageItem(p, p, p === currentPage, false));
                } else if (p === currentPage - 2 || p === currentPage + 2) {
                    const li = document.createElement('li');
                    li.className = 'page-item disabled';
                    li.innerHTML = '<span class="page-link">...</span>';
                    paginationUl.appendChild(li);
                }
            }
            paginationUl.appendChild(addPageItem('next', '<i class="fa fa-angle-left"></i>', false, currentPage === totalPages));
        }

        // نمایش اطلاعات کشویی با جزئیات کامل ادمین‌ها و پایه‌ها
        function showDetail(jsonString) {
            const ev = JSON.parse(jsonString);
            const drawerBody = document.getElementById('drawerBody');

            // قالب‌بندی گزینه‌های عملیاتی
            let optionsHtml = '';
            let personalNoteHtml = '';
            if (ev.options && ev.options.length > 0) {
                optionsHtml = `<div class="mt-3"><span class="detail-item-label">گزینه‌های عملیاتی:</span><div class="d-flex flex-wrap gap-1">`;
                ev.options.forEach(opt => {
                    optionsHtml += `<span class="badge bg-light text-dark border p-2">${opt.events_option} ${opt.needs_reason ? '<span class="badge bg-warning text-dark ms-1">نیازمند دلیل</span>':''}</span>`;
                });
                optionsHtml += `</div></div>`;
            }
            if (ev.personal_note) {
                personalNoteHtml = `
        <div class="detail-item mt-2">
            <span class="detail-item-label text-warning"><i class="fa fa-sticky-note me-1"></i> یادداشت شخصی:</span>
            <p class="mb-0 text-secondary small" style="white-space: pre-line;">${ev.personal_note}</p>
        </div>
    `;
            }

            // قالب‌بندی هدف‌ها (ادمین‌ها و پایه‌ها)
            let targetsHtml = '';
            if (ev.target_type === 'admin' || ev.target_type === 'both') {
                targetsHtml += `
                    <div class="detail-item mt-2">
                        <span class="detail-item-label text-primary"><i class="fa fa-user-tie"></i> مخاطبان ادمین:</span>
                        <span class="detail-item-value text-muted small" style="line-height: 1.6;">${ev.admin_targets || 'همه ادمین‌ها'}</span>
                    </div>
                `;
            }
            if (ev.target_type === 'user' || ev.target_type === 'both') {
                targetsHtml += `
                    <div class="detail-item mt-2">
                        <span class="detail-item-label text-info"><i class="fa fa-graduation-cap"></i> مخاطبان کاربر (پایه‌ها):</span>
                        <span class="detail-item-value text-muted small" style="line-height: 1.6;">${ev.course_targets || 'همه کاربران'}</span>
                    </div>
                `;
            }

            drawerBody.innerHTML = `
                <div class="overflow-auto pe-1 flex-grow-1">
                    <div class="p-2 mb-3 rounded text-white" style="background-color: ${ev.color || '#3b82f6'}">
                        <small class="opacity-75 d-block mb-1">عنوان رویداد</small>
                        <h6 class="fw-bold mb-0">${ev.title}</h6>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6"><div class="detail-item h-100"><span class="detail-item-label text-success">تاریخ شروع:</span><span class="detail-item-value small">${ev.start_date_jalali}</span></div></div>
                        <div class="col-6"><div class="detail-item h-100"><span class="detail-item-label text-success">ساعت شروع:</span><span class="detail-item-value small">${ev.start_time}</span></div></div>
                        <div class="col-6"><div class="detail-item h-100"><span class="detail-item-label text-danger">تاریخ پایان:</span><span class="detail-item-value small">${ev.end_date_jalali || 'ندارد'}</span></div></div>
                        <div class="col-6"><div class="detail-item h-100"><span class="detail-item-label text-danger">ساعت پایان:</span><span class="detail-item-value small">${ev.end_time || 'ندارد'}</span></div></div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6"><div class="detail-item h-100"><span class="detail-item-label">نوع رویداد:</span><span class="detail-item-value">${ev.event_type == 1 ? 'عملی' : 'عادی'}</span></div></div>
                        <div class="col-6"><div class="detail-item h-100"><span class="detail-item-label">نمایش دانش‌آموزان:</span><span class="detail-item-value">${ev.visibility == 1 ? '⚠️ مخفی' : '👁️ نمایان'}</span></div></div>
                    </div>

                    ${targetsHtml}

                    <div class="detail-item mt-2">
                        <span class="detail-item-label">توضیحات تکمیلی:</span>
                        <p class="mb-0 text-secondary small" style="white-space: pre-line;">${ev.description || 'توضیحاتی ثبت نشده است.'}</p>
                    </div>
                    ${personalNoteHtml}

                    ${optionsHtml}
                </div>
                               <div class="pt-3 border-top d-flex gap-2">
                    ${ev.can_edit ? `
                        <button type="button" class="btn btn-sm btn-warning flex-fill" onclick="editFromPopup(${ev.id})">
                            <i class="fa fa-edit"></i> ویرایش
                        </button>
                        <button type="button" class="btn btn-sm btn-danger flex-fill" onclick="deleteFromPopup(${ev.id})">
                            <i class="fa fa-trash"></i> حذف
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-sm btn-secondary flex-fill" data-bs-dismiss="offcanvas">بستن</button>
                </div>
            `;
            bsOffcanvas.show();
        }

        gridContainer.addEventListener('click', function(e) {
            const card = e.target.closest('.event-card-wrapper');
            if (card) showDetail(card.dataset.json);
        });

        searchInput.addEventListener('input', filterEvents);
        rowsPerPageSelect.addEventListener('change', function() {
            rowsPerPage = parseInt(this.value);
            currentPage = 1;
            renderPage();
        });

        filterEvents();
    })();
</script>
</body>
</html>