<?php
$stmt = $conn->prepare("SELECT idadmin, adminrole, permission_calendar FROM dbadmin WHERE username = ?");
$stmt->execute([$_SESSION['admin']['user']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$adminId = $admin['idadmin'];
$adminRole = $admin['adminrole'];
$permission_calendar = $admin['permission_calendar'];

function debug_log($message, $data = null) {
    $logFile = __DIR__ . '/calendar_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= " - " . print_r($data, true);
    }
    $logEntry .= PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
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

    // If no roles and no specific admins, it's an "all admins" event
    if (empty($roles) && empty($admins)) {
        return 'همه ادمین‌ها';
    }

    $roleNames = [
        0 => 'مدیریت', 1 => 'پشتیبان', 2 => 'مدیرمیانی', 3 => 'استاد',
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

function jalaliToGregorian($jalaliDatetime) {
    if (empty($jalaliDatetime)) return null;
    $parts = explode(' ', trim($jalaliDatetime));
    $datePart = $parts[0];
    $timePart = isset($parts[1]) ? $parts[1] : '00:00';

    $dateParts = preg_split('/[-\/]/', $datePart);
    if (count($dateParts) != 3) return null;
    list($jy, $jm, $jd) = $dateParts;
    $greg = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
    $gregDate = sprintf("%04d-%02d-%02d", $greg[0], $greg[1], $greg[2]);
    return $gregDate . ' ' . $timePart . ':00';
}

// ---------- AJAX: GET events (for FullCalendar) ----------
if (isset($_GET['get_events']) && $_GET['get_events'] == 1) {
    header('Content-Type: application/json');

    // Show both admin and user events to the admin calendar
    if ($permission_calendar == 1) {
        // Full access: show all events (no filtering)
        $sql = "SELECT DISTINCT e.* FROM calendar_events e";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        // Normal role-based filtering – only admin events that target this admin
        $sql = "
            SELECT DISTINCT e.*
            FROM calendar_events e
            LEFT JOIN calendar_admin_role_targets rt ON e.id = rt.event_id
            LEFT JOIN calendar_admin_user_targets ut ON e.id = ut.event_id
            WHERE e.target_type IN ('admin', 'both')
              AND (
                  rt.adminrole = ?
                  OR ut.admin_id = ?
                  OR (NOT EXISTS (SELECT 1 FROM calendar_admin_role_targets WHERE event_id = e.id)
                      AND NOT EXISTS (SELECT 1 FROM calendar_admin_user_targets WHERE event_id = e.id))
              )
            ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$adminRole, $adminId]);
    }
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $start_j = gregorian_to_jalali(substr($row['start_date'],0,4), substr($row['start_date'],5,2), substr($row['start_date'],8,2));
        $jalali_start = sprintf("%04d/%02d/%02d %s", $start_j[0], $start_j[1], $start_j[2], date('H:i', strtotime($row['start_date'])));
        $jalali_end = '';
        if ($row['end_date']) {
            $end_j = gregorian_to_jalali(substr($row['end_date'],0,4), substr($row['end_date'],5,2), substr($row['end_date'],8,2));
            $jalali_end = sprintf("%04d/%02d/%02d %s", $end_j[0], $end_j[1], $end_j[2], date('H:i', strtotime($row['end_date'])));
        }
        $target_summary = getEventTargetSummary($row['id'], $conn);
        // Get roles and specific admins for this event (needed for editing)
        $roles = [];
        $roleStmt = $conn->prepare("SELECT adminrole FROM calendar_admin_role_targets WHERE event_id = ?");
        $roleStmt->execute([$row['id']]);
        while ($r = $roleStmt->fetch(PDO::FETCH_ASSOC)) {
            $roles[] = $r['adminrole'];
        }
        $users = [];
        $userStmt = $conn->prepare("SELECT admin_id FROM calendar_admin_user_targets WHERE event_id = ?");
        $userStmt->execute([$row['id']]);
        while ($u = $userStmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $u['admin_id'];
        }
        $courses = [];
        $courseStmt = $conn->prepare("SELECT course_id FROM calendar_course_targets WHERE event_id = ?");
        $courseStmt->execute([$row['id']]);
        while ($c = $courseStmt->fetch(PDO::FETCH_ASSOC)) {
            $courses[] = (int)$c['course_id'];
        }

        $events[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'start' => date('Y-m-d\TH:i:s', strtotime($row['start_date'])),
            'end'   => $row['end_date'] ? date('Y-m-d\TH:i:s', strtotime($row['end_date'])) : null,
            'color' => $row['color'] ?? '#3788d8',
            'description' => $row['description'],
            'jalali_start' => $jalali_start,
            'jalali_end' => $jalali_end,
            'target_summary' => $target_summary,
            'target_type' => $row['target_type'],
            'admin_roles' => $roles,
            'admin_users' => $users,
            'course_targets' => $courses,
            'visibility' => $row['visibility'] ?? 0,
            'event_type'  => $row['event_type'] ?? 0
        ];
    }
    echo json_encode($events);
    exit;
}

// ---------- POST: Add event (redirect, no JSON) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event' && $permission_calendar == 1) {
    debug_log("add_event started", $_POST);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_jalali = $_POST['start_date'] ?? '';
    $end_jalali = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $color = !empty($_POST['color']) ? $_POST['color'] : '#3788d8';
    $target_group = $_POST['target_group'] ?? 'admin';
    $visibility = isset($_POST['visibility']) ? 1 : 0;
    $event_type = isset($_POST['event_type']) ? (int)$_POST['event_type'] : 0;

    $start_greg = jalaliToGregorian($start_jalali);
    $end_greg = jalaliToGregorian($end_jalali);

    if (empty($title) || !$start_greg) {
        debug_log("Validation failed", ['title' => $title, 'start_greg' => $start_greg]);
        $_SESSION['calendar_message'] = ['success' => false, 'message' => 'عنوان و تاریخ شروع معتبر اجباری هستند.'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
    INSERT INTO calendar_events 
    (title, description, start_date, end_date, color, creator_id, updated_by_id, target_type, visibility)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
        $stmt->execute([$title, $description, $start_greg, $end_greg, $color, $adminId, $adminId, $target_group, $visibility]);
        $eventId = $conn->lastInsertId();

        if ($target_group === 'admin' || $target_group === 'both') {
            $adminRoles = json_decode($_POST['admin_roles'] ?? '[]', true);
            $adminUsers = json_decode($_POST['admin_users'] ?? '[]', true);

            if (!empty($adminRoles)) {
                $roleStmt = $conn->prepare("INSERT INTO calendar_admin_role_targets (event_id, adminrole) VALUES (?, ?)");
                foreach ($adminRoles as $role) {
                    $roleStmt->execute([$eventId, $role]);
                }
            }
            if (!empty($adminUsers)) {
                $userStmt = $conn->prepare("INSERT INTO calendar_admin_user_targets (event_id, admin_id) VALUES (?, ?)");
                foreach ($adminUsers as $uid) {
                    $userStmt->execute([$eventId, $uid]);
                }
            }
        }

        if ($target_group === 'user' || $target_group === 'both') {
            $selectedCourses = json_decode($_POST['selected_courses'] ?? '[]', true);
            if (!empty($selectedCourses)) {
                $courseStmt = $conn->prepare("INSERT INTO calendar_course_targets (event_id, course_id) VALUES (?, ?)");
                foreach ($selectedCourses as $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) {
                        $courseStmt->execute([$eventId, $cid]);
                    }
                }
            }
        }
        $conn->commit();
        $_SESSION['calendar_message'] = ['success' => true, 'message' => 'رویداد با موفقیت اضافه شد.'];
    } catch (Exception $e) {
        debug_log("Exception caught", $e->getMessage());
        $conn->rollBack();
        $_SESSION['calendar_message'] = ['success' => false, 'message' => $e->getMessage()];
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ---------- POST: Update event ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_event' && $permission_calendar == 1) {
    $eventId = $_POST['event_id'] ?? 0;

    if (isset($_POST['start_greg']) && !empty($_POST['start_greg'])) {
        $start_greg = $_POST['start_greg'];
        $end_greg = !empty($_POST['end_greg']) ? $_POST['end_greg'] : null;
    } else {
        $start_jalali = $_POST['start_date'] ?? '';
        $end_jalali = $_POST['end_date'] ?? '';
        $start_greg = jalaliToGregorian($start_jalali);
        $end_greg = $end_jalali ? jalaliToGregorian($end_jalali) : null;
    }

    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $color = $_POST['color'] ?? '#3788d8';
    $target_group = $_POST['target_group'] ?? 'admin';
    $visibility = isset($_POST['visibility']) ? 1 : 0;
    $event_type = isset($_POST['event_type']) ? (int)$_POST['event_type'] : 0;
    if (!$eventId || !$start_greg) {
        if (isset($_POST['update_roles']) && $_POST['update_roles'] == 0) {
            // AJAX drag‑drop request
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص.']);
        } else {
            $_SESSION['calendar_message'] = ['success' => false, 'message' => 'اطلاعات ناقص.'];
            header('Location: ' . $_SERVER['PHP_SELF']);
        }
        exit;
    }

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE calendar_events SET title=?, description=?, start_date=?, end_date=?, color=?, updated_by_id=?, target_type=?, visibility=?, event_type=? WHERE id=?");
        $stmt->execute([$title, $description, $start_greg, $end_greg, $color, $adminId, $target_group, $visibility, $event_type, $eventId]);

        $isAjaxDragDrop = (isset($_POST['update_roles']) && $_POST['update_roles'] == 0);

        if (!$isAjaxDragDrop) {

            $conn->prepare("DELETE FROM calendar_admin_role_targets WHERE event_id = ?")->execute([$eventId]);
            $conn->prepare("DELETE FROM calendar_admin_user_targets WHERE event_id = ?")->execute([$eventId]);
            $conn->prepare("DELETE FROM calendar_course_targets WHERE event_id = ?")->execute([$eventId]);

            if ($target_group === 'admin' || $target_group === 'both') {
                $adminRoles = json_decode($_POST['admin_roles'] ?? '[]', true);
                $adminUsers = json_decode($_POST['admin_users'] ?? '[]', true);

                if (!empty($adminRoles)) {
                    $roleStmt = $conn->prepare("INSERT INTO calendar_admin_role_targets (event_id, adminrole) VALUES (?, ?)");
                    foreach ($adminRoles as $role) {
                        $roleStmt->execute([$eventId, $role]);
                    }
                }
                if (!empty($adminUsers)) {
                    $userStmt = $conn->prepare("INSERT INTO calendar_admin_user_targets (event_id, admin_id) VALUES (?, ?)");
                    foreach ($adminUsers as $uid) {
                        $userStmt->execute([$eventId, $uid]);
                    }
                }
            }

            if ($target_group === 'user' || $target_group === 'both') {
                $selectedCourses = json_decode($_POST['selected_courses'] ?? '[]', true);
                if (!empty($selectedCourses)) {
                    $courseStmt = $conn->prepare("INSERT INTO calendar_course_targets (event_id, course_id) VALUES (?, ?)");
                    foreach ($selectedCourses as $cid) {
                        $cid = (int)$cid;
                        if ($cid > 0) {
                            $courseStmt->execute([$eventId, $cid]);
                        }
                    }
                }
            }
        }

        $conn->commit();

        if ($isAjaxDragDrop) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            $_SESSION['calendar_message'] = ['success' => true, 'message' => 'رویداد با موفقیت به‌روزرسانی شد.'];
            header('Location: ' . $_SERVER['PHP_SELF']);
        }
    } catch (Exception $e) {
        $conn->rollBack();
        if ($isAjaxDragDrop) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } else {
            $_SESSION['calendar_message'] = ['success' => false, 'message' => $e->getMessage()];
            header('Location: ' . $_SERVER['PHP_SELF']);
        }
    }
    exit;
}
// ---------- POST: Delete event (redirect, no JSON) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event' && $permission_calendar == 1) {
    $eventId = $_POST['event_id'] ?? 0;
    if (!$eventId) {
        $_SESSION['calendar_message'] = ['success' => false, 'message' => 'شناسه رویداد نامعتبر.'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    try {
        // Also delete related targets (optional, foreign key cascade may handle it)
        $conn->prepare("DELETE FROM calendar_course_targets WHERE event_id = ?")->execute([$eventId]);
        $conn->prepare("DELETE FROM calendar_admin_role_targets WHERE event_id = ?")->execute([$eventId]);
        $conn->prepare("DELETE FROM calendar_admin_user_targets WHERE event_id = ?")->execute([$eventId]);
        $conn->prepare("DELETE FROM calendar_events WHERE id = ?")->execute([$eventId]);
        $_SESSION['calendar_message'] = ['success' => true, 'message' => 'رویداد حذف شد.'];
    } catch (Exception $e) {
        $_SESSION['calendar_message'] = ['success' => false, 'message' => $e->getMessage()];
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
function renderCalendar() {
    global $permission_calendar, $conn;
    ?>
    <style>

    </style>
    <?php if ($permission_calendar == 1) { ?>
        <div class="mb-3 mt-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                + افزودن رویداد جدید
            </button>
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                📥 خروجی اکسل
            </button>
        </div>
    <?php } ?>
    <div id="calendar"></div>
    <!-- Modal افزودن/ویرایش رویداد (reused for both) -->
    <?php if ($permission_calendar == 1) { ?>
        <div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEventModalLabel">افزودن رویداد جدید</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addEventForm" method="POST">
                            <input type="hidden" id="editEventId" value="">
                            <input type="hidden" name="action" id="formAction" value="add_event">
                            <input type="hidden" name="event_id" id="formEventId" value="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">عنوان *</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="color" class="form-label">رنگ</label>
                                    <input type="color" class="form-control" id="color" name="color" value="#3788d8" style="height:38px;">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">تاریخ و ساعت شروع * (شمسی)</label>
                                    <input type="text" class="form-control" id="start_date" name="start_date" placeholder="۱۴۰۴/۰۱/۰۱ ۱۲:۰۰" autocomplete="off" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">تاریخ و ساعت پایان (شمسی)</label>
                                    <input type="text" class="form-control" id="end_date" name="end_date" placeholder="۱۴۰۴/۰۱/۰۱ ۱۲:۰۰" autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="targetTypeSelect" class="form-label">کاربر مورد نظر</label>
                                <select class="form-select" id="targetTypeSelect" name="target_group">
                                    <option value="admin">ادمین‌ها</option>
                                    <option value="user">کاربران</option>
                                    <option value="both">ترکیبی </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="visibilitySelect" class="form-label">نمایش برای دانش‌آموزان</label>
                                <select class="form-select" id="visibilitySelect" name="visibility">
                                    <option value="0">نمایش داده شود (همه ببینند)</option>
                                    <option value="1">مخفی باشد (فقط ادمین‌ها ببینند)</option>
                                </select>
                                <small class="text-muted">در صورت انتخاب "مخفی باشد"، رویداد در تقویم دانش‌آموزان دیده نمی‌شود.</small>
                            </div>
                            <div class="mb-3">
                                <label for="eventTypeSelect" class="form-label">نوع رویداد</label>
                                <select class="form-select" id="eventTypeSelect" name="event_type">
                                    <option value="0">عادی</option>
                                    <option value="1">عملی</option>
                                </select>
                            </div>
                            <div id="adminTargetsContainer">
                                <input type="hidden" id="selectedAdminRoles" name="admin_roles" value="">
                                <input type="hidden" id="selectedAdminUsers" name="admin_users" value="">
                                <input type="hidden" id="isAllAdmins" name="is_all_admins" value="0">

                                <div class="mb-3">
                                    <label class="form-label">نقش‌های ادمین (چندگانه)</label>
                                    <div class="d-flex flex-wrap gap-2" id="adminRolesList">
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="all" >همه</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="0">مدیریت</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="1">پشتیبان</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="2">مدیرمیانی</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="3">استاد</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="6">کارشناس فروش</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="51">معاونت اجرایی</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="52">معاونت آموزشی</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="53">معاونت فروش</div>
                                        <div class="role-pill btn btn-outline-primary btn-sm" data-role="54">معاونت فنی</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">ادمین‌های خاص (چندگانه)</label>
                                    <div class="d-flex flex-wrap gap-2" id="adminUsersList">
                                        <?php
                                        $roleNames = [
                                            0  => 'مدیریت',
                                            1  => 'پشتیبان',
                                            2  => 'مدیرمیانی',
                                            3  => 'استاد',
                                            6  => 'کارشناس فروش',
                                            51 => 'معاونت اجرایی',
                                            52 => 'معاونت آموزشی',
                                            53 => 'معاونت فروش',
                                            54 => 'معاونت فنی',
                                        ];
                                        $stmt = $conn->query("SELECT idadmin, username, adminrole FROM dbadmin WHERE adminactive = 1 ORDER BY fname");
                                        while ($admin = $stmt->fetch(PDO::FETCH_OBJ)):
                                            $roleId = $admin->adminrole;
                                            $roleName = $roleNames[$roleId] ?? ('نقش ' . $roleId);
                                            ?>
                                            <div class="admin-pill btn btn-outline-secondary btn-sm" data-admin-id="<?= $admin->idadmin ?>" data-role="<?= $roleId ?>">
                                                <?= htmlspecialchars($admin->username) ?> (<?= htmlspecialchars($roleName) ?>)
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">انتخاب شده‌ها:</label>
                                    <div id="selectedTargetsSummary" class="text-muted small" style="background:#f5f5f5; padding:8px; border-radius:6px;">هیچکدام</div>
                                </div>
                            </div>

                            <div id="userTargetsContainer" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label">پایه‌های هدف (چندگانه)</label>
                                    <div class="d-flex flex-wrap gap-2" id="coursePillsList">
                                        <?php
                                        $courses = retCourse(1);
                                        if ($courses && count($courses) > 0):
                                            foreach ($courses as $course): ?>
                                                <div class="course-pill btn btn-outline-primary btn-sm" data-course-id="<?= $course->idcourse ?>">
                                                    <?= htmlspecialchars($course->coursename) ?>
                                                </div>
                                            <?php endforeach; endif; ?>
                                    </div>
                                    <small class="text-muted">در صورت عدم انتخاب، رویداد برای همه کاربران نمایش داده می‌شود.</small>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">پایه‌های انتخاب شده:</label>
                                    <div id="selectedCoursesSummary" class="text-muted small" style="background:#f5f5f5; padding:8px; border-radius:6px;">هیچکدام (همه کاربران)</div>
                                </div>
                                <input type="hidden" id="selectedCourses" name="selected_courses" value="">
                            </div>
                            <div class="modal-footer px-0 pb-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                <button type="submit" class="btn btn-primary">ذخیره</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal خروجی به اکسل با فیلتر مخاطبان -->
        <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">خروجی رویدادها به اکسل</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="exportForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="export_start" class="form-label">تاریخ شروع (شمسی)</label>
                                    <input type="text" class="form-control" id="export_start" placeholder="۱۴۰۴/۰۱/۰۱" autocomplete="off">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="export_end" class="form-label">تاریخ پایان (شمسی)</label>
                                    <input type="text" class="form-control" id="export_end" placeholder="۱۴۰۴/۰۱/۰۱" autocomplete="off">
                                </div>
                            </div>
                            <hr>
                            <h6 class="main-content-label mb-1">فیلتر بر اساس مخاطبان</h6>
                            <p class="text-muted card-sub-title">(اختیاری – در صورت عدم انتخاب، تمام رویدادهای بازه نمایش داده می‌شوند)</p>

                            <div id="exportTargetsContainer">
                                <input type="hidden" id="exportSelectedRoles" value="">
                                <input type="hidden" id="exportSelectedUsers" value="">
                                <input type="hidden" id="exportIsAllAdmins" value="0">
                                <input type="hidden" id="exportSelectedCourses" value="">

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="export_event_type" class="form-label">نوع رویداد</label>
                                        <select class="form-select" id="export_event_type">
                                            <option value="admin">رویدادهای ادمین‌ها</option>
                                            <option value="user">رویدادهای کاربران</option>
                                            <option value="both">همه رویدادها </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Admin filters (visible when event_type = admin) -->
                                <div id="exportAdminFilters">
                                    <div class="mb-3">
                                        <label class="form-label">نقش‌های ادمین</label>
                                        <div class="d-flex flex-wrap gap-2" id="exportRolesList">
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="all">همه</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="0">مدیریت</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="1">پشتیبان</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="2">مدیرمیانی</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="3">استاد</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="6">کارشناس فروش</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="51">معاونت اجرایی</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="52">معاونت آموزشی</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="53">معاونت فروش</div>
                                            <div class="export-role-pill btn btn-outline-primary btn-sm" data-role="54">معاونت فنی</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">ادمین‌های خاص</label>
                                        <div class="d-flex flex-wrap gap-2" id="exportUsersList">
                                            <?php
                                            $roleNames = [
                                                0 => 'مدیریت', 1 => 'پشتیبان', 2 => 'مدیرمیانی', 3 => 'استاد',
                                                6 => 'کارشناس فروش', 51 => 'معاونت اجرایی', 52 => 'معاونت آموزشی',
                                                53 => 'معاونت فروش', 54 => 'معاونت فنی'
                                            ];
                                            $stmt = $conn->query("SELECT idadmin, username, adminrole FROM dbadmin WHERE adminactive = 1 ORDER BY fname");
                                            while ($admin = $stmt->fetch(PDO::FETCH_OBJ)):
                                                $roleId = $admin->adminrole;
                                                $roleName = $roleNames[$roleId] ?? ('نقش ' . $roleId);
                                                ?>
                                                <div class="export-admin-pill btn btn-outline-secondary btn-sm" data-admin-id="<?= $admin->idadmin ?>" data-role="<?= $roleId ?>">
                                                    <?= htmlspecialchars($admin->username) ?> (<?= htmlspecialchars($roleName) ?>)
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- User filters (visible when event_type = user) -->
                                <div id="exportUserFilters" style="display:none;">
                                    <div class="mb-3">
                                        <label class="form-label">پایه‌های هدف (چندگانه)</label>
                                        <div class="d-flex flex-wrap gap-2" id="exportCoursePillsList">
                                            <?php
                                            $courses = retCourse(1);
                                            if ($courses && count($courses) > 0):
                                                foreach ($courses as $course): ?>
                                                    <div class="export-course-pill btn btn-outline-primary btn-sm" data-course-id="<?= $course->idcourse ?>">
                                                        <?= htmlspecialchars($course->coursename) ?>
                                                    </div>
                                                <?php endforeach; endif; ?>
                                        </div>
                                        <small class="text-muted">در صورت عدم انتخاب، رویدادهای همه پایه‌ها نمایش داده می‌شوند.</small>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">انتخاب شده‌ها:</label>
                                    <div id="exportSelectedSummary" class="text-muted small" style="background:#f5f5f5; padding:8px; border-radius:6px;">هیچکدام (تمام رویدادهای بازه)</div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-success" id="doExportBtn">خروجی گرفتن</button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
    <!-- Modal نمایش جزئیات رویداد -->
    <div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات رویداد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="eventDetailContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    <?php if ($permission_calendar == 1) { ?>
                        <button type="button" class="btn btn-warning" id="editEventBtn">ویرایش</button>
                        <button type="button" class="btn btn-danger" id="deleteEventBtn">حذف</button>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
<?php } ?>
   <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ------------------------------ DOM elements ------------------------------
        const calendarEl = document.getElementById('calendar');
        const targetTypeSelect = document.getElementById('targetTypeSelect');
        const adminContainer = document.getElementById('adminTargetsContainer');
        const userContainer = document.getElementById('userTargetsContainer');
        const addEventModal = document.getElementById('addEventModal');
        const eventDetailModal = document.getElementById('eventDetailModal');
        const form = document.getElementById('addEventForm');
        const hasCalendarPermission = <?php echo $permission_calendar == 1 ? 'true' : 'false'; ?>;
        const selectedCoursesInput = document.getElementById('selectedCourses');
        const selectedCoursesSummary = document.getElementById('selectedCoursesSummary');
        let coursePills = [];
        var courseNameMap = {};
        <?php
        $allCourses = retCourse(1);
        if ($allCourses) {
            foreach ($allCourses as $c) {
                echo "courseNameMap[{$c->idcourse}] = " . json_encode(htmlspecialchars($c->coursename, ENT_QUOTES)) . ";";
            }
        }
        ?>
        function updateCoursesSummary() {
            // Guard: only run if the summary element exists
            if (!selectedCoursesSummary || !selectedCoursesInput) return;
            const selectedIds = coursePills.filter(pill => pill.classList.contains('active')).map(pill => pill.getAttribute('data-course-id'));
            if (selectedIds.length === 0) {
                selectedCoursesSummary.innerText = 'هیچکدام (همه کاربران)';
                selectedCoursesInput.value = '[]';
            } else {
                const selectedNames = coursePills.filter(pill => pill.classList.contains('active')).map(pill => pill.innerText.trim());
                selectedCoursesSummary.innerText = 'پایه‌ها: ' + selectedNames.join(', ');
                selectedCoursesInput.value = JSON.stringify(selectedIds);
            }
        }

        function initCoursePills() {
            const coursePillsList = document.querySelector('#coursePillsList');
            if (!coursePillsList) return;  // ✅ exit silently if not present
            coursePills = Array.from(coursePillsList.querySelectorAll('.course-pill'));
            coursePills.forEach(pill => {
                pill.addEventListener('click', function(e) {
                    e.stopPropagation();
                    this.classList.toggle('active');
                    this.classList.toggle('btn-primary');
                    this.classList.toggle('btn-outline-primary');
                    updateCoursesSummary();
                });
            });
            updateCoursesSummary();
        }

// Only initialise if the container exists (i.e., user has permission)
        if (document.getElementById('userTargetsContainer')) {
            initCoursePills();
        }

        // Hidden flag for "all admins" mode (only if adminContainer exists)
        let isAllAdminsInput = null;
        if (adminContainer) {
            isAllAdminsInput = document.getElementById('isAllAdmins');
            if (!isAllAdminsInput) {
                isAllAdminsInput = document.createElement('input');
                isAllAdminsInput.type = 'hidden';
                isAllAdminsInput.id = 'isAllAdmins';
                isAllAdminsInput.value = '0';
                adminContainer.appendChild(isAllAdminsInput);
            }
        }

        // ------------------------------ Global variables ------------------------------
        let calendar = null;
        let rolePills = [];
        let adminPills = [];

        // ------------------------------ Helper functions (only run if pills exist) ------------------------------
        function updateTargetsSummary() {
            if (!adminContainer) return;
            const selectedRoles = rolePills.filter(p => p.classList.contains('active')).map(p => p.getAttribute('data-role'));
            const selectedAdmins = adminPills.filter(p => p.classList.contains('active')).map(p => p.getAttribute('data-admin-id'));
            const rolesField = document.getElementById('selectedAdminRoles');
            const usersField = document.getElementById('selectedAdminUsers');
            if (rolesField) rolesField.value = JSON.stringify(selectedRoles);
            if (usersField) usersField.value = JSON.stringify(selectedAdmins);
            const summaryDiv = document.getElementById('selectedTargetsSummary');
            if (summaryDiv) {
                const roleNames = selectedRoles.map(r => { let pill = rolePills.find(p => p.getAttribute('data-role') === r); return pill ? pill.innerText.trim() : r; });
                const adminNames = selectedAdmins.map(a => { let pill = adminPills.find(p => p.getAttribute('data-admin-id') === a); return pill ? pill.innerText.trim() : a; });
                const parts = [];
                if (roleNames.length) parts.push('نقش‌ها: ' + roleNames.join(', '));
                if (adminNames.length) parts.push('ادمین‌ها: ' + adminNames.join(', '));
                summaryDiv.innerText = parts.length ? parts.join(' | ') : 'هیچکدام';
            }
        }

        function filterAdminPillsBySelectedRoles() {
            if (!adminContainer) return;
            const selectedRoleIds = rolePills.filter(p => p.classList.contains('active')).map(p => p.getAttribute('data-role'));
            adminPills.forEach(pill => {
                const pillRole = pill.getAttribute('data-role');
                if (selectedRoleIds.includes(pillRole)) {
                    pill.style.display = 'none';
                    if (pill.classList.contains('active')) {
                        pill.classList.remove('active', 'btn-secondary');
                        pill.classList.add('btn-outline-secondary');
                    }
                } else {
                    pill.style.display = '';
                }
            });
        }

        function filterRolePillsBySelectedAdmins() {
            if (!adminContainer) return;
            const selectedAdminRoles = adminPills.filter(p => p.classList.contains('active')).map(p => p.getAttribute('data-role'));
            rolePills.forEach(pill => {
                const role = pill.getAttribute('data-role');
                if (selectedAdminRoles.includes(role)) {
                    pill.style.pointerEvents = 'none';
                    pill.style.opacity = '0.5';
                    if (pill.classList.contains('active')) {
                        pill.classList.remove('active', 'btn-primary');
                        pill.classList.add('btn-outline-primary');
                    }
                } else {
                    pill.style.pointerEvents = '';
                    pill.style.opacity = '';
                }
            });
        }

        function refreshPillState() {
            updateTargetsSummary();
            filterAdminPillsBySelectedRoles();
            filterRolePillsBySelectedAdmins();
        }

        function showAllPills() {
            if (!adminContainer) return;
            rolePills.forEach(pill => {
                if (pill.getAttribute('data-role') !== 'all') pill.style.display = '';
            });
            adminPills.forEach(pill => pill.style.display = '');
        }

        function hideNonAllPills() {
            if (!adminContainer) return;
            rolePills.forEach(pill => {
                if (pill.getAttribute('data-role') !== 'all') pill.style.display = 'none';
            });
            adminPills.forEach(pill => pill.style.display = 'none');
        }

        // ------------------------------ Pill initialisation (only if adminContainer exists) ------------------------------
        function initPills() {
            if (!adminContainer) return;
            rolePills = Array.from(document.querySelectorAll('#adminRolesList .role-pill'));
            adminPills = Array.from(document.querySelectorAll('#adminUsersList .admin-pill'));

            rolePills.forEach(pill => {
                if (pill.getAttribute('data-role') !== 'all') {
                    pill.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (this.style.pointerEvents === 'none') return;
                        if (isAllAdminsInput) isAllAdminsInput.value = '0';
                        showAllPills();
                        this.classList.toggle('active');
                        this.classList.toggle('btn-primary');
                        this.classList.toggle('btn-outline-primary');
                        refreshPillState();
                    });
                }
            });

            const allPill = rolePills.find(pill => pill.getAttribute('data-role') === 'all');
            if (allPill && isAllAdminsInput) {
                allPill.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const currentlyActive = allPill.classList.contains('active');
                    if (currentlyActive) {
                        isAllAdminsInput.value = '0';
                        showAllPills();
                        allPill.classList.remove('active', 'btn-primary');
                        allPill.classList.add('btn-outline-primary');
                        refreshPillState();
                    } else {
                        isAllAdminsInput.value = '1';
                        hideNonAllPills();
                        rolePills.forEach(pill => {
                            if (pill.getAttribute('data-role') !== 'all') {
                                pill.classList.remove('active', 'btn-primary');
                                pill.classList.add('btn-outline-primary');
                            }
                        });
                        adminPills.forEach(pill => {
                            pill.classList.remove('active', 'btn-secondary');
                            pill.classList.add('btn-outline-secondary');
                        });
                        allPill.classList.add('active', 'btn-primary');
                        allPill.classList.remove('btn-outline-primary');
                        const rolesField = document.getElementById('selectedAdminRoles');
                        const usersField = document.getElementById('selectedAdminUsers');
                        if (rolesField) rolesField.value = '[]';
                        if (usersField) usersField.value = '[]';
                        const summaryDiv = document.getElementById('selectedTargetsSummary');
                        if (summaryDiv) summaryDiv.innerText = 'همه ادمین‌ها';
                    }
                });
            }

            adminPills.forEach(pill => {
                pill.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (isAllAdminsInput) isAllAdminsInput.value = '0';
                    showAllPills();
                    this.classList.toggle('active');
                    this.classList.toggle('btn-secondary');
                    this.classList.toggle('btn-outline-secondary');
                    refreshPillState();
                });
            });

            if (isAllAdminsInput && isAllAdminsInput.value === '1') {
                hideNonAllPills();
                if (allPill) allPill.classList.add('active', 'btn-primary');
            } else {
                refreshPillState();
            }
        }

        // ------------------------------ Edit / Delete functions ------------------------------
        function editEvent(eventId) {
            if (!addEventModal || !form) {
                alert('ویرایش رویداد امکان‌پذیر نیست.');
                return;
            }
            // Close detail modal if open
            if (eventDetailModal) {
                const detailModal = bootstrap.Modal.getInstance(eventDetailModal);
                if (detailModal) detailModal.hide();
            }

            const event = calendar.getEventById(eventId);
            if (!event) {
                alert('رویداد یافت نشد');
                return;
            }

            const storedRoles = event.extendedProps.admin_roles || [];
            const storedUsers = event.extendedProps.admin_users || [];
            const storedCourses = event.extendedProps.course_targets || [];
            const visibility = event.extendedProps.visibility || 0;
            if (coursePills.length) {
                coursePills.forEach(pill => {
                    const courseId = pill.getAttribute('data-course-id');
                    if (storedCourses.includes(courseId)) {
                        pill.classList.add('active', 'btn-primary');
                        pill.classList.remove('btn-outline-primary');
                    } else {
                        pill.classList.remove('active', 'btn-primary');
                        pill.classList.add('btn-outline-primary');
                    }
                });
                updateCoursesSummary();
            }
            if (adminContainer) {
                rolePills.forEach(p => {
                    p.classList.remove('active', 'btn-primary');
                    p.classList.add('btn-outline-primary');
                });
                adminPills.forEach(p => {
                    p.classList.remove('active', 'btn-secondary');
                    p.classList.add('btn-outline-secondary');
                });

                if (storedRoles.length === 0 && storedUsers.length === 0 && isAllAdminsInput) {
                    isAllAdminsInput.value = '1';
                    hideNonAllPills();
                    const allPill = rolePills.find(p => p.getAttribute('data-role') === 'all');
                    if (allPill) {
                        allPill.classList.add('active', 'btn-primary');
                        allPill.classList.remove('btn-outline-primary');
                    }
                    const rolesField = document.getElementById('selectedAdminRoles');
                    const usersField = document.getElementById('selectedAdminUsers');
                    if (rolesField) rolesField.value = '[]';
                    if (usersField) usersField.value = '[]';
                    const summaryDiv = document.getElementById('selectedTargetsSummary');
                    if (summaryDiv) summaryDiv.innerText = 'همه ادمین‌ها';
                } else {
                    if (isAllAdminsInput) isAllAdminsInput.value = '0';
                    showAllPills();
                    storedRoles.forEach(role => {
                        const pill = rolePills.find(p => p.getAttribute('data-role') == role);
                        if (pill) {
                            pill.classList.add('active', 'btn-primary');
                            pill.classList.remove('btn-outline-primary');
                        }
                    });
                    storedUsers.forEach(userId => {
                        const pill = adminPills.find(p => p.getAttribute('data-admin-id') == userId);
                        if (pill) {
                            pill.classList.add('active', 'btn-secondary');
                            pill.classList.remove('btn-outline-secondary');
                        }
                    });
                    refreshPillState();
                }
            }

            document.getElementById('addEventModalLabel').innerText = 'ویرایش رویداد';
            document.getElementById('editEventId').value = eventId;
            document.getElementById('title').value = event.title;
            document.getElementById('description').value = event.extendedProps.description || '';
            document.getElementById('color').value = event.backgroundColor;
            document.getElementById('start_date').value = event.extendedProps.jalali_start || '';
            document.getElementById('end_date').value = event.extendedProps.jalali_end || '';
            document.getElementById('formAction').value = 'update_event';
            document.getElementById('formEventId').value = eventId;
            document.getElementById('visibilitySelect').value = visibility;
            document.getElementById('eventTypeSelect').value = event.extendedProps.event_type || 0;

            const targetType = event.extendedProps.target_type || 'admin';
            if (targetTypeSelect) {
                targetTypeSelect.value = targetType;
                targetTypeSelect.dispatchEvent(new Event('change'));
            }
            if (adminContainer && userContainer) {
                if (targetType === 'admin') {
                    adminContainer.style.display = 'block';
                    userContainer.style.display = 'none';
                } else {
                    adminContainer.style.display = 'none';
                    userContainer.style.display = 'block';
                }
            }
            new bootstrap.Modal(addEventModal).show();
        }

        function deleteEvent(eventId) {
            if (!confirm('آیا از حذف این رویداد اطمینان دارید؟')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'delete_event';
            const idInput = document.createElement('input');
            idInput.name = 'event_id';
            idInput.value = eventId;
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }        // ------------------------------ Calendar initialisation ------------------------------
        calendar = new FullCalendar.Calendar(calendarEl, {
            height: 500,
            locale: 'fa',
            initialView: 'dayGridMonth',
            firstDay: 6,     // 0 = Sunday, 6 = Saturday
            direction: 'rtl', // right-to-left layout
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'امروز',
                month: 'ماه',
                week: 'هفته',
                day: 'روز',
                list: 'برنامه'
            },

            editable: hasCalendarPermission,
            selectable: true,
            events: function(fetchInfo, successCallback, failureCallback) {
                fetch(window.location.href + '?get_events=1')
                    .then(response => response.json())
                    .then(events => {
                        events.forEach(ev => {
                            ev.extendedProps = ev.extendedProps || {};
                            ev.extendedProps.description = ev.description;
                            ev.extendedProps.jalali_start = ev.jalali_start;
                            ev.extendedProps.jalali_end = ev.jalali_end;
                            ev.extendedProps.target_summary = ev.target_summary;
                            ev.extendedProps.target_type = ev.target_type;
                            ev.extendedProps.admin_roles = ev.admin_roles || [];
                            ev.extendedProps.admin_users = ev.admin_users || [];
                            ev.extendedProps.visibility = ev.visibility;
                            ev.extendedProps.event_type = ev.event_type;
                        });
                        successCallback(events);
                    })
                    .catch(error => failureCallback(error));
            },
            dayHeaderContent: function(arg) {
                // Full Persian names (starting from Saturday)
                const fullNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
                // Single-letter abbreviations in same order
                const shortNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

                let dayIndex = arg.date.getDay(); // 0..6

                let persianIndex = (dayIndex + 1) % 7;

                const isSmall = window.innerWidth < 768; // adjust breakpoint as needed
                return { html: isSmall ? shortNames[persianIndex] : fullNames[persianIndex] };
            },
            eventClick: function(info) {
                const ev = info.event;
                const desc = ev.extendedProps.description || '—';
                let audienceHtml = '';
                let visibilityHtml = '';
                let eventTypeHtml = '';

                // Only show extra info if admin has full calendar permission
                if (hasCalendarPermission) {
                    // --- Audience (target) ---
                    if (ev.extendedProps.target_type === 'user') {
                        const courseIds = ev.extendedProps.course_targets || [];
                        if (courseIds.length === 0) {
                            audienceHtml = '<div style="margin-bottom:15px;"><strong>کاربران:</strong> همه کاربران</div>';
                        } else {
                            const courseNames = courseIds.map(id => courseNameMap[id] || id).join('، ');
                            audienceHtml = `<div style="margin-bottom:15px;"><strong>کاربران (پایه‌ها):</strong> ${courseNames}</div>`;
                        }
                    } else if (ev.extendedProps.target_type === 'both') {
                        const targetSummary = ev.extendedProps.target_summary || 'همه ادمین‌ها';
                        const courseIds = ev.extendedProps.course_targets || [];
                        let courseHtml = '';
                        if (courseIds.length === 0) {
                            courseHtml = '<div><strong>کاربران:</strong> همه کاربران</div>';
                        } else {
                            const courseNames = courseIds.map(id => courseNameMap[id] || id).join('، ');
                            courseHtml = `<div><strong>کاربران (پایه‌ها):</strong> ${courseNames}</div>`;
                        }
                        audienceHtml = `<div style="margin-bottom:15px;"><strong>ادمین‌ها:</strong> ${targetSummary}</div>${courseHtml}`;
                    } else {
                        // admin only
                        const targetSummary = ev.extendedProps.target_summary || 'همه ادمین‌ها';
                        audienceHtml = `<div style="margin-bottom:15px;"><strong>کاربران:</strong> ${targetSummary}</div>`;
                    }

                    // --- Visibility (hidden from students?) ---
                    const visibility = ev.extendedProps.visibility || 0;
                    const visibilityText = visibility == 1 ? 'مخفی' : 'قابل مشاهده';
                    visibilityHtml = `<div style="margin-bottom:15px;"><strong>نمایش برای دانش‌آموزان:</strong> ${visibilityText}</div>`;

                    // --- Event type (active/passive) ---
                    const eventType = ev.extendedProps.event_type || 0;
                    const eventTypeText = eventType == 1 ? 'عملی' : 'عادی';
                    eventTypeHtml = `<div style="margin-bottom:15px;"><strong>نوع رویداد:</strong> ${eventTypeText}</div>`;
                }

                const contentDiv = document.getElementById('eventDetailContent');
                if (contentDiv) {
                    contentDiv.innerHTML = `
            <div style="margin-bottom:15px;"><strong>عنوان:</strong> ${ev.title}</div>
            <div style="margin-bottom:15px;"><strong>توضیحات:</strong> ${desc}</div>
            <div style="margin-bottom:15px;"><strong>تاریخ شروع:</strong> ${ev.extendedProps.jalali_start}</div>
            ${ev.extendedProps.jalali_end ? `<div style="margin-bottom:15px;"><strong>تاریخ پایان:</strong> ${ev.extendedProps.jalali_end}</div>` : ''}
            ${audienceHtml}
            ${visibilityHtml}
            ${eventTypeHtml}
        `;
                }
                const editBtn = document.getElementById('editEventBtn');
                const deleteBtn = document.getElementById('deleteEventBtn');
                if (editBtn) {
                    editBtn.setAttribute('data-event-id', ev.id);
                    editBtn.onclick = () => editEvent(ev.id);
                }
                if (deleteBtn) {
                    deleteBtn.setAttribute('data-event-id', ev.id);
                    deleteBtn.onclick = () => deleteEvent(ev.id);
                }
                if (eventDetailModal) new bootstrap.Modal(eventDetailModal).show();
            },
            eventDrop: function(info) {
                if (!hasCalendarPermission) return;
                const event = info.event;
                const startGreg = event.startStr;
                const endGreg = event.endStr ? event.endStr : '';
                const formData = new URLSearchParams();
                formData.append('action', 'update_event');
                formData.append('event_id', event.id);
                formData.append('title', event.title);
                formData.append('description', event.extendedProps.description || '');
                formData.append('color', event.backgroundColor);
                formData.append('target_group', event.extendedProps.target_type || 'admin');
                formData.append('start_greg', startGreg);
                formData.append('end_greg', endGreg);
                formData.append('update_roles', '0');
                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) location.reload();
                        else alert('خطا در به‌روزرسانی تاریخ: ' + data.message);
                    })
                    .catch(err => { console.error(err); alert('خطا در ارتباط با سرور.'); });
            }
        });
        calendar.render();
        window.addEventListener('resize', function() {
            // Re-run dayHeaderContent by re-rendering the calendar
            calendar.render();
        });
        // ------------------------------ Datepickers ------------------------------
        // Add event modal datepickers (datetime)
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        if (startDateInput && typeof AzarDatepicker !== 'undefined') {
            new AzarDatepicker({
                selector: '#start_date',
                mode: 'datetime',
                calendar: 'jalali',
                inputFormat: 'YYYY/MM/DD HH:mm',
                outputFormat: 'YYYY/MM/DD HH:mm'
            });
        }
        if (endDateInput && typeof AzarDatepicker !== 'undefined') {
            new AzarDatepicker({
                selector: '#end_date',
                mode: 'datetime',
                calendar: 'jalali',
                inputFormat: 'YYYY/MM/DD HH:mm',
                outputFormat: 'YYYY/MM/DD HH:mm'
            });
        }

        // Export modal datepickers (date only)
        const exportStart = document.getElementById('export_start');
        const exportEnd = document.getElementById('export_end');
        if (exportStart && typeof AzarDatepicker !== 'undefined') {
            new AzarDatepicker({
                selector: '#export_start',
                mode: 'date',
                calendar: 'jalali',
                inputFormat: 'YYYY/MM/DD',
                outputFormat: 'YYYY/MM/DD'
            });
            exportStart._hasAzarPicker = true;
        }
        if (exportEnd && typeof AzarDatepicker !== 'undefined') {
            new AzarDatepicker({
                selector: '#export_end',
                mode: 'date',
                calendar: 'jalali',
                inputFormat: 'YYYY/MM/DD',
                outputFormat: 'YYYY/MM/DD'
            });
            exportEnd._hasAzarPicker = true;
        }

        // ------------------------------ Pills initialisation ------------------------------
        if (targetTypeSelect && adminContainer && userContainer) {
            targetTypeSelect.addEventListener('change', function() {
                const val = this.value;
                if (val === 'admin') {
                    adminContainer.style.display = 'block';
                    userContainer.style.display = 'none';
                } else if (val === 'user') {
                    adminContainer.style.display = 'none';
                    userContainer.style.display = 'block';
                } else if (val === 'both') {
                    // Show both sections
                    adminContainer.style.display = 'block';
                    userContainer.style.display = 'block';
                }
            });
            initPills();
            // Trigger once to set initial visibility
            targetTypeSelect.dispatchEvent(new Event('change'));
        }
        // ------------------------------ Form submit (add / edit) ------------------------------
// ------------------------------ Form submit (add / edit) - normal POST ----------
        if (form) {
            form.addEventListener('submit', function(e) {
                const targetGroup = targetTypeSelect ? targetTypeSelect.value : 'admin';
                const isAdminGroup = (targetGroup === 'admin');
                const isAllMode = (isAdminGroup && isAllAdminsInput && isAllAdminsInput.value === '1');

                // Validate admin targets
                if (isAdminGroup && !isAllMode) {
                    updateTargetsSummary();
                    const roles = document.getElementById('selectedAdminRoles').value;
                    const users = document.getElementById('selectedAdminUsers').value;
                    if ((!roles || roles === '[]') && (!users || users === '[]')) {
                        e.preventDefault();  // only prevent if validation fails
                        alert('لطفاً حداقل یک نقش، یک ادمین خاص را انتخاب کنید یا روی "همه" کلیک کنید.');
                        return false;
                    }
                }

                // If "all admins" mode, clear the role/user fields so they are sent as empty arrays
                if (isAdminGroup && isAllMode) {
                    document.getElementById('selectedAdminRoles').value = '[]';
                    document.getElementById('selectedAdminUsers').value = '[]';
                }

                // For user events, make sure the courses summary is updated
                if (targetGroup === 'user' || targetGroup === 'both') {
                    updateCoursesSummary();
                }

                if ((targetGroup === 'admin' || targetGroup === 'both') && !isAllMode) {
                    updateTargetsSummary();
                    const roles = document.getElementById('selectedAdminRoles').value;
                    const users = document.getElementById('selectedAdminUsers').value;
                    if ((!roles || roles === '[]') && (!users || users === '[]')) {
                        e.preventDefault();
                        alert('لطفاً حداقل یک نقش، یک ادمین خاص را انتخاب کنید یا روی "همه" کلیک کنید.');
                        return false;
                    }
                }
                // Do NOT call preventDefault() otherwise – let the browser submit the form normally
                return true;
            });
        }
        // Reinitialize datepickers when modals open
        if (addEventModal && typeof $ !== 'undefined') {
            $('#addEventModal').on('shown.bs.modal', function() {
                if (adminContainer && (!rolePills.length || !adminPills.length)) {
                    initPills();
                }
                if (typeof AzarDatepicker !== 'undefined') {
                    const startInput = document.getElementById('start_date');
                    const endInput = document.getElementById('end_date');
                    if (startInput && !startInput._hasAzarPicker) {
                        new AzarDatepicker({
                            selector: '#start_date',
                            mode: 'datetime',
                            calendar: 'jalali',
                            inputFormat: 'YYYY/MM/DD HH:mm',
                            outputFormat: 'YYYY/MM/DD HH:mm'
                        });
                        startInput._hasAzarPicker = true;
                    }
                    if (endInput && !endInput._hasAzarPicker) {
                        new AzarDatepicker({
                            selector: '#end_date',
                            mode: 'datetime',
                            calendar: 'jalali',
                            inputFormat: 'YYYY/MM/DD HH:mm',
                            outputFormat: 'YYYY/MM/DD HH:mm'
                        });
                        endInput._hasAzarPicker = true;
                    }
                }
            });
        }

        // ------------------------------ Export modal logic (server‑side Excel) ------------------------------
        // ------------------------------ Export modal logic (server‑side Excel) ------------------------------
        let exportRolePills = [];
        let exportAdminPills = [];
        let exportCoursePills = [];
        let exportIsAll = false;

        function updateExportSummary() {
            const eventType = document.getElementById('export_event_type').value;
            let summaryText = '';

            if (eventType === 'admin') {
                const selectedRoles = exportRolePills.filter(p => p.classList.contains('active')).map(p => p.getAttribute('data-role'));
                const selectedAdmins = exportAdminPills.filter(p => p.classList.contains('active')).map(p => p.getAttribute('data-admin-id'));
                document.getElementById('exportSelectedRoles').value = JSON.stringify(selectedRoles);
                document.getElementById('exportSelectedUsers').value = JSON.stringify(selectedAdmins);
                document.getElementById('exportSelectedCourses').value = '[]';

                if (selectedRoles.length === 0 && selectedAdmins.length === 0) {
                    summaryText = 'هیچکدام (تمام رویدادهای بازه)';
                } else {
                    const roleNames = selectedRoles.map(r => { let pill = exportRolePills.find(p => p.getAttribute('data-role') === r); return pill ? pill.innerText.trim() : r; });
                    const adminNames = selectedAdmins.map(a => { let pill = exportAdminPills.find(p => p.getAttribute('data-admin-id') === a); return pill ? pill.innerText.trim() : a; });
                    const parts = [];
                    if (roleNames.length) parts.push('نقش‌ها: ' + roleNames.join(', '));
                    if (adminNames.length) parts.push('ادمین‌ها: ' + adminNames.join(', '));
                    summaryText = parts.join(' | ');
                }
            } else { // user
                const selectedCourses = exportCoursePills.filter(p => p.classList.contains('active')).map(p => p.getAttribute('data-course-id'));
                document.getElementById('exportSelectedCourses').value = JSON.stringify(selectedCourses);
                document.getElementById('exportSelectedRoles').value = '[]';
                document.getElementById('exportSelectedUsers').value = '[]';

                if (selectedCourses.length === 0) {
                    summaryText = 'هیچکدام (همه پایه‌ها)';
                } else {
                    const courseNames = selectedCourses.map(c => { let pill = exportCoursePills.find(p => p.getAttribute('data-course-id') === c); return pill ? pill.innerText.trim() : c; });
                    summaryText = 'پایه‌ها: ' + courseNames.join(', ');
                }
            }
            const summaryDiv = document.getElementById('exportSelectedSummary');
            if (summaryDiv) summaryDiv.innerText = summaryText;
        }

        function initExportPills() {
            exportRolePills = Array.from(document.querySelectorAll('#exportRolesList .export-role-pill'));
            exportAdminPills = Array.from(document.querySelectorAll('#exportUsersList .export-admin-pill'));
            exportCoursePills = Array.from(document.querySelectorAll('#exportCoursePillsList .export-course-pill'));

            // Role pills
            exportRolePills.forEach(pill => {
                if (pill.getAttribute('data-role') !== 'all') {
                    pill.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (exportIsAll) {
                            exportIsAll = false;
                            document.getElementById('exportIsAllAdmins').value = '0';
                            showAllExportPills();
                        }
                        this.classList.toggle('active');
                        this.classList.toggle('btn-primary');
                        this.classList.toggle('btn-outline-primary');
                        updateExportSummary();
                    });
                }
            });

            const allPill = exportRolePills.find(p => p.getAttribute('data-role') === 'all');
            if (allPill) {
                allPill.addEventListener('click', function(e) {
                    e.stopPropagation();
                    exportIsAll = !exportIsAll;
                    if (exportIsAll) {
                        document.getElementById('exportIsAllAdmins').value = '1';
                        hideNonAllExportPills();
                        exportRolePills.forEach(p => {
                            if (p.getAttribute('data-role') !== 'all') {
                                p.classList.remove('active', 'btn-primary');
                                p.classList.add('btn-outline-primary');
                            }
                        });
                        exportAdminPills.forEach(p => {
                            p.classList.remove('active', 'btn-secondary');
                            p.classList.add('btn-outline-secondary');
                        });
                        allPill.classList.add('active', 'btn-primary');
                        allPill.classList.remove('btn-outline-primary');
                        document.getElementById('exportSelectedRoles').value = '[]';
                        document.getElementById('exportSelectedUsers').value = '[]';
                        document.getElementById('exportSelectedSummary').innerText = 'همه ادمین‌ها';
                    } else {
                        document.getElementById('exportIsAllAdmins').value = '0';
                        showAllExportPills();
                        allPill.classList.remove('active', 'btn-primary');
                        allPill.classList.add('btn-outline-primary');
                        updateExportSummary();
                    }
                });
            }

            // Admin pills
            exportAdminPills.forEach(pill => {
                pill.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (exportIsAll) {
                        exportIsAll = false;
                        document.getElementById('exportIsAllAdmins').value = '0';
                        showAllExportPills();
                        const allPill = exportRolePills.find(p => p.getAttribute('data-role') === 'all');
                        if (allPill) allPill.classList.remove('active', 'btn-primary');
                    }
                    this.classList.toggle('active');
                    this.classList.toggle('btn-secondary');
                    this.classList.toggle('btn-outline-secondary');
                    updateExportSummary();
                });
            });

            // Course pills for user events
            exportCoursePills.forEach(pill => {
                pill.addEventListener('click', function(e) {
                    e.stopPropagation();
                    this.classList.toggle('active');
                    this.classList.toggle('btn-primary');
                    this.classList.toggle('btn-outline-primary');
                    updateExportSummary();
                });
            });
        }

        function showAllExportPills() {
            exportRolePills.forEach(p => p.style.display = '');
            exportAdminPills.forEach(p => p.style.display = '');
            // course pills always visible when user filter active, but handled separately
        }

        function hideNonAllExportPills() {
            exportRolePills.forEach(p => {
                if (p.getAttribute('data-role') !== 'all') p.style.display = 'none';
            });
            exportAdminPills.forEach(p => p.style.display = 'none');
        }

// Toggle between admin and user filters
        const exportEventType = document.getElementById('export_event_type');
        const exportAdminFilters = document.getElementById('exportAdminFilters');
        const exportUserFilters = document.getElementById('exportUserFilters');

        if (exportEventType) {
            exportEventType.addEventListener('change', function() {
                if (this.value === 'admin') {
                    exportAdminFilters.style.display = 'block';
                    exportUserFilters.style.display = 'none';
                } else {
                    exportAdminFilters.style.display = 'none';
                    exportUserFilters.style.display = 'block';
                }
                updateExportSummary();
            });
        }

        const exportModal = document.getElementById('exportModal');
        if (exportModal) {
            exportModal.addEventListener('shown.bs.modal', function() {
                // Reinitialize datepickers if needed
                if (exportStart && typeof AzarDatepicker !== 'undefined' && !exportStart._hasAzarPicker) {
                    new AzarDatepicker({
                        selector: '#export_start',
                        mode: 'date',
                        calendar: 'jalali',
                        inputFormat: 'YYYY/MM/DD',
                        outputFormat: 'YYYY/MM/DD'
                    });
                    exportStart._hasAzarPicker = true;
                }
                if (exportEnd && typeof AzarDatepicker !== 'undefined' && !exportEnd._hasAzarPicker) {
                    new AzarDatepicker({
                        selector: '#export_end',
                        mode: 'date',
                        calendar: 'jalali',
                        inputFormat: 'YYYY/MM/DD',
                        outputFormat: 'YYYY/MM/DD'
                    });
                    exportEnd._hasAzarPicker = true;
                }
                if (exportRolePills.length === 0) initExportPills();
                // Reset selections
                exportIsAll = false;
                document.getElementById('exportIsAllAdmins').value = '0';
                showAllExportPills();
                exportRolePills.forEach(p => {
                    if (p.getAttribute('data-role') !== 'all') {
                        p.classList.remove('active', 'btn-primary');
                        p.classList.add('btn-outline-primary');
                    }
                });
                exportAdminPills.forEach(p => {
                    p.classList.remove('active', 'btn-secondary');
                    p.classList.add('btn-outline-secondary');
                });
                exportCoursePills.forEach(p => {
                    p.classList.remove('active', 'btn-primary');
                    p.classList.add('btn-outline-primary');
                });
                const allPill = exportRolePills.find(p => p.getAttribute('data-role') === 'all');
                if (allPill) allPill.classList.remove('active', 'btn-primary');

                // Reset event type to admin (default)
                exportEventType.value = 'admin';
                exportAdminFilters.style.display = 'block';
                exportUserFilters.style.display = 'none';

                updateExportSummary();
                // Clear date fields
                if (exportStart) exportStart.value = '';
                if (exportEnd) exportEnd.value = '';
            });
        }

// Export button: call server‑side PHP to generate Excel
        const doExportBtn = document.getElementById('doExportBtn');
        if (doExportBtn) {
            doExportBtn.addEventListener('click', function() {
                const startJalali = exportStart ? exportStart.value.trim() : '';
                const endJalali = exportEnd ? exportEnd.value.trim() : '';
                const selectedRoles = document.getElementById('exportSelectedRoles').value;
                const selectedUsers = document.getElementById('exportSelectedUsers').value;
                const isAllAdmins = document.getElementById('exportIsAllAdmins').value;
                const eventType = document.getElementById('export_event_type').value;
                const selectedCourses = document.getElementById('exportSelectedCourses').value;

                const formData = new FormData();
                formData.append('start_date', startJalali);
                formData.append('end_date', endJalali);
                formData.append('selected_roles', selectedRoles);
                formData.append('selected_users', selectedUsers);
                formData.append('is_all_admins', isAllAdmins);
                formData.append('event_type', eventType);
                formData.append('selected_courses', selectedCourses);

                // Path to the export PHP script (adjust if needed)
                const exportUrl = '/admin/Bill/calendar-excel.php?calendar-history=1';
                fetch(exportUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Server error');
                        return response.blob();
                    })
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'calendar_events.xlsx';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);
                        bootstrap.Modal.getInstance(exportModal).hide();
                    })
                    .catch(error => {
                        console.error(error);
                        alert('خطا در تولید فایل اکسل');
                    });
            });
        }

        // Remove all query parameters from the URL without reloading (for better appearance)
        if (window.location.search && !window.location.search.includes('get_events=1')) {
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    });



</script>