<?php
$stmt = $conn->prepare("SELECT idadmin, adminrole, permission_calendar FROM dbadmin WHERE username = ?");
$stmt->execute([$_SESSION['admin']['user']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$adminId = $admin['idadmin'];
$adminRole = $admin['adminrole'];
$permission_calendar = $admin['permission_calendar'];
// Calendar groups are پایه‌ها in a school and دوره‌ها in an institute; the
// weekly view puts one column per group, in the order they were created.
$gw = inst_is('institute') ? 'دوره' : 'پایه';
$weekGroups = $conn->query("SELECT id, title, color FROM calendar_groups ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if (isset($dashboardCalendarKinds) && AdminHasAnyCurrentTreePermission()) {
    // The index widget is view-only for tree accounts; editing belongs to the
    // separately permissioned executive/academic calendar pages.
    $permission_calendar = 0;
}

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

    // صفحه تقویم آموزشی قبل از include این متغیر را مقدار می دهد تا فقط
    // رویدادهای یک نوع خاص (کلاس) برگردانده شوند. در تقویم اجرایی خالی است.
    $onlyType = isset($calendar_only_type) && $calendar_only_type !== null
        ? (int)$calendar_only_type
        : null;

    if ($permission_calendar == 1) {
        $sql = "SELECT e.*, COALESCE(g.color, '#B5B5B5') AS event_color, g.title AS group_title
        FROM calendar_events e
        LEFT JOIN calendar_groups g ON e.calendar_groups_id = g.id";
        $params = [];
        if ($onlyType !== null) {
            $sql .= " WHERE e.event_type = ?";
            $params[] = $onlyType;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "
    SELECT DISTINCT e.*, COALESCE(g.color, '#B5B5B5') AS event_color, g.title AS group_title
    FROM calendar_events e
    LEFT JOIN calendar_groups g ON e.calendar_groups_id = g.id
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
        // اگر رویداد به گروه های آموزشی محدود شده باشد، فقط اعضای همان گروه ها آن را می بینند
        $sql .= "
      AND (
          NOT EXISTS (SELECT 1 FROM calendar_event_teacher_groups cg WHERE cg.event_id = e.id)
          OR EXISTS (
              SELECT 1 FROM calendar_event_teacher_groups cg
              INNER JOIN teacher_group_members tm ON tm.group_id = cg.group_id
              WHERE cg.event_id = e.id AND tm.admin_id = ?
          )
      )
    ";
        $params = [$adminRole, $adminId, $adminId];
        if ($onlyType !== null) {
            $sql .= " AND e.event_type = ?";
            $params[] = $onlyType;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    }
    $eventRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // ممکن است سیاست مخاطبان قدیمی تقویم با سیاست نقش‌های مدرسه یکسان نباشد.
    // اگر کاربر اعلان مجازِ همین رویداد را دریافت کرده باشد، لینک اعلان باید
    // بتواند همان رویداد را نمایش دهد؛ بدون اعلان مجاز، واردکردن دستی شناسه
    // باعث دورزدن سطح دسترسی تقویم نمی‌شود.
    $focusEventId = isset($_GET['event']) ? (int)$_GET['event'] : 0;
    if ($focusEventId > 0 && !in_array($focusEventId, array_map('intval', array_column($eventRows, 'id')), true)) {
        $focusAllowed = false;
        foreach (GetSchoolNotifications((string)($_SESSION['admin']['user'] ?? ''), null, 300) as $notification) {
            if (!in_array($notification['event_type'] ?? '', ['executive_calendar', 'academic_calendar'], true)) {
                continue;
            }
            $query = parse_url((string)($notification['target_url'] ?? ''), PHP_URL_QUERY);
            $targetParams = [];
            parse_str((string)$query, $targetParams);
            if ((int)($targetParams['event'] ?? 0) === $focusEventId) {
                $focusAllowed = true;
                break;
            }
        }
        if ($focusAllowed) {
            $focusStmt = $conn->prepare("SELECT e.*, COALESCE(g.color, '#B5B5B5') AS event_color, g.title AS group_title
                FROM calendar_events e
                LEFT JOIN calendar_groups g ON e.calendar_groups_id = g.id
                WHERE e.id = ? LIMIT 1");
            $focusStmt->execute([$focusEventId]);
            $focusRow = $focusStmt->fetch(PDO::FETCH_ASSOC);
            if ($focusRow) {
                $eventRows[] = $focusRow;
            }
        }
    }

    if (isset($dashboardCalendarKinds)) {
        $eventRows = array_values(array_filter($eventRows, static function (array $row) use ($dashboardCalendarKinds): bool {
            return !empty($row['is_academic'])
                ? !empty($dashboardCalendarKinds['academic'])
                : !empty($dashboardCalendarKinds['executive']);
        }));
    }
    $events = [];
    foreach ($eventRows as $row) {
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
        $options = [];
        $optStmt = $conn->prepare("SELECT events_option as `option`, needs_reason FROM active_event_options WHERE event_id = ?");
        $optStmt->execute([$row['id']]);
        while ($o = $optStmt->fetch(PDO::FETCH_ASSOC)) {
            $options[] = $o;
        }
        // رویداد با ساعت شروع ۰۰:۰۰ یعنی تمام روز (ساعت پایان در این حالت اهمیتی ندارد).
        // در غیر این صورت رویداد ساعت دار است و باید با ساعت خودش به تقویم داده شود.
        $start_ts = strtotime($row['start_date']);
        $end_ts   = $row['end_date'] ? strtotime($row['end_date']) : null;

        $is_all_day = (date('H:i:s', $start_ts) === '00:00:00');

        // نکته مهم: رشته ها باید بدون افست ساعت باشند.
        // تقویم با timeZone: 'local' همین ساعت را عیناً نمایش می دهد؛
        // افزودن 03:30+ باعث جابجایی ساعت نمایش داده شده می شود.
        if ($is_all_day) {
            $start_out = date('Y-m-d', $start_ts);
            // در حالت تمام روز، تقویم تاریخ پایان را انحصاری در نظر می گیرد؛
            // برای اینکه خود روز پایانی هم پوشش داده شود یک روز به آن اضافه می شود.
            if ($end_ts === null || date('Y-m-d', $end_ts) === date('Y-m-d', $start_ts)) {
                $end_out = null; // رویداد یک روزه
            } else {
                $end_out = date('Y-m-d', strtotime('+1 day', $end_ts));
            }
        } else {
            $start_out = date('Y-m-d\TH:i:s', $start_ts);
            $end_out   = $end_ts ? date('Y-m-d\TH:i:s', $end_ts) : null;
        }

        $events[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'start' => $start_out,
            'end'   => $end_out,
            'allDay' => $is_all_day,
            'color' => $row['event_color'],
            'description' => $row['description'],
            'personal_note' => $row['personal_note'],
            'calendar_groups_id' => $row['calendar_groups_id'] ?? null,
            'group_title' => $row['group_title'] ?? '',
            'jalali_start' => $jalali_start,
            'jalali_end' => $jalali_end,
            'target_summary' => $target_summary,
            'target_type' => $row['target_type'],
            'admin_roles' => $roles,
            'admin_users' => $users,
            'course_targets' => $courses,
            'visibility' => $row['visibility'] ?? 0,
            'event_type'  => $row['event_type'] ?? 0,
            'is_academic' => $row['is_academic'] ?? 0,
            'teacher_groups' => getEventTeacherGroups((int)$row['id']),
            'options' => $options,
            'overlap' => $row['overlap'] ?? 0
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
    $personal_note = trim($_POST['personal_note'] ?? '');
    $start_jalali = $_POST['start_date'] ?? '';
    $end_jalali = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $calendar_groups_id = !empty($_POST['calendar_groups_id']) ? (int)$_POST['calendar_groups_id'] : null;
    $target_group = $_POST['target_group'] ?? 'admin';
    $visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 0;
    $event_type = isset($_POST['event_type']) ? (int)$_POST['event_type'] : 0;
    $isAcademic = !empty($calendar_force_academic)
        ? 1
        : (isset($_POST['is_academic']) ? (int)$_POST['is_academic'] : 0);
    $overlap = isset($_POST['overlap']) ? (int)$_POST['overlap'] : 0;
    $start_greg = jalaliToGregorian($start_jalali);
    $end_greg = jalaliToGregorian($end_jalali);



    if (empty($title) || !$start_greg) {
        debug_log("Validation failed", ['title' => $title, 'start_greg' => $start_greg]);
        $_SESSION['calendar_message'] = ['success' => false, 'message' => 'عنوان و تاریخ شروع معتبر اجباری هستند.'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ---------- رویداد هفتگی ----------
    // رویداد در روزِ انتخاب شده از هفته، از تاریخ شروع تا تاریخ پایان و با همان ساعت تکرار می شود.
    // هر تکرار یک رویداد مستقل است تا مثل بقیه ی رویدادها قابل ویرایش و حذف باشد.
    $is_weekly  = (($_POST['is_weekly'] ?? '') === '1');
    $weekly_day = isset($_POST['weekly_day']) ? (int)$_POST['weekly_day'] : -1;

    if ($is_weekly && ($weekly_day < 0 || $weekly_day > 6)) {
        $_SESSION['calendar_message'] = ['success' => false, 'message' => 'برای رویداد هفتگی، روز هفته را انتخاب کنید.'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    if ($is_weekly && !$end_greg) {
        $_SESSION['calendar_message'] = ['success' => false, 'message' => 'برای رویداد هفتگی، وارد کردن تاریخ پایان الزامی است.'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // فهرست جلسه ها؛ برای رویداد معمولی فقط یک مورد است
    $occurrences = [];
    if ($is_weekly) {
        $startTs   = strtotime($start_greg);
        $endTs     = strtotime($end_greg);
        $startTime = date('H:i:s', $startTs);
        $endTime   = date('H:i:s', $endTs);
        $lastDayTs = strtotime(date('Y-m-d', $endTs));

        if ($endTs < $startTs) {
            $_SESSION['calendar_message'] = ['success' => false, 'message' => 'تاریخ پایان نمی تواند قبل از تاریخ شروع باشد.'];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // اولین روزِ هفته ی انتخاب شده، از تاریخ شروع به بعد
        $cursor = strtotime(date('Y-m-d', $startTs));
        $cursor = strtotime('+' . ((($weekly_day - (int)date('w', $cursor)) + 7) % 7) . ' days', $cursor);

        // سقف تعداد جلسه ها تا یک بازه ی اشتباه، هزاران رویداد نسازد
        $maxOccurrences = 260;
        while ($cursor <= $lastDayTs && count($occurrences) < $maxOccurrences) {
            $day      = date('Y-m-d', $cursor);
            $occStart = $day . ' ' . $startTime;
            $occEnd   = $day . ' ' . $endTime;
            // اگر ساعت پایان بعد از ساعت شروع نباشد، جلسه بدون زمان پایان ثبت می شود
            if (strtotime($occEnd) <= strtotime($occStart)) {
                $occEnd = null;
            }
            $occurrences[] = [$occStart, $occEnd];
            $cursor = strtotime('+7 days', $cursor);
        }

        if (empty($occurrences)) {
            $_SESSION['calendar_message'] = ['success' => false, 'message' => 'در بازه ی انتخاب شده هیچ روزی با روز هفته ی انتخابی مطابقت ندارد.'];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } else {
        $occurrences[] = [$start_greg, $end_greg];
    }

    if ($overlap) {
        foreach ($occurrences as [$occStart, $occEnd]) {
            if (!$occStart || !$occEnd) {
                continue;
            }
            $conflictError = checkOverlapConflict(
                $conn, 0, $occStart, $occEnd,
                $target_group,
                json_decode($_POST['selected_courses'] ?? '[]', true),
                json_decode($_POST['admin_roles'] ?? '[]', true),
                json_decode($_POST['admin_users'] ?? '[]', true),
                ($_POST['is_all_admins'] ?? '0') === '1'
            );
            if ($conflictError) {
                // در رویداد هفتگی مشخص می شود کدام جلسه تداخل دارد
                if ($is_weekly) {
                    $conflictError .= ' (جلسه ی ' . jdate('Y/m/d', strtotime($occStart)) . ')';
                }
                $_SESSION['calendar_message'] = ['success' => false, 'message' => $conflictError];
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
    try {
        $conn->beginTransaction();
        $createdEventIds = [];
        $selectedCoursesForNotification = json_decode($_POST['selected_courses'] ?? '[]', true);
        if (!is_array($selectedCoursesForNotification)) {
            $selectedCoursesForNotification = [];
        }
        // هر جلسه ی رویداد هفتگی یک رکورد مستقل با همان مخاطبان و تنظیمات است
        // جلسه های رویداد هفتگی به ترتیب شماره می گیرند: «عنوان #1»، «عنوان #2» ...
        foreach ($occurrences as $sessionIndex => [$occStart, $occEnd]) {
            $occTitle = $is_weekly ? $title . ' #' . ($sessionIndex + 1) : $title;

            $stmt = $conn->prepare("
        INSERT INTO calendar_events 
        (title, description,personal_note, start_date, end_date, calendar_groups_id, creator_id, updated_by_id, target_type, visibility, event_type, overlap, is_academic)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
            $stmt->execute([$occTitle, $description, $personal_note , $occStart, $occEnd, $calendar_groups_id, $adminId, $adminId, $target_group, $visibility, $event_type, $overlap, $isAcademic]);
            $eventId = $conn->lastInsertId();
            $createdEventIds[] = (int)$eventId;

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

            // گروه های آموزشی مجاز برای دیدن این رویداد
            setEventTeacherGroups((int)$eventId, $_POST['teacher_groups'] ?? []);

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
            if ($event_type == 1) {
                $options = json_decode($_POST['active_options'] ?? '[]', true);
                if (!empty($options)) {
                    $optionStmt = $conn->prepare("INSERT INTO active_event_options (event_id, events_option, needs_reason) VALUES (?, ?, ?)");
                    foreach ($options as $opt) {
                        $text = trim($opt['text'] ?? '');
                        $needsReason = isset($opt['needs_reason']) ? (int)$opt['needs_reason'] : 0;
                        if ($text !== '') {
                            $optionStmt->execute([$eventId, $text, $needsReason]);
                        }
                    }
                }
            }
        }

        $conn->commit();
        $notificationCourses = $selectedCoursesForNotification ?: [null];
        $notificationSourceId = $createdEventIds[0] ?? null;
        foreach ($notificationCourses as $notificationCourse) {
            CreateSchoolNotification(
                $isAcademic ? 'academic_calendar' : 'executive_calendar',
                ($isAcademic ? 'تقویم آموزشی: ' : 'تقویم اجرایی: ') . $title,
                $description,
                (int)$adminId,
                (string)($_SESSION['admin']['user'] ?? ''),
                $notificationCourse ? (int)$notificationCourse : null,
                null,
                null,
                ($isAcademic ? 'admin/panel/academic-calendar.php' : 'admin/panel/calendar_panel.php')
                    . '?event=' . (int)$notificationSourceId,
                'calendar_events',
                $notificationSourceId ? (string)$notificationSourceId : null
            );
        }
        $_SESSION['calendar_message'] = ['success' => true, 'message' => $is_weekly
            ? 'رویداد هفتگی با موفقیت ثبت شد؛ ' . count($occurrences) . ' جلسه ساخته شد.'
            : 'رویداد با موفقیت اضافه شد.'];
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
    $personal_note = $_POST['personal_note'] ?? '';
    $calendar_groups_id = !empty($_POST['calendar_groups_id']) ? (int)$_POST['calendar_groups_id'] : null;
    $target_group = $_POST['target_group'] ?? 'admin';
    $visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 0;
    $event_type = isset($_POST['event_type']) ? (int)$_POST['event_type'] : 0;
    $isAcademic = !empty($calendar_force_academic)
        ? 1
        : (isset($_POST['is_academic']) ? (int)$_POST['is_academic'] : 0);
    $overlap = isset($_POST['overlap']) ? (int)$_POST['overlap'] : 0;
    if (!$eventId || !$start_greg) {
        if (isset($_POST['update_roles']) && $_POST['update_roles'] == 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص.']);
        } else {
            $_SESSION['calendar_message'] = ['success' => false, 'message' => 'اطلاعات ناقص.'];
            header('Location: ' . $_SERVER['PHP_SELF']);
        }
        exit;
    }
    $isAjaxDragDrop = (isset($_POST['update_roles']) && $_POST['update_roles'] == 0);

    if ($overlap && $start_greg && $end_greg) {
        $conflictError = checkOverlapConflict(
            $conn, $eventId, $start_greg, $end_greg,
            $target_group,
            json_decode($_POST['selected_courses'] ?? '[]', true),
            json_decode($_POST['admin_roles'] ?? '[]', true),
            json_decode($_POST['admin_users'] ?? '[]', true),
            ($_POST['is_all_admins'] ?? '0') === '1'
        );

        if ($conflictError) {
            if ($isAjaxDragDrop) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $conflictError]);
            } else {
                $_SESSION['calendar_message'] = ['success' => false, 'message' => $conflictError];
                header('Location: ' . $_SERVER['PHP_SELF']);
            }
            exit;
        }
    }
    try {
        $conn->beginTransaction();

        if ($isAjaxDragDrop) {
            // Drag‑drop: only update the dates
            $stmt = $conn->prepare("UPDATE calendar_events SET start_date=?, end_date=?, updated_by_id=? WHERE id=?");
            $stmt->execute([$start_greg, $end_greg, $adminId, $eventId]);
            // کشیدن به ستون پایه/دوره ی دیگر در نمای هفته، پایه ی رویداد را هم عوض می کند (0 = بدون پایه)
            if (isset($_POST['move_group'])) {
                $moveGroup = (int)$_POST['move_group'] ?: null;
                $conn->prepare("UPDATE calendar_events SET calendar_groups_id=? WHERE id=?")->execute([$moveGroup, $eventId]);
            }
        } else {
            $oldTitleStmt = $conn->prepare("SELECT title FROM calendar_events WHERE id = ?");
            $oldTitleStmt->execute([$eventId]);
            $oldTitle = (string)$oldTitleStmt->fetchColumn();

            // Full update from the modal
            $stmt = $conn->prepare("UPDATE calendar_events SET title=?, description=?, personal_note=? , start_date=?, end_date=?, calendar_groups_id=?, updated_by_id=?, target_type=?, visibility=?, event_type=?, overlap=?, is_academic=? WHERE id=?");
            $stmt->execute([$title, $description, $personal_note, $start_greg, $end_greg, $calendar_groups_id, $adminId, $target_group, $visibility, $event_type, $overlap, $isAcademic, $eventId]);

            // فقط شماره ی جلسه عوض شده (مثلاً «ریاضی ششم #3» → «#2»): جلسه های بعدی همان
            // رویداد هفتگی (همان عنوان و همان پایه، بعد از این جلسه) از شماره ی جدید پشت سر هم شماره می گیرند.
            if (preg_match('/^(.*\S)\s*#(\d+)$/u', trim($oldTitle), $oldM)
                && preg_match('/^(.*\S)\s*#(\d+)$/u', trim($title), $newM)
                && $oldM[1] === $newM[1] && $oldM[2] !== $newM[2]) {
                $base = $newM[1];
                $next = (int)$newM[2];
                $seriesStmt = $conn->prepare("SELECT id, title FROM calendar_events
                    WHERE id <> ? AND start_date > ? AND calendar_groups_id <=> ? AND title LIKE ?
                    ORDER BY start_date, id");
                $seriesStmt->execute([$eventId, $start_greg, $calendar_groups_id, $base . '%#%']);
                $renumber = $conn->prepare("UPDATE calendar_events SET title = ?, updated_by_id = ? WHERE id = ?");
                foreach ($seriesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (preg_match('/^(.*\S)\s*#(\d+)$/u', trim($row['title']), $m) && $m[1] === $base) {
                        $renumber->execute([$base . ' #' . (++$next), $adminId, $row['id']]);
                    }
                }
            }

            // گروه های آموزشی مجاز
            setEventTeacherGroups((int)$eventId, $_POST['teacher_groups'] ?? []);

            // Decode incoming selections based on target group settings
            $adminRoles = ($target_group === 'admin' || $target_group === 'both') ? json_decode($_POST['admin_roles'] ?? '[]', true) : [];
            $adminUsers = ($target_group === 'admin' || $target_group === 'both') ? json_decode($_POST['admin_users'] ?? '[]', true) : [];
            $selectedCourses = ($target_group === 'user' || $target_group === 'both') ? json_decode($_POST['selected_courses'] ?? '[]', true) : [];

            // --- 1. Synchronize Admin Roles ---
            $stmt = $conn->prepare("SELECT adminrole FROM calendar_admin_role_targets WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $currentRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $rolesToDelete = array_diff($currentRoles, $adminRoles);
            $rolesToInsert = array_diff($adminRoles, $currentRoles);

            if (!empty($rolesToDelete)) {
                $in = str_repeat('?,', count($rolesToDelete) - 1) . '?';
                $conn->prepare("DELETE FROM calendar_admin_role_targets WHERE event_id = ? AND adminrole IN ($in)")->execute(array_merge([$eventId], $rolesToDelete));
            }
            if (!empty($rolesToInsert)) {
                $roleStmt = $conn->prepare("INSERT INTO calendar_admin_role_targets (event_id, adminrole) VALUES (?, ?)");
                foreach ($rolesToInsert as $role) $roleStmt->execute([$eventId, $role]);
            }

            // --- 2. Synchronize Admin Users ---
            $stmt = $conn->prepare("SELECT admin_id FROM calendar_admin_user_targets WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $currentUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $usersToDelete = array_diff($currentUsers, $adminUsers);
            $usersToInsert = array_diff($adminUsers, $currentUsers);

            if (!empty($usersToDelete)) {
                $in = str_repeat('?,', count($usersToDelete) - 1) . '?';
                $conn->prepare("DELETE FROM calendar_admin_user_targets WHERE event_id = ? AND admin_id IN ($in)")->execute(array_merge([$eventId], $usersToDelete));
            }
            if (!empty($usersToInsert)) {
                $userStmt = $conn->prepare("INSERT INTO calendar_admin_user_targets (event_id, admin_id) VALUES (?, ?)");
                foreach ($usersToInsert as $uid) $userStmt->execute([$eventId, $uid]);
            }

            // --- 3. Synchronize Course Targets ---
            $stmt = $conn->prepare("SELECT course_id FROM calendar_course_targets WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $currentCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $coursesToDelete = array_diff($currentCourses, $selectedCourses);
            $coursesToInsert = array_diff($selectedCourses, $currentCourses);

            if (!empty($coursesToDelete)) {
                $in = str_repeat('?,', count($coursesToDelete) - 1) . '?';
                $conn->prepare("DELETE FROM calendar_course_targets WHERE event_id = ? AND course_id IN ($in)")->execute(array_merge([$eventId], $coursesToDelete));
            }
            if (!empty($coursesToInsert)) {
                $courseStmt = $conn->prepare("INSERT INTO calendar_course_targets (event_id, course_id) VALUES (?, ?)");
                foreach ($coursesToInsert as $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) $courseStmt->execute([$eventId, $cid]);
                }
            }

            // --- 4. Synchronize Active Event Options ---
            $stmt = $conn->prepare("SELECT events_option, needs_reason FROM active_event_options WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $currentOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $currentOptsMap = [];
            foreach ($currentOptions as $co) {
                $currentOptsMap[$co['events_option']] = (int)$co['needs_reason'];
            }

            $newOptions = [];
            if ($event_type == 1) {
                $submittedOptions = json_decode($_POST['active_options'] ?? '[]', true);
                foreach ($submittedOptions as $opt) {
                    $text = trim($opt['text'] ?? '');
                    if ($text !== '') {
                        $newOptions[$text] = isset($opt['needs_reason']) ? (int)$opt['needs_reason'] : 0;
                    }
                }
            }

            // Delete options no longer present
            foreach ($currentOptsMap as $text => $needsReason) {
                if (!isset($newOptions[$text])) {
                    $conn->prepare("DELETE FROM active_event_options WHERE event_id = ? AND events_option = ?")->execute([$eventId, $text]);
                }
            }

            // Insert new options or safely update changed flags
            foreach ($newOptions as $text => $needsReason) {
                if (!isset($currentOptsMap[$text])) {
                    $conn->prepare("INSERT INTO active_event_options (event_id, events_option, needs_reason) VALUES (?, ?, ?)")->execute([$eventId, $text, $needsReason]);
                } else if ($currentOptsMap[$text] !== $needsReason) {
                    $conn->prepare("UPDATE active_event_options SET needs_reason = ? WHERE event_id = ? AND events_option = ?")->execute([$needsReason, $eventId, $text]);
                }
            }
        }

        $conn->commit();

        if ($isAjaxDragDrop) {
            $calendarContextStmt = $conn->prepare('SELECT title, description, is_academic FROM calendar_events WHERE id = ? LIMIT 1');
            $calendarContextStmt->execute([$eventId]);
            $calendarContext = $calendarContextStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $title = (string)($calendarContext['title'] ?? $title);
            $description = (string)($calendarContext['description'] ?? $description);
            $isAcademic = (int)($calendarContext['is_academic'] ?? 0);
        }
        $notificationCourseStmt = $conn->prepare('SELECT course_id FROM calendar_course_targets WHERE event_id = ?');
        $notificationCourseStmt->execute([$eventId]);
        $notificationCourses = array_map('intval', $notificationCourseStmt->fetchAll(PDO::FETCH_COLUMN));
        if ($notificationCourses === []) $notificationCourses = [null];
        foreach ($notificationCourses as $notificationCourse) {
            CreateSchoolNotification(
                $isAcademic ? 'academic_calendar' : 'executive_calendar',
                ($isAcademic ? 'تغییر تقویم آموزشی: ' : 'تغییر تقویم اجرایی: ') . $title,
                $description,
                (int)$adminId,
                (string)($_SESSION['admin']['user'] ?? ''),
                $notificationCourse ? (int)$notificationCourse : null,
                null,
                null,
                ($isAcademic ? 'admin/panel/academic-calendar.php' : 'admin/panel/calendar_panel.php')
                    . '?event=' . (int)$eventId,
                'calendar_events_update',
                null
            );
        }

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
    global $permission_calendar, $conn, $gw, $weekGroups;
    ?>
    <style>
        /* overriding the original fulcalendar styles for customizing the calendar. for more customization change or add here. */
        #calendar {
            background: linear-gradient(45deg, #d49c85, #2b9587, #1a2d57);
            border-radius: 12px;
            padding: 8px;
        }
        #calendar .calendar-event-focus {
            position: relative;
            z-index: 5;
            outline: 3px solid #ff2d55 !important;
            outline-offset: 2px;
            box-shadow: 0 0 0 5px rgba(255,45,85,.22), 0 0 18px rgba(255,45,85,.8) !important;
            animation: calendarEventFocusPulse 1s ease-in-out 4;
        }
        @keyframes calendarEventFocusPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.035); }
        }
        #calendar .fc-button {
            background-color: #298680;
            color: #fff;
            border: none;
            transition: background-color 200ms;
        }

        #calendar .fc-button:hover {
            background-color: #37bab4;
        }

        #calendar .fc-button.fc-button-active {
            background-color: #4bfff9;
            color: #2e004a;
        }
        #calendar .fc-timegrid-slot-label-cushion,
        #calendar .fc-timegrid-axis-cushion {
            color:  white !important;
            /* time color on the right side of screen while week and day are active */
        }
        #calendar .fc-daygrid-day-frame {
            background: #e4ffed;
            border: 1px solid lightgrey !important;
            border-radius: 10px !important;
        }
        .fc-toolbar-title {
            color: white !important;
        }

        /* ---------- دراپ‌داون های چندانتخابی داخل مودال ----------
           select2 داخل مودال عرض ظرف را ۰ حساب می کند و در نتیجه کادر جستجو
           به اندازه یک حرف باریک می ماند و placeholder دیده نمی شود. */
        #addEventModal .select2-container {
            width: 100% !important;
        }

        #addEventModal .select2-container--default .select2-selection--multiple {
            width: 100%;
            min-height: 38px;
            border-color: #ced4da;
        }

        #addEventModal .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: block;
            width: 100%;
        }

        /* فقط وقتی هیچ گزینه ای انتخاب نشده کادر جستجو تمام عرض را می گیرد تا
           متن راهنما کامل دیده شود. اگر همیشه ۱۰۰٪ باشد، برچسب های انتخاب شده
           از کادر بیرون می افتند و دیده نمی شوند. */
        #addEventModal .select2-container.is-empty--default .select2-search--inline .select2-search__field,
        #addEventModal .select2-container.is-empty .select2-search--inline .select2-search__field {
            width: 100% !important;
        }

        /* برچسب های انتخاب شده باید بشکنند و داخل کادر بمانند */
        #addEventModal .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 3px;
            padding-left: 6px;
            padding-right: 6px;
        }

        #addEventModal .select2-container--default .select2-selection--multiple .select2-selection__choice {
            margin: 2px 0;
            float: none;
        }

        #addEventModal .select2-container--default .select2-selection--multiple .select2-search--inline {
            float: none;
        }

        /* ---------- نمای جابجاشده روز/هفته ----------
           ساختار و رنگ ها عیناً از نمای ماه گرفته شده است:
           سلول شفاف + قاب گرد نعنایی داخل آن، و سربرگ به شکل قرص روشن.
           روزها در سطر و ساعت ها در ستون؛ محور زمان راست به چپ است. */
        #transposedCalendar {
            background: transparent;
            padding: 0;
            overflow-x: auto;
        }

        #transposedCalendar table {
            width: 100%;
            min-width: 780px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* مثل td های نمای ماه: شفاف با خط جدا کننده نازک */
        #transposedCalendar th,
        #transposedCalendar td {
            background: transparent;
            border: 1px solid #dddddd;
            padding: 0;
            vertical-align: top;
        }

        /* قرص سربرگ، دقیقاً مثل fc-col-header-cell-cushion */
        #transposedCalendar .tc-pill {
            display: inline-block;
            background: #e8e8e8;
            border: 2px solid lightgrey;
            border-radius: 10px;
            padding: 2px 4px;
            margin: 4px 2px;
            font-size: 13px;
            font-weight: 700;
            color: #1d212f;
        }

        #transposedCalendar thead th {
            text-align: center;
        }

        #transposedCalendar th.tc-day-head {
            width: 118px;
            text-align: center;
        }

        /* قاب گرد نعنایی، دقیقاً مثل fc-daygrid-day-frame */
        #transposedCalendar .tc-frame {
            background: #e4ffed;
            border: 1px solid lightgrey;
            border-radius: 10px;
            height: 100%;
            position: relative;
        }

        #transposedCalendar td.tc-day-head {
            width: 118px;
        }

        #transposedCalendar td.tc-day-head .tc-frame {
            padding: 4px;
            text-align: center;
            /* محتوای برچسب روز وسط چین شود تا زیر عنوان فضای خالی نماند */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #transposedCalendar .tc-dayname {
            font-size: 14px;
            font-weight: 600;
            color: #1d212f;
            display: block;
        }

        #transposedCalendar .tc-daydate {
            font-size: 12px;
            font-weight: 400;
            color: #5b6b62;
            display: block;
        }

        #transposedCalendar tr.tc-today td.tc-day-head .tc-frame {
            background: #c9f7dc;
            border-color: #298680;
        }

        /* ردیف ساعت ها: هر ساعت یک قاب مستقل، هم شکل سلول های ماه */
        #transposedCalendar td.tc-track {
            position: relative;
        }

        #transposedCalendar .tc-hours {
            position: absolute;
            inset: 0;
            display: flex;
        }

        #transposedCalendar .tc-hour {
            flex: 1 1 0;
            background: #e4ffed;
            border: 1px solid lightgrey;
            border-radius: 10px;
            margin: 0 1px;
        }

        #transposedCalendar .tc-events {
            position: absolute;
            inset: 0;
        }

        /* رویدادها؛ گردی و اندازه هم خانواده نمای ماه */
        #transposedCalendar .tc-event {
            position: absolute;
            height: 26px;
            line-height: 24px;
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            padding: 0 7px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }

        #transposedCalendar .tc-event:hover {
            filter: brightness(.94);
        }

        /* رویداد تمام روز مثل نوار خاکستری نمای ماه */
        #transposedCalendar .tc-allday {
            display: block;
            margin: 3px 0 0;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #transposedCalendar .tc-empty {
            text-align: center;
            color: #1d212f;
            font-size: 12px;
            padding: 18px;
        }

        /* ---------- نمای هفته: روزها در سطر، پایه ها در ستون ---------- */
        #transposedCalendar table.wg-table {
            background: #fff;
            border-radius: 10px;
        }

        #transposedCalendar th.wg-head,
        #transposedCalendar th.wg-day {
            padding: 8px 6px;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid #c9c9c9;
        }

        #transposedCalendar th.wg-day { background: #e8e8e8; color: #1d212f; }

        #transposedCalendar td.wg-day {
            width: 118px;
            background: #e4ffed;
            text-align: center;
            vertical-align: middle;
            padding: 6px 4px;
        }

        #transposedCalendar tr.wg-today td { background: #f3fff8; }
        #transposedCalendar tr.wg-today td.wg-day { background: #c9f7dc; }

        #transposedCalendar td.wg-cell { padding: 4px; }

        #transposedCalendar .wg-event {
            display: block;
            margin: 0 0 4px;
            padding: 4px 8px;
            background: #f6f7fb;
            border: 1px solid #e3e5ee;
            border-inline-start: 5px solid #B5B5B5;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
        }

        #transposedCalendar .wg-event:hover { filter: brightness(.95); }
        #transposedCalendar .wg-event[draggable] { cursor: grab; }
        #transposedCalendar .wg-event.wg-dragging { opacity: .45; }
        #transposedCalendar td.wg-cell.wg-drop { background: #e4ffed; outline: 2px dashed #298680; outline-offset: -3px; }
        #transposedCalendar .wg-title { display: block; font-size: 13px; font-weight: 700; color: #1d212f; }
        #transposedCalendar .wg-time { display: block; font-size: 12px; color: #5b6270; direction: ltr; }

        /* ---------- نوار جستجوی درون تقویم ---------- */
        .calendar-search-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .calendar-search-field {
            position: relative;
            flex: 0 1 340px;
        }

        .calendar-search-field input {
            padding-right: 34px;
            padding-left: 30px;
        }

        .calendar-search-field .cs-icon {
            position: absolute;
            top: 50%;
            right: 11px;
            transform: translateY(-50%);
            color: #9aa0b5;
            font-size: 14px;
            pointer-events: none;
        }

        .calendar-search-field .cs-clear {
            position: absolute;
            top: 50%;
            left: 8px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #8a90a6;
            font-size: 15px;
            line-height: 1;
            padding: 2px 4px;
            cursor: pointer;
        }

        .calendar-search-field .cs-clear:hover { color: #d9534f; }

        .calendar-search-info {
            font-size: 12px;
            color: #6c757d;
        }

        .calendar-search-info.is-empty { color: #d9534f; }

    </style>
    <?php if ($permission_calendar == 1) { ?>
        <?php if (isset($_SESSION['calendar_message'])): ?>
            <div class="alert alert-<?= $_SESSION['calendar_message']['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['calendar_message']['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['calendar_message']); ?>
        <?php endif; ?>
        <div class="mb-3 mt-3">
            <a class="btn text-center btn-info" href="calendar_groups.php"> مدیریت <?= $gw ?>‌ها </a>
            <button type="button" class="btn btn-primary" id="openAddEventBtn" data-bs-toggle="modal" data-bs-target="#addEventModal">
                + افزودن رویداد جدید
            </button>
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                📥 خروجی اکسل
            </button>
            <span class="menu-info-trigger ms-2"
                  data-tooltip="در این بخش می‌توانید تقویم اجرایی مجموعه را مدیریت کنید. امکان تعریف و ویرایش رویدادها، مدیریت <?= $gw ?>‌ها، مشاهده برنامه‌های زمان‌بندی‌شده و دریافت خروجی اکسل از اطلاعات تقویم در این قسمت فراهم شده است."
                  aria-label="راهنمای تقویم اجرایی">

        <i class="fe fe-info fs-4"></i>

    </span>

        </div>
    <?php } ?>
    <?php /* جستجو درون همین تقویم؛ رویدادهای ناهمخوان در نمای ماه، هفته و روز پنهان می شوند */ ?>
    <div class="calendar-search-bar">
        <div class="calendar-search-field">
            <i class="fe fe-search cs-icon"></i>
            <input type="text" id="calendarSearch" class="form-control"
                   placeholder="جستجو در تقویم (عنوان، توضیحات، تاریخ، <?= $gw ?> ...)" autocomplete="off">
            <button type="button" class="cs-clear" id="calendarSearchClear" title="پاک کردن جستجو" style="display:none;">&#10005;</button>
        </div>
        <span class="calendar-search-info" id="calendarSearchInfo"></span>
    </div>
    <div id="calendar"></div>

    <?php /* نمای جابجاشده روز/هفته: روزها سطر، ساعت ها ستون */ ?>
    <div id="transposedCalendar" style="display:none;"></div>
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
                                    <label for="groupSelect" class="form-label"><?= $gw ?></label>
                                    <select class="form-select" id="groupSelect" name="calendar_groups_id">
                                        <option value="">بدون <?= $gw ?> (مشکی)</option>
                                        <?php
                                        foreach ($weekGroups as $g):
                                            ?>
                                            <option style="color: <?= htmlspecialchars($g['color']) ?>;" value="<?= $g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="personal_note" class="form-label">یادداشت شخصی</label>
                                <textarea class="form-control" id="personal_note" name="personal_note" rows="2"></textarea>
                            </div>

                            <?php /* رویداد هفتگی: یک رویداد تکرارشونده در یک روز مشخص از هفته */ ?>
                            <style>
                                /* چیدمان مستقل از قالب: تیک دقیقاً کنار عنوان می نشیند */
                                #weeklyEventBlock .weekly-check {
                                    display: flex;
                                    align-items: center;
                                    gap: 8px;
                                    padding: 0;
                                    margin: 0;
                                }

                                #weeklyEventBlock .weekly-check input[type="checkbox"] {
                                    position: static !important;
                                    float: none !important;
                                    margin: 0 !important;
                                    flex: 0 0 18px;
                                    width: 18px;
                                    height: 18px;
                                    cursor: pointer;
                                }

                                #weeklyEventBlock .weekly-check label {
                                    margin: 0;
                                    cursor: pointer;
                                    user-select: none;
                                }
                            </style>
                            <div class="mb-3 p-3 rounded" id="weeklyEventBlock" style="background:#f7f7fb; border:1px solid #e6e6f0;">
                                <div class="weekly-check">
                                    <input type="checkbox" value="1" id="isWeekly" name="is_weekly">
                                    <label class="fw-bold" for="isWeekly">رویداد هفتگی</label>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    با فعال کردن این گزینه، رویداد در فاصله‌ی تاریخ شروع تا تاریخ پایان، هر هفته در روزِ
                                    انتخاب‌شده و با همان ساعتی که وارد کرده‌اید تکرار می‌شود؛ مانند کلاسی که هر هفته
                                    در یک روز و ساعت ثابت برگزار می‌شود.
                                </small>
                                <div class="mt-3" id="weeklyDayContainer" style="display:none;">
                                    <label for="weeklyDay" class="form-label">روز هفته</label>
                                    <select class="form-select" id="weeklyDay" name="weekly_day">
                                        <option value="6">شنبه</option>
                                        <option value="0">یکشنبه</option>
                                        <option value="1">دوشنبه</option>
                                        <option value="2">سه‌شنبه</option>
                                        <option value="3">چهارشنبه</option>
                                        <option value="4">پنج‌شنبه</option>
                                        <option value="5">جمعه</option>
                                    </select>
                                    <small class="text-muted d-block mt-1">
                                        در این حالت وارد کردن تاریخ پایان الزامی است. اولین جلسه، نخستین
                                        «روز هفته»ی انتخاب‌شده از تاریخ شروع به بعد است و ساعت شروع و پایانِ هر جلسه
                                        همان ساعت‌هایی است که در کادرهای تاریخ وارد می‌کنید.
                                    </small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">تاریخ شروع * (شمسی)</label>
                                    <div class="d-flex gap-2">
                                        <input type="text" class="form-control flex-grow-1" id="start_date" placeholder="۱۴۰۴/۰۱/۰۱" autocomplete="off" required>
                                        <input type="time" class="form-control" id="start_time" style="max-width: 120px;" value="00:00">
                                    </div>
                                    <input type="hidden" id="start_combined" name="start_date" value="">
                                    <small class="text-muted">ساعت را با انتخابگر یا تایپ دستی وارد کنید</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">تاریخ پایان (شمسی)</label>
                                    <div class="d-flex gap-2">
                                        <input type="text" class="form-control flex-grow-1" id="end_date" placeholder="۱۴۰۴/۰۱/۰۱" autocomplete="off">
                                        <input type="time" class="form-control" id="end_time" style="max-width: 120px;" value="00:00">
                                    </div>
                                    <input type="hidden" id="end_combined" name="end_date" value="">
                                    <small class="text-muted">اختیاری – در صورت عدم نیاز خالی بگذارید</small>
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
                                    <option value="2">کلاس</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="calendarKindSelect" class="form-label">دسته تقویم</label>
                                <select class="form-select" id="calendarKindSelect" name="is_academic">
                                    <option value="0">تقویم اجرایی</option>
                                    <option value="1">تقویم آموزشی</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="overlapSelect" class="form-label">همپوشانی</label>
                                <select class="form-select" id="overlapSelect" name="overlap">
                                    <option value="0">غیرفعال</option>
                                    <option value="1">فعال</option>
                                </select>
                                <small class="text-muted">در صورت فعال بودن، رویداد جدید با رویدادهای دارای همپوشانی یکسان و مخاطبان مشترک بررسی می‌شود.</small>
                            </div>

                            <div id="activeOptionsContainer" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label">گزینه‌های رویداد عملی</label>
                                    <div id="optionsList">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success mt-2" id="addOptionBtn">
                                        + افزودن گزینه
                                    </button>
                                    <small class="text-muted d-block">هر گزینه می‌تواند نیاز به توضیح داشته باشد (تیک بزنید).</small>
                                </div>
                            </div>

                            <div id="adminTargetsContainer">
                                <input type="hidden" id="selectedAdminRoles" name="admin_roles" value="">
                                <input type="hidden" id="selectedAdminUsers" name="admin_users" value="">
                                <input type="hidden" id="isAllAdmins" name="is_all_admins" value="0">

                                <div class="mb-3">
                                    <label class="form-label" for="adminRolesSelect">نقش‌های ادمین (چندگانه)</label>
                                    <select id="adminRolesSelect" class="form-control js-target-select" multiple
                                            data-placeholder="انتخاب نقش‌ها ...">
                                        <option value="all">همه ادمین‌ها</option>
                                        <option value="0">مدیریت</option>
                                        <option value="1">پشتیبان</option>
                                        <option value="2">مدیرمیانی</option>
                                        <option value="3"><?php echo t('role_teacher') ?></option>
                                        <option value="6">کارشناس فروش</option>
                                        <option value="51">معاونت اجرایی</option>
                                        <option value="52">معاونت آموزشی</option>
                                        <option value="53">معاونت فروش</option>
                                        <option value="54">معاونت فنی</option>
                                        <option value="55">معاونت مالی</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="adminUsersSelect">ادمین‌های خاص (چندگانه)</label>
                                    <select id="adminUsersSelect" class="form-control js-target-select" multiple
                                            data-placeholder="انتخاب ادمین‌ها ...">
                                        <?php
                                        $roleNames = [
                                            0  => 'مدیریت',
                                            1  => 'پشتیبان',
                                            2  => 'مدیرمیانی',
                                            3 => t('role_teacher'),
                                            6  => 'کارشناس فروش',
                                            51 => 'معاونت اجرایی',
                                            52 => 'معاونت آموزشی',
                                            53 => 'معاونت فروش',
                                            54 => 'معاونت فنی',
                                            55 => 'معاونت مالی',
                                        ];
                                        $stmt = $conn->query("SELECT idadmin, username, adminrole FROM dbadmin WHERE adminactive = 1 ORDER BY fname");
                                        while ($admin = $stmt->fetch(PDO::FETCH_OBJ)):
                                            $roleId = $admin->adminrole;
                                            $roleName = $roleNames[$roleId] ?? ('نقش ' . $roleId);
                                            ?>
                                            <option value="<?= $admin->idadmin ?>" data-role="<?= $roleId ?>">
                                                <?= htmlspecialchars($admin->username) ?> (<?= htmlspecialchars($roleName) ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">انتخاب شده‌ها:</label>
                                    <div id="selectedTargetsSummary" class="text-muted small" style="background:#f5f5f5; padding:8px; border-radius:6px;">هیچکدام</div>
                                </div>
                            </div>

                            <?php /* محدود کردن دید رویداد به گروه های آموزشی */ ?>
                            <div class="mb-3">
                                <label class="form-label" for="teacherGroupsSelect">گروه‌های آموزشی مجاز (چندگانه)</label>
                                <select id="teacherGroupsSelect" class="form-control js-target-select" multiple
                                        name="teacher_groups[]" data-placeholder="انتخاب گروه‌ها ...">
                                    <?php foreach (getTeacherGroups() as $tg): ?>
                                        <option value="<?= (int)$tg['id'] ?>"><?= htmlspecialchars($tg['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted"><?php echo m('help.teacher_group_filter') ?></small>
                            </div>

                            <div id="userTargetsContainer" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label" for="coursesSelect">پایه‌های هدف (چندگانه)</label>
                                    <select id="coursesSelect" class="form-control js-target-select" multiple
                                            data-placeholder="انتخاب پایه‌ها ...">
                                        <?php
                                        $courses = retCourse(1);
                                        if ($courses && count($courses) > 0):
                                            foreach ($courses as $course): ?>
                                                <option value="<?= $course->idcourse ?>">
                                                    <?= htmlspecialchars($course->coursename) ?>
                                                </option>
                                            <?php endforeach; endif; ?>
                                    </select>
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

                                <div id="exportAdminFilters">
                                    <div class="mb-3">
                                        <label class="form-label" for="exportRolesSelect">نقش‌های ادمین (چندگانه)</label>
                                        <select id="exportRolesSelect" class="form-control js-target-select" multiple
                                                data-placeholder="انتخاب نقش‌ها ...">
                                            <option value="0">مدیریت</option>
                                            <option value="1">پشتیبان</option>
                                            <option value="2">مدیرمیانی</option>
                                            <option value="3"><?php echo t('role_teacher') ?></option>
                                            <option value="6">کارشناس فروش</option>
                                            <option value="51">معاونت اجرایی</option>
                                            <option value="52">معاونت آموزشی</option>
                                            <option value="53">معاونت فروش</option>
                                            <option value="54">معاونت فنی</option>
                                            <option value="55">معاونت مالی</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="exportAdminsSelect">ادمین‌های خاص (چندگانه)</label>
                                        <select id="exportAdminsSelect" class="form-control js-target-select" multiple
                                                data-placeholder="انتخاب ادمین‌ها ...">
                                            <?php
                                            $roleNames = [
                                                0 => 'مدیریت', 1 => 'پشتیبان', 2 => 'مدیرمیانی', 3 => t('role_teacher'),
                                                6 => 'کارشناس فروش', 51 => 'معاونت اجرایی', 52 => 'معاونت آموزشی',
                                                53 => 'معاونت فروش', 54 => 'معاونت فنی', 55 => 'معاونت مالی'
                                            ];
                                            $stmt = $conn->query("SELECT idadmin, username, adminrole FROM dbadmin WHERE adminactive = 1 ORDER BY fname");
                                            while ($admin = $stmt->fetch(PDO::FETCH_OBJ)):
                                                $roleId = $admin->adminrole;
                                                $roleName = $roleNames[$roleId] ?? ('نقش ' . $roleId);
                                                ?>
                                                <option value="<?= $admin->idadmin ?>" data-role="<?= $roleId ?>">
                                                    <?= htmlspecialchars($admin->username) ?> (<?= htmlspecialchars($roleName) ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <small class="text-muted">در صورت عدم انتخاب نقش و ادمین، رویدادهای همه ادمین‌ها نمایش داده می‌شوند.</small>
                                    </div>
                                </div>

                                <div id="exportUserFilters" style="display:none;">
                                    <div class="mb-3">
                                        <label class="form-label" for="exportCoursesSelect">پایه‌های هدف (چندگانه)</label>
                                        <select id="exportCoursesSelect" class="form-control js-target-select" multiple
                                                data-placeholder="انتخاب پایه‌ها ...">
                                            <?php
                                            $courses = retCourse(1);
                                            if ($courses && count($courses) > 0):
                                                foreach ($courses as $course): ?>
                                                    <option value="<?= $course->idcourse ?>"><?= htmlspecialchars($course->coursename) ?></option>
                                                <?php endforeach; endif; ?>
                                        </select>
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
        const requestedEventId = new URLSearchParams(window.location.search).get('event');
        let requestedEventHandled = false;
        let courseSelect = null;
        var courseNameMap = {};
        // Active event options handling
        const eventTypeSelect = document.getElementById('eventTypeSelect'); // actually the select for event_type
        const activeOptionsContainer = document.getElementById('activeOptionsContainer');
        const optionsList = document.getElementById('optionsList');
        const addOptionBtn = document.getElementById('addOptionBtn');
        let optionCounter = 0;

        function addOptionRow(optionText = '', needsReason = false) {
            const row = document.createElement('div');
            row.className = 'row align-items-center mb-2';
            row.dataset.index = optionCounter++;

            // Create the toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'btn btn-sm toggle-reason';
            toggleBtn.dataset.reason = needsReason ? '1' : '0';

            // Set initial style and text
            function updateButton() {
                const isOn = toggleBtn.dataset.reason === '1';
                toggleBtn.className = `btn btn-sm toggle-reason ${isOn ? 'btn-success' : 'btn-danger'}`;
                toggleBtn.textContent = isOn ? '✔ توضیح' : '✘ توضیح';
            }
            updateButton();

            // Toggle on click
            toggleBtn.addEventListener('click', function() {
                this.dataset.reason = this.dataset.reason === '1' ? '0' : '1';
                updateButton.call(this);
            });

            row.innerHTML = `
        <div class="col-md-6">
            <input type="text" class="form-control option-text" placeholder="متن گزینه" value="${optionText}">
        </div>
        <div class="col-md-3">
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-sm btn-danger remove-option-btn">×</button>
        </div>
    `;

            const middleCol = row.querySelector('.col-md-3:not(:last-child)');
            middleCol.appendChild(toggleBtn);

            row.querySelector('.remove-option-btn').addEventListener('click', function() {
                row.remove();
            });

            optionsList.appendChild(row);
        }
        if (eventTypeSelect && addOptionBtn) {
            addOptionRow('', false);

            addOptionBtn.addEventListener('click', function() {
                addOptionRow('', false);
            });

            eventTypeSelect.addEventListener('change', function() {
                if (this.value == '1') {
                    activeOptionsContainer.style.display = 'block';
                    if (optionsList.children.length === 0) {
                        addOptionRow('', false);
                    }
                } else {
                    activeOptionsContainer.style.display = 'none';
                }
            });

            eventTypeSelect.dispatchEvent(new Event('change'));
        }
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
            if (!selectedCoursesSummary || !selectedCoursesInput || !courseSelect) return;
            const selected = Array.from(courseSelect.selectedOptions);
            if (selected.length === 0) {
                selectedCoursesSummary.innerText = 'هیچکدام (همه کاربران)';
                selectedCoursesInput.value = '[]';
            } else {
                selectedCoursesSummary.innerText = 'پایه‌ها: ' + selected.map(o => o.textContent.trim()).join(', ');
                selectedCoursesInput.value = JSON.stringify(selected.map(o => o.value));
            }
            syncEmptySelectState(courseSelect);
        }

        function initCoursePills() {
            courseSelect = document.getElementById('coursesSelect');
            if (!courseSelect) return;  // exit silently if not present

            const hasSelect2 = !!(window.jQuery && jQuery.fn.select2);
            if (hasSelect2) {
                // مثل دراپ‌داون های نقش/ادمین، فقط بعد از نمایش مودال ساخته می شود
                window.__buildCourseSelect2 = function () {
                    if (jQuery(courseSelect).data('select2')) {
                        jQuery(courseSelect).select2('destroy');
                    }
                    jQuery(courseSelect).select2({
                        width: '100%',
                        dir: 'rtl',
                        dropdownParent: jQuery('#addEventModal').length
                            ? jQuery('#addEventModal')
                            : jQuery(document.body),
                        placeholder: courseSelect.getAttribute('data-placeholder') || '',
                        allowClear: true,
                        closeOnSelect: false
                    });
                    // کلاس «select2» عمداً دست نخورده می ماند؛ برای بسته شدن
                    // دراپ‌داون با کلیک بیرون لازم است.
                    syncEmptySelectState(courseSelect);
                };

                jQuery(courseSelect).off('change.targets').on('change.targets', updateCoursesSummary);
            } else {
                courseSelect.addEventListener('change', updateCoursesSummary);
            }

            updateCoursesSummary();
        }

        // انتخاب پایه های ذخیره شده هنگام ویرایش رویداد
        function applyStoredCourses(storedCourses) {
            if (!courseSelect) return;
            const wanted = (storedCourses || []).map(String);
            Array.from(courseSelect.options).forEach(opt => {
                opt.selected = wanted.includes(String(opt.value));
            });
            if (window.jQuery && jQuery(courseSelect).data('select2')) {
                jQuery(courseSelect).trigger('change.select2');
            }
            updateCoursesSummary();
        }

        if (document.getElementById('userTargetsContainer')) {
            initCoursePills();
        }

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

        // ------------------------------ Admin target selects ------------------------------
        // نقش ها و ادمین ها به صورت دو دراپ‌داون چندانتخابی هستند.
        // قواعد قبلی حفظ شده است:
        //  - «همه ادمین‌ها» انحصاری است و is_all_admins را ۱ می کند
        //  - اگر نقشی انتخاب شود، ادمین های همان نقش غیرفعال می شوند (نقش پوشش شان می دهد)
        //  - اگر ادمینی انتخاب شود، نقش او غیرفعال می شود
        let roleSelect = null;
        let adminSelect = null;

        // کلاس is-empty روی کانتینر، حالت «هیچ انتخابی نشده» را مشخص می کند
        // تا فقط در همان حالت کادر جستجو تمام عرض را بگیرد.
        function syncEmptySelectState(sel) {
            if (!sel || !window.jQuery) return;
            const inst = jQuery(sel).data('select2');
            if (!inst || !inst.$container) return;
            inst.$container.toggleClass('is-empty', sel.selectedOptions.length === 0);
        }

        function tsValues(sel) {
            return sel ? Array.from(sel.selectedOptions).map(o => o.value) : [];
        }

        function tsLabel(sel, value) {
            if (!sel) return value;
            const opt = Array.from(sel.options).find(o => o.value === value);
            return opt ? opt.textContent.trim() : value;
        }

        // select2 بعد از تغییر برنامه ای باید رفرش شود
        function tsRefreshWidget(sel) {
            if (sel && window.jQuery && jQuery(sel).data('select2')) {
                jQuery(sel).trigger('change.select2');
            }
        }

        function updateTargetsSummary() {
            if (!adminContainer) return;

            const allAdmins = isAllAdminsInput && isAllAdminsInput.value === '1';
            const selectedRoles = allAdmins ? [] : tsValues(roleSelect).filter(v => v !== 'all');
            const selectedAdmins = allAdmins ? [] : tsValues(adminSelect);

            const rolesField = document.getElementById('selectedAdminRoles');
            const usersField = document.getElementById('selectedAdminUsers');
            if (rolesField) rolesField.value = JSON.stringify(selectedRoles);
            if (usersField) usersField.value = JSON.stringify(selectedAdmins);

            const summaryDiv = document.getElementById('selectedTargetsSummary');
            if (!summaryDiv) return;

            if (allAdmins) {
                summaryDiv.innerText = 'همه ادمین‌ها';
                syncEmptySelectState(roleSelect);
                syncEmptySelectState(adminSelect);
                return;
            }
            const parts = [];
            if (selectedRoles.length) {
                parts.push('نقش‌ها: ' + selectedRoles.map(r => tsLabel(roleSelect, r)).join(', '));
            }
            if (selectedAdmins.length) {
                parts.push('ادمین‌ها: ' + selectedAdmins.map(a => tsLabel(adminSelect, a)).join(', '));
            }
            summaryDiv.innerText = parts.length ? parts.join(' | ') : 'هیچکدام';
            syncEmptySelectState(roleSelect);
            syncEmptySelectState(adminSelect);
        }

        // نقش ها و ادمین های خاص همدیگر را محدود نمی کنند.
        // شرط سمت سرور «نقش انتخاب شده یا شخص انتخاب شده» است، پس می توان یک نقش
        // را انتخاب کرد و علاوه بر آن چند نفر مشخص (حتی از همان نقش) را هم اضافه کرد.
        // این دو تابع فقط مطمئن می شوند هیچ گزینه ای قفل نمانده باشد.
        function filterAdminOptionsBySelectedRoles() {
            if (!adminSelect) return;
            Array.from(adminSelect.options).forEach(opt => { opt.disabled = false; });
        }

        function filterRoleOptionsBySelectedAdmins() {
            if (!roleSelect) return;
            Array.from(roleSelect.options).forEach(opt => {
                if (opt.value !== 'all') opt.disabled = false;
            });
        }

        function setAllAdminsMode(on) {
            if (isAllAdminsInput) isAllAdminsInput.value = on ? '1' : '0';

            if (on) {
                // «همه» انحصاری است: بقیه نقش ها و همه ادمین ها پاک و غیرفعال می شوند
                Array.from(roleSelect.options).forEach(opt => {
                    if (opt.value !== 'all') { opt.selected = false; opt.disabled = true; }
                });
                Array.from(adminSelect.options).forEach(opt => { opt.selected = false; });
                adminSelect.disabled = true;
                tsRefreshWidget(roleSelect);
                tsRefreshWidget(adminSelect);
            } else {
                Array.from(roleSelect.options).forEach(opt => { opt.disabled = false; });
                adminSelect.disabled = false;
                filterAdminOptionsBySelectedRoles();
                filterRoleOptionsBySelectedAdmins();
                tsRefreshWidget(roleSelect);
                tsRefreshWidget(adminSelect);
            }
        }

        function refreshPillState() {
            if (!adminContainer) return;
            if (isAllAdminsInput && isAllAdminsInput.value === '1') {
                updateTargetsSummary();
                return;
            }
            filterAdminOptionsBySelectedRoles();
            filterRoleOptionsBySelectedAdmins();
            updateTargetsSummary();
        }

        // برای سازگاری با کدهای قبلی نگه داشته شده اند
        function showAllPills() { }
        function hideNonAllPills() { }

        function initPills() {
            if (!adminContainer) return;
            roleSelect = document.getElementById('adminRolesSelect');
            adminSelect = document.getElementById('adminUsersSelect');
            if (!roleSelect || !adminSelect) return;

            const hasSelect2 = !!(window.jQuery && jQuery.fn.select2);

            function buildTargetSelect2() {
                const groupSel = document.getElementById('teacherGroupsSelect');
                [roleSelect, adminSelect, groupSel].filter(Boolean).forEach(function (el) {
                    // اگر قبلاً ساخته شده، اول نابود شود تا ویجت تکراری جا نماند
                    if (jQuery(el).data('select2')) {
                        jQuery(el).select2('destroy');
                    }
                    jQuery(el).select2({
                        width: '100%',
                        dir: 'rtl',
                        dropdownParent: jQuery('#addEventModal').length
                            ? jQuery('#addEventModal')
                            : jQuery(document.body),
                        placeholder: el.getAttribute('data-placeholder') || '',
                        allowClear: true,
                        closeOnSelect: false
                    });

                    syncEmptySelectState(el);

                    // نکته: کلاس «select2» روی کانتینر باید بماند؛ خود select2 برای
                    // تشخیص کلیک بیرون از کادر به آن نیاز دارد و بدون آن دراپ‌داون
                    // با کلیک روی جای دیگر بسته نمی شود. چون ویجت بعد از نمایش مودال
                    // ساخته می شود، اسکریپت سراسری قالب دیگر آن را اسکن نمی کند.
                });

                // پاک کردن ویجت های تکراری احتمالی
                jQuery('#adminTargetsContainer .select2-container').each(function () {
                    const prev = this.previousElementSibling;
                    if (!prev || prev.tagName !== 'SELECT') jQuery(this).remove();
                });
            }

            // ویجت ها وقتی ساخته می شوند که مودال واقعاً نمایش داده شده باشد.
            // اگر موقع مخفی بودن مودال ساخته شوند، select2 عرض ها را صفر حساب می کند
            // و کادر جستجو (و در نتیجه placeholder) به اندازه یک حرف باریک می ماند.
            if (hasSelect2) {
                window.__buildTargetSelect2 = buildTargetSelect2;
            }

            function onRolesChanged() {
                if (tsValues(roleSelect).includes('all')) {
                    setAllAdminsMode(true);
                    updateTargetsSummary();
                    return;
                }
                if (isAllAdminsInput && isAllAdminsInput.value === '1') setAllAdminsMode(false);
                refreshPillState();
            }

            function onAdminsChanged() {
                if (isAllAdminsInput) isAllAdminsInput.value = '0';
                refreshPillState();
            }

            // select2 رویداد change را از طریق jQuery می فرستد و به addEventListener نمی رسد،
            // پس وقتی select2 فعال است حتماً با jQuery گوش می دهیم.
            if (hasSelect2) {
                jQuery(roleSelect).off('change.targets').on('change.targets', onRolesChanged);
                jQuery(adminSelect).off('change.targets').on('change.targets', onAdminsChanged);
            } else {
                roleSelect.addEventListener('change', onRolesChanged);
                adminSelect.addEventListener('change', onAdminsChanged);
            }

            if (isAllAdminsInput && isAllAdminsInput.value === '1') {
                const allOpt = Array.from(roleSelect.options).find(o => o.value === 'all');
                if (allOpt) allOpt.selected = true;
                setAllAdminsMode(true);
                tsRefreshWidget(roleSelect);
                updateTargetsSummary();
            } else {
                refreshPillState();
            }
        }

        // مقداردهی انتخاب ها هنگام ویرایش رویداد
        function applyStoredTargets(storedRolesStr, storedUsersStr) {
            if (!roleSelect || !adminSelect) return;

            const allOpt = Array.from(roleSelect.options).find(o => o.value === 'all');

            if (storedRolesStr.length > 0 || storedUsersStr.length > 0) {
                if (isAllAdminsInput) isAllAdminsInput.value = '0';
                if (allOpt) allOpt.selected = false;
                Array.from(roleSelect.options).forEach(opt => {
                    opt.disabled = false;
                    if (opt.value !== 'all') opt.selected = storedRolesStr.includes(opt.value);
                });
                adminSelect.disabled = false;
                Array.from(adminSelect.options).forEach(opt => {
                    opt.disabled = false;
                    opt.selected = storedUsersStr.includes(opt.value);
                });
            } else {
                if (allOpt) allOpt.selected = true;
                setAllAdminsMode(true);
            }

            tsRefreshWidget(roleSelect);
            tsRefreshWidget(adminSelect);
            refreshPillState();
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

            const fullStart = event.extendedProps.jalali_start || '';
            const fullEnd = event.extendedProps.jalali_end || '';
            const storedRoles = event.extendedProps.admin_roles || [];
            const storedUsers = event.extendedProps.admin_users || [];
            const storedCourses = event.extendedProps.course_targets || [];
            const visibility = event.extendedProps.visibility || 0;

            // ---- Split date/time ----
            if (fullStart) {
                const parts = fullStart.split(' ');
                document.getElementById('start_date').value = parts[0] || '';
                document.getElementById('start_time').value = parts[1] || '00:00';
            } else {
                document.getElementById('start_date').value = '';
                document.getElementById('start_time').value = '00:00';
            }
            if (fullEnd) {
                const parts = fullEnd.split(' ');
                document.getElementById('end_date').value = parts[0] || '';
                document.getElementById('end_time').value = parts[1] || '00:00';
            } else {
                document.getElementById('end_date').value = '';
                document.getElementById('end_time').value = '00:00';
            }
            updateCombinedDateTime();

            // ---- Ensure the target selects are initialised ----
            if (!roleSelect || !adminSelect) {
                initPills();
            }

            // Convert stored IDs to strings to match option values
            const storedRolesStr = storedRoles.map(String);
            const storedUsersStr = storedUsers.map(String);

            // انتخاب های ذخیره شده روی دو دراپ‌داون اعمال می شوند
            applyStoredTargets(storedRolesStr, storedUsersStr);

            // گروه های آموزشی مجاز این رویداد
            const groupSel = document.getElementById('teacherGroupsSelect');
            if (groupSel) {
                const storedGroups = (event.extendedProps.teacher_groups || []).map(String);
                Array.from(groupSel.options).forEach(function (opt) {
                    opt.selected = storedGroups.includes(String(opt.value));
                });
                if (window.jQuery && jQuery(groupSel).data('select2')) {
                    jQuery(groupSel).trigger('change.select2');
                }
                syncEmptySelectState(groupSel);
            }

            // ---- Ensure the course select is initialised ----
            if (!courseSelect) {
                initCoursePills();
            }
            // پایه های ذخیره شده روی دراپ‌داون اعمال می شوند
            applyStoredCourses(storedCourses);

            // ---- Set form fields ----
            document.getElementById('addEventModalLabel').innerText = 'ویرایش رویداد';
            document.getElementById('editEventId').value = eventId;
            document.getElementById('title').value = event.title;
            document.getElementById('description').value = event.extendedProps.description || '';
            document.getElementById('personal_note').value = event.extendedProps.personal_note || '';
            document.getElementById('groupSelect').value = event.extendedProps.calendar_groups_id || '';
            document.getElementById('formAction').value = 'update_event';
            if (typeof window.__setWeeklyBlockVisible === 'function') {
                window.__setWeeklyBlockVisible(false);
            }
            document.getElementById('formEventId').value = eventId;
            document.getElementById('visibilitySelect').value = visibility;
            document.getElementById('eventTypeSelect').value = event.extendedProps.event_type || 0;
            document.getElementById('calendarKindSelect').value = event.extendedProps.is_academic || 0;
            document.getElementById('overlapSelect').value = event.extendedProps.overlap || 0;

            // ---- Load active options (if event_type == 1) ----
            const storedOptions = event.extendedProps.options || [];
            optionsList.innerHTML = '';
            if (storedOptions.length === 0) {
                addOptionRow('', false);
            } else {
                storedOptions.forEach(opt => {
                    addOptionRow(opt.option, opt.needs_reason == 1);
                });
            }
            eventTypeSelect.dispatchEvent(new Event('change'));

            // ---- Set target type and show/hide containers ----
            const targetType = event.extendedProps.target_type || 'admin';
            if (targetTypeSelect) {
                targetTypeSelect.value = targetType;
                targetTypeSelect.dispatchEvent(new Event('change'));

            }

            // ---- Finally, show the modal ----
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
        }

        // ---- کمکی های کنتراست رنگ ----
        function channelLuminance(value) {
            const c = value / 255;
            return (c <= 0.03928) ? (c / 12.92) : Math.pow((c + 0.055) / 1.055, 2.4);
        }

        function relativeLuminance(r, g, b) {
            return 0.2126 * channelLuminance(r)
                 + 0.7152 * channelLuminance(g)
                 + 0.0722 * channelLuminance(b);
        }

        function contrastRatio(l1, l2) {
            const hi = Math.max(l1, l2), lo = Math.min(l1, l2);
            return (hi + 0.05) / (lo + 0.05);
        }

        // روشنایی رنگ متن تیره (#2f3245)
        const DARK_TEXT_LUM = relativeLuminance(47, 50, 69);

        // ------------------------------------------------------------------
        // نمای جابجاشده برای روز و هفته
        // در تقویم استاندارد، ساعت ها در ستون و روزها در سطر هستند.
        // اینجا برعکس می شود: هر روز یک سطر و ساعت ها ستون های آن سطر.
        // محور زمان راست به چپ است، یعنی ساعت های ابتدایی سمت راست.
        // ------------------------------------------------------------------
        var CAL_GROUP_WORD = <?= json_encode($gw, JSON_UNESCAPED_UNICODE) ?>;
        var CAL_WEEK_GROUPS = <?= json_encode(array_map(static fn($g) => ['id' => (int)$g['id'], 'title' => $g['title'], 'color' => $g['color']], $weekGroups), JSON_UNESCAPED_UNICODE) ?>;
        var TC_MONTH_HEIGHT = 500; // همان ارتفاعی که تقویم با آن ساخته می شود
        var TC_DAY_NAMES = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

        function tcEscape(text) {
            var d = document.createElement('div');
            d.textContent = (text === null || text === undefined) ? '' : String(text);
            return d.innerHTML.replace(/"/g, '&quot;');
        }

        function tcDayLabel(date) {
            return TC_DAY_NAMES[date.getDay()]; // 0=Sunday .. 6=Saturday
        }

        function tcJalaliDate(date) {
            try {
                return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
                    month: '2-digit', day: '2-digit'
                }).format(date);
            } catch (e) {
                return (date.getMonth() + 1) + '/' + date.getDate();
            }
        }

        function tcSameDay(a, b) {
            return a.getFullYear() === b.getFullYear()
                && a.getMonth() === b.getMonth()
                && a.getDate() === b.getDate();
        }

        function tcMinutes(date) {
            return date.getHours() * 60 + date.getMinutes();
        }

        // بازه ساعت ها بر اساس رویدادهای همان بازه تعیین می شود (پیش فرض ۸ تا ۱۸)
        function tcHourRange(events) {
            var min = 8, max = 18;
            events.forEach(function (ev) {
                if (ev.allDay || !ev.start) return;
                var sh = ev.start.getHours();
                var eh = ev.end ? Math.ceil(tcMinutes(ev.end) / 60) : sh + 1;
                if (sh < min) min = sh;
                if (eh > max) max = eh;
            });
            min = Math.max(0, Math.min(min, 23));
            max = Math.min(24, Math.max(max, min + 1));
            return { min: min, max: max };
        }

        // رنگ گروه ها به صورت هگز (#03d100) می آید و رنگ محاسبه شده مرورگر به صورت rgb()
        // پس هر دو حالت باید پشتیبانی شود.
        function tcParseColor(color) {
            if (!color) return null;
            var c = String(color).trim();

            var hex = c.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
            if (hex) {
                var h = hex[1];
                if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
                return [
                    parseInt(h.substr(0, 2), 16),
                    parseInt(h.substr(2, 2), 16),
                    parseInt(h.substr(4, 2), 16)
                ];
            }

            var nums = c.match(/[\d.]+/g);
            if (nums && nums.length >= 3) {
                if (nums.length >= 4 && Number(nums[3]) === 0) return null; // شفاف
                return [Number(nums[0]), Number(nums[1]), Number(nums[2])];
            }
            return null;
        }

        function tcTextColorFor(bg) {
            var rgb = tcParseColor(bg);
            if (!rgb) return '#ffffff';
            var lum = relativeLuminance(rgb[0], rgb[1], rgb[2]);
            return contrastRatio(lum, DARK_TEXT_LUM) >= contrastRatio(lum, 1) ? '#2f3245' : '#ffffff';
        }

        // ------------------------------ جستجو درون تقویم ------------------------------
        // رویدادهایی که با متن جستجو نمی خوانند در همه ی نماها (ماه، هفته، روز) پنهان می شوند.
        var calendarSearchTerm = '';

        // ارقام فارسی و عربی به انگلیسی، ی و ک عربی به فارسی و نیم فاصله به فاصله تبدیل می شود
        // تا نوشتن «۱۴۰۵» و «1405» یا «رياضي» و «ریاضی» نتیجه ی یکسان بدهد.
        function normalizeSearchText(value) {
            return (value === null || value === undefined ? '' : String(value))
                .replace(/[\u06F0-\u06F9]/g, function (d) { return String.fromCharCode(d.charCodeAt(0) - 0x06F0 + 48); })
                .replace(/[\u0660-\u0669]/g, function (d) { return String.fromCharCode(d.charCodeAt(0) - 0x0660 + 48); })
                .replace(/[\u064A\u0649]/g, '\u06CC')
                .replace(/\u0643/g, '\u06A9')
                .replace(/[\u200B-\u200F]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
        }

        // هر چیزی که در تقویم درباره ی رویداد دیده می شود، قابل جستجو است
        function eventSearchHaystack(ev) {
            if (ev.__searchText !== undefined) return ev.__searchText;
            var p = ev.extendedProps || {};
            var options = (p.options || []).map(function (o) { return o.option || ''; }).join(' ');
            ev.__searchText = normalizeSearchText([
                ev.title,
                p.description,
                p.personal_note,
                p.group_title,
                p.jalali_start,
                p.jalali_end,
                p.target_summary,
                options
            ].join(' '));
            return ev.__searchText;
        }

        function eventMatchesSearch(ev) {
            if (!calendarSearchTerm) return true;
            return eventSearchHaystack(ev).indexOf(calendarSearchTerm) !== -1;
        }

        // فهرست رویدادهای قابل نمایش؛ نمای جابجاشده (هفته و روز) از همین استفاده می کند
        function visibleCalendarEvents() {
            if (!calendar) return [];
            return calendar.getEvents().filter(eventMatchesSearch);
        }

        // نمای ماه را خود FullCalendar می سازد، پس رویداد ناهمخوان با display خاموش می شود
        function applyCalendarSearchDisplay() {
            if (!calendar) return { shown: 0, total: 0 };
            var shown = 0;
            var all = calendar.getEvents();
            all.forEach(function (ev) {
                var match = eventMatchesSearch(ev);
                if (match) shown++;
                var want = match ? 'auto' : 'none';
                // فقط وقتی تغییر می دهیم که لازم باشد، وگرنه eventsSet بی پایان صدا زده می شود
                if (ev.display !== want) ev.setProp('display', want);
            });
            return { shown: shown, total: all.length };
        }

        function updateCalendarSearchInfo(stats) {
            var info = document.getElementById('calendarSearchInfo');
            if (!info) return;
            if (!calendarSearchTerm) {
                info.textContent = '';
                info.classList.remove('is-empty');
                return;
            }
            // شمارش روی همه ی رویدادهای تقویم است، نه فقط بازه ی نمایش داده شده
            info.textContent = stats.shown > 0
                ? (stats.shown + ' رویداد با این عبارت پیدا شد.')
                : 'رویدادی با این عبارت پیدا نشد.';
            info.classList.toggle('is-empty', stats.shown === 0);
        }

        // جستجو را روی نمای فعلی اعمال می کند
        function refreshCalendarSearch() {
            if (!calendar) return;
            var stats = applyCalendarSearchDisplay();
            var v = calendar.view;
            if (v && (v.type === 'timeGridWeek' || v.type === 'timeGridDay')) {
                renderTimeView(v, visibleCalendarEvents());
            }
            updateCalendarSearchInfo(stats);
        }

        // هفته: روزها در سطر و پایه ها (یا دوره ها) در ستون، مثل جدول برنامه ی هفتگی.
        // روز: همان نمای ساعت به ساعت.
        function renderTimeView(view, events) {
            if (view.type === 'timeGridWeek') {
                renderWeekByGroup(view.activeStart, view.activeEnd, events);
            } else {
                renderTransposed(view.activeStart, view.activeEnd, events);
            }
        }

        function wgTime(d) {
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }

        function renderWeekByGroup(viewStart, viewEnd, events) {
            var host = document.getElementById('transposedCalendar');
            if (!host) return;

            var days = [];
            var cur = new Date(viewStart.getFullYear(), viewStart.getMonth(), viewStart.getDate());
            while (cur < viewEnd) {
                days.push(new Date(cur));
                cur.setDate(cur.getDate() + 1);
            }

            // ستون ها: همه ی پایه ها؛ ستون «بدون پایه» فقط وقتی رویدادی بدون پایه در این هفته هست
            var known = {};
            CAL_WEEK_GROUPS.forEach(function (g) { known[g.id] = true; });
            var groupOf = function (ev) {
                var id = parseInt(ev.extendedProps.calendar_groups_id, 10);
                return known[id] ? id : 0;
            };
            var inWeek = function (ev) {
                if (!ev.start) return false;
                var e = ev.end || new Date(ev.start.getTime() + 1);
                return ev.start < viewEnd && e > viewStart;
            };
            var columns = CAL_WEEK_GROUPS.slice();
            if (events.some(function (ev) { return inWeek(ev) && groupOf(ev) === 0; })) {
                columns.push({ id: 0, title: 'بدون ' + CAL_GROUP_WORD, color: '#B5B5B5' });
            }
            if (!columns.length) {
                host.innerHTML = '<div class="tc-empty">هنوز ' + tcEscape(CAL_GROUP_WORD) + 'ای تعریف نشده است؛ از «مدیریت '
                    + tcEscape(CAL_GROUP_WORD) + '‌ها» آن‌ها را بسازید.</div>';
                return;
            }

            var today = new Date();
            var html = '<table class="wg-table" style="min-width:' + (130 + columns.length * 170) + 'px">';
            html += '<thead><tr><th class="wg-day">روز</th>';
            columns.forEach(function (g) {
                html += '<th class="wg-head" style="background:' + tcEscape(g.color) + ';color:' + tcTextColorFor(g.color) + '">'
                      + tcEscape(g.title) + '</th>';
            });
            html += '</tr></thead><tbody>';

            days.forEach(function (day) {
                var dayStart = new Date(day.getFullYear(), day.getMonth(), day.getDate());
                var dayEnd = new Date(dayStart.getTime() + 86400000);
                var dayEvents = events.filter(function (ev) {
                    if (!ev.start) return false;
                    var e = ev.end || new Date(ev.start.getTime() + 1);
                    return ev.start < dayEnd && e > dayStart;
                }).sort(function (a, b) {
                    return (b.allDay - a.allDay) || (a.start - b.start);
                });

                html += '<tr class="' + (tcSameDay(day, today) ? 'wg-today' : '') + '">';
                html += '<td class="wg-day"><span class="tc-dayname">' + tcDayLabel(day) + '</span>'
                      + '<span class="tc-daydate">' + tcJalaliDate(day) + '</span></td>';
                var dayKey = day.getFullYear() + '-' + (day.getMonth() + 1) + '-' + day.getDate();
                columns.forEach(function (g) {
                    html += '<td class="wg-cell" data-day="' + dayKey + '" data-group="' + g.id + '">';
                    dayEvents.filter(function (ev) { return groupOf(ev) === g.id; }).forEach(function (ev) {
                        var bg = ev.backgroundColor || ev.borderColor || g.color || '#B5B5B5';
                        var when = ev.allDay ? 'تمام روز'
                            : (ev.end ? wgTime(ev.start) + ' - ' + wgTime(ev.end) : wgTime(ev.start));
                        html += '<div class="wg-event" data-ev="' + tcEscape(ev.id) + '" data-group="' + g.id + '"'
                              + (hasCalendarPermission ? ' draggable="true"' : '') + ' style="border-inline-start-color:' + tcEscape(bg) + '"'
                              + ' title="' + tcEscape(ev.title) + '">'
                              + '<span class="wg-title">' + tcEscape(ev.title) + '</span>'
                              + '<span class="wg-time">' + when + '</span></div>';
                    });
                    html += '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            host.innerHTML = html;

            host.querySelectorAll('[data-ev]').forEach(function (node) {
                node.addEventListener('click', function () {
                    var ev = calendar.getEventById(node.getAttribute('data-ev'));
                    if (ev) showEventDetail(ev);
                });
            });
            if (hasCalendarPermission) wgEnableDrag(host);
        }

        // جابجایی جلسه با کشیدن: سطر دیگر روز را عوض می کند و ستون دیگر پایه/دوره را؛ ساعت همان می ماند
        var wgDragging = null;
        function wgEnableDrag(host) {
            host.querySelectorAll('.wg-event[draggable]').forEach(function (node) {
                node.addEventListener('dragstart', function (e) {
                    wgDragging = { id: node.getAttribute('data-ev'), group: node.getAttribute('data-group') };
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', wgDragging.id);
                    node.classList.add('wg-dragging');
                });
                node.addEventListener('dragend', function () {
                    node.classList.remove('wg-dragging');
                    host.querySelectorAll('.wg-drop').forEach(function (c) { c.classList.remove('wg-drop'); });
                    wgDragging = null;
                });
            });
            host.querySelectorAll('td.wg-cell').forEach(function (cell) {
                var accepts = function () { return !!wgDragging; };
                cell.addEventListener('dragover', function (e) {
                    if (!accepts()) return;
                    e.preventDefault();
                    cell.classList.add('wg-drop');
                });
                cell.addEventListener('dragleave', function () { cell.classList.remove('wg-drop'); });
                cell.addEventListener('drop', function (e) {
                    if (!accepts()) return;
                    e.preventDefault();
                    cell.classList.remove('wg-drop');
                    var ev = calendar.getEventById(wgDragging.id);
                    if (!ev || !ev.start) return;
                    var p = cell.getAttribute('data-day').split('-').map(Number);
                    var target = new Date(p[0], p[1] - 1, p[2]);
                    var from = new Date(ev.start.getFullYear(), ev.start.getMonth(), ev.start.getDate());
                    var days = Math.round((target - from) / 86400000);
                    var group = cell.getAttribute('data-group');
                    if (!days && group === wgDragging.group) return;
                    wgMoveEvent(ev, days, group !== wgDragging.group ? group : null);
                });
            });
        }

        function wgLocalIso(d) {
            var pad = function (n) { return String(n).padStart(2, '0'); };
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
                + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        }

        function wgShift(d, days) {
            var n = new Date(d.getTime());
            n.setDate(n.getDate() + days); // تقویمی، تا تغییر ساعت رسمی ساعت جلسه را جابجا نکند
            return n;
        }

        function wgMoveEvent(ev, days, group) {
            var formData = new URLSearchParams();
            formData.append('action', 'update_event');
            formData.append('event_id', ev.id);
            formData.append('title', ev.title);
            formData.append('target_group', ev.extendedProps.target_type || 'admin');
            formData.append('start_greg', wgLocalIso(wgShift(ev.start, days)));
            formData.append('end_greg', ev.end ? wgLocalIso(wgShift(ev.end, days)) : '');
            formData.append('update_roles', '0');
            if (group !== null) formData.append('move_group', group);
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) calendar.refetchEvents();
                    else alert('خطا در جابجایی: ' + data.message);
                })
                .catch(function () { alert('خطا در ارتباط با سرور.'); });
        }

        function renderTransposed(viewStart, viewEnd, events) {
            var host = document.getElementById('transposedCalendar');
            if (!host) return;

            var days = [];
            var cur = new Date(viewStart.getFullYear(), viewStart.getMonth(), viewStart.getDate());
            while (cur < viewEnd) {
                days.push(new Date(cur));
                cur.setDate(cur.getDate() + 1);
            }

            if (!days.length) {
                host.innerHTML = '<div class="tc-empty">بازه‌ای برای نمایش وجود ندارد.</div>';
                return;
            }

            var range = tcHourRange(events);
            var hourCount = range.max - range.min;
            var totalMin = hourCount * 60;
            var today = new Date();

            var html = '<table style="--tc-hour-w: calc((100% - 108px) / ' + hourCount + ')">';
            html += '<thead><tr><th class="tc-day-head"><span class="tc-pill">روز</span></th>';
            for (var h = range.min; h < range.max; h++) {
                html += '<th><span class="tc-pill">' + String(h).padStart(2, '0') + ':00</span></th>';
            }
            html += '</tr></thead><tbody>';

            days.forEach(function (day) {
                var dayStart = new Date(day.getFullYear(), day.getMonth(), day.getDate());
                var dayEnd = new Date(dayStart.getTime() + 86400000);

                var dayEvents = events.filter(function (ev) {
                    if (!ev.start) return false;
                    var e = ev.end || new Date(ev.start.getTime() + 3600000);
                    return ev.start < dayEnd && e > dayStart;
                });

                var allDay = dayEvents.filter(function (ev) { return ev.allDay; });
                var timed = dayEvents.filter(function (ev) { return !ev.allDay; });

                // رویدادهای هم زمان در لِین های جداگانه می نشینند تا روی هم نیفتند
                var lanes = [];
                timed.forEach(function (ev) {
                    var s = Math.max(tcMinutes(ev.start), range.min * 60);
                    var e = ev.end ? tcMinutes(ev.end) : s + 60;
                    if (e <= s) e = s + 30;
                    e = Math.min(e, range.max * 60);
                    ev.__s = s;
                    ev.__e = e;
                    var placed = false;
                    for (var i = 0; i < lanes.length && !placed; i++) {
                        if (lanes[i][lanes[i].length - 1].__e <= s) {
                            lanes[i].push(ev);
                            placed = true;
                        }
                    }
                    if (!placed) lanes.push([ev]);
                });

                var laneCount = Math.max(1, lanes.length);
                var rowHeight = 16 + laneCount * 32; // ارتفاع سطر روز

                html += '<tr class="' + (tcSameDay(day, today) ? 'tc-today' : '') + '">';
                // ارتفاع سلول روز هم مثل ردیف ساعت ها ست می شود،
                // وگرنه height:100% قاب داخلی جایی برای محاسبه ندارد و زیر عنوان فضای خالی می ماند
                html += '<td class="tc-day-head" style="height:' + rowHeight + 'px"><div class="tc-frame">'
                      + '<span class="tc-dayname">' + tcDayLabel(day) + '</span>'
                      + '<span class="tc-daydate">' + tcJalaliDate(day) + '</span>';
                allDay.forEach(function (ev) {
                    var bg = ev.backgroundColor || ev.borderColor || '#B5B5B5';
                    html += '<span class="tc-allday" data-ev="' + tcEscape(ev.id) + '" style="background:'
                          + bg + ';color:' + tcTextColorFor(bg) + '" title="' + tcEscape(ev.title) + '">'
                          + tcEscape(ev.title) + '</span>';
                });
                html += '</div></td>';

                html += '<td class="tc-track" colspan="' + hourCount + '" style="height:' + rowHeight + 'px">';
                // لایه پس زمینه: به ازای هر ساعت یک قاب، هم شکل سلول های نمای ماه
                html += '<div class="tc-hours">';
                for (var hh = 0; hh < hourCount; hh++) { html += '<div class="tc-hour"></div>'; }
                html += '</div><div class="tc-events">';
                lanes.forEach(function (lane, laneIndex) {
                    lane.forEach(function (ev) {
                        var offset = ((ev.__s - range.min * 60) / totalMin) * 100;
                        var width = ((ev.__e - ev.__s) / totalMin) * 100;
                        var bg = ev.backgroundColor || ev.borderColor || '#B5B5B5';
                        var label = ev.start.toTimeString().slice(0, 5) + '  ' + ev.title;
                        html += '<div class="tc-event" data-ev="' + tcEscape(ev.id) + '"'
                              + ' style="right:' + offset + '%; width:' + width + '%; top:'
                              + (4 + laneIndex * 32) + 'px; background:' + bg
                              + '; color:' + tcTextColorFor(bg) + '"'
                              + ' title="' + tcEscape(label) + '">' + tcEscape(label) + '</div>';
                    });
                });
                html += '</div></td></tr>';
            });

            html += '</tbody></table>';
            host.innerHTML = html;

            // کلیک روی رویداد همان پنجره جزئیات تقویم اصلی را باز می کند
            host.querySelectorAll('[data-ev]').forEach(function (node) {
                node.addEventListener('click', function () {
                    var ev = calendar.getEventById(node.getAttribute('data-ev'));
                    if (ev) showEventDetail(ev);
                });
            });
        }

        // جابجایی بین تقویم استاندارد (ماه) و نمای جابجاشده (روز/هفته)
        function applyTransposedMode(info) {
            var host = document.getElementById('transposedCalendar');
            var calEl = document.getElementById('calendar');
            var harness = calEl ? calEl.querySelector('.fc-view-harness') : null;
            // در FullCalendar 5 کلاس fc روی خود عنصر #calendar قرار می گیرد، نه فرزند آن
            var fcRoot = calEl
                ? (calEl.classList.contains('fc') ? calEl : calEl.querySelector('.fc'))
                : null;
            if (!host || !harness || !fcRoot) return;

            var isTimeGrid = (info.view.type === 'timeGridWeek' || info.view.type === 'timeGridDay');

            // بدنه تقویم اصلی پنهان می شود ولی نوار بالا (ماه/هفته/روز) سر جایش می ماند
            harness.style.display = isTimeGrid ? 'none' : '';
            host.style.display = isTimeGrid ? '' : 'none';

            if (isTimeGrid) {
                // جدول جابجاشده درست زیر نوار ابزار و داخل همان کارت قرار می گیرد
                var toolbar = fcRoot.querySelector('.fc-header-toolbar');
                if (toolbar && host.parentNode !== fcRoot) {
                    toolbar.insertAdjacentElement('afterend', host);
                }
                // ارتفاع ثابت آزاد می شود تا زیر جدول فضای خالی نماند.
                // این کار از طریق خود آپشن تقویم انجام می شود، نه دستکاری style،
                // وگرنه محاسبات داخلی FullCalendar خراب شده و شبکه ماه با ارتفاع صفر برمی گردد.
                if (calendar.getOption('height') !== 'auto') {
                    calendar.setOption('height', 'auto');
                }
                renderTimeView(info.view, visibleCalendarEvents());
            } else {
                if (calendar.getOption('height') !== TC_MONTH_HEIGHT) {
                    calendar.setOption('height', TC_MONTH_HEIGHT);
                }
            }
        }

        // جزئیات رویداد؛ هم از تقویم اصلی و هم از نمای جابجاشده (روز/هفته) صدا زده می شود
        function showEventDetail(ev) {
                const desc = ev.extendedProps.description || '—';
                const note = ev.extendedProps.personal_note || '—';
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
                    const EVENT_TYPE_LABELS = { 0: 'عادی', 1: 'عملی', 2: 'کلاس' };
                    const eventTypeText = EVENT_TYPE_LABELS[eventType] || 'عادی';
                    eventTypeHtml = `<div style="margin-bottom:15px;"><strong>نوع رویداد:</strong> ${eventTypeText}</div>`;
                }

                const contentDiv = document.getElementById('eventDetailContent');
                if (contentDiv) {
                    contentDiv.innerHTML = `
            <div style="margin-bottom:15px;"><strong>عنوان:</strong> ${ev.title}</div>
            <div style="margin-bottom:15px;"><strong>توضیحات:</strong> ${desc}</div>
            <div style="margin-bottom:15px;"><strong>یادداشت شخصی:</strong> ${note}</div>
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
        }

        // متن راهنمای هاور رویداد: عنوان + بازه شمسی
        function buildEventTooltip(ev) {
            const title = ev.title || '';
            const start = ev.extendedProps.jalali_start || '';
            const end   = ev.extendedProps.jalali_end || '';
            if (!start) return title;
            return end ? (title + ' — ' + start + ' تا ' + end) : (title + ' — ' + start);
        }

        // ورود از اعلان: تقویم روی تاریخ همان رویداد قرار می‌گیرد، رویداد
        // برجسته می‌شود و پنجره جزئیات آن به‌صورت خودکار باز می‌شود.
        function focusRequestedEvent() {
            if (!requestedEventId || requestedEventHandled || !calendar) return;
            const requestedEvent = calendar.getEventById(String(requestedEventId));
            if (!requestedEvent) return;

            requestedEventHandled = true;
            if (requestedEvent.start) {
                calendar.gotoDate(requestedEvent.start);
            }
            setTimeout(function () {
                calendarEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                showEventDetail(requestedEvent);
            }, 180);
        }

        window.editEvent   = editEvent;
        window.deleteEvent = deleteEvent;
        // ------------------------------ Calendar initialisation ------------------------------
        calendar = new FullCalendar.Calendar(calendarEl, {
            height: 500,
            locale: 'fa',
            // تاریخ ها بدون افست ساعت از سرور می آیند (مثل 2026-09-17T08:00:00)
            // و با timeZone: 'local' دقیقاً همان ساعت ثبت شده نمایش داده می شود،
            // مستقل از منطقه زمانی سیستم کاربر.
            // در عوض خط «اکنون» و روز جاری با ساعت واقعی کاربر حساب می شوند
            // (با 'UTC' این دو به اندازه اختلاف منطقه زمانی جابجا می شدند).
            timeZone: 'local',
            // ساعت رویدادهای ساعت دار نمایش داده شود (رویداد تمام روز خودش ساعت ندارد)
            displayEventTime: true,
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
            // رویدادهای هم زمان به جای روی هم افتادن، کنار هم چیده شوند
            slotEventOverlap: false,
            nowIndicator: true,
            expandRows: true,
            scrollTime: '07:00:00',
            allDayText: 'تمام روز',
            initialView: 'dayGridMonth',
            firstDay: 6,     // 0 = Sunday, 6 = Saturday
            direction: 'rtl', // right-to-left layout
            slotLabelFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
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
            dateClick: function(info) {
                // info.dateStr is Gregorian 'YYYY-MM-DD'
                var gregDate = info.dateStr;
                // Open the popup – adjust the path to day_info.php as needed
                window.open(
                    'day_info.php?date=' + gregDate,
                    'DayInfo',
                    'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                );
            },
            editable: hasCalendarPermission,
            selectable: true,
            events: function(fetchInfo, successCallback, failureCallback) {
                const eventsUrl = new URL(window.location.href);
                eventsUrl.searchParams.set('get_events', '1');
                fetch(eventsUrl.toString())
                    .then(response => response.json())
                    .then(events => {
                        events.forEach(ev => {
                            ev.extendedProps = ev.extendedProps || {};
                            ev.extendedProps.description = ev.description;
                            ev.extendedProps.personal_note = ev.personal_note;
                            ev.extendedProps.jalali_start = ev.jalali_start;
                            ev.extendedProps.jalali_end = ev.jalali_end;
                            ev.extendedProps.target_summary = ev.target_summary;
                            ev.extendedProps.target_type = ev.target_type;
                            ev.extendedProps.admin_roles = ev.admin_roles || [];
                            ev.extendedProps.admin_users = ev.admin_users || [];
                            ev.extendedProps.visibility = ev.visibility;
                            ev.extendedProps.event_type = ev.event_type;
                            ev.extendedProps.is_academic = ev.is_academic || 0;
                            ev.extendedProps.options = ev.options || [];
                            ev.extendedProps.course_targets = ev.course_targets || [];
                            ev.extendedProps.overlap = ev.overlap || 0;
                            ev.extendedProps.teacher_groups = ev.teacher_groups || [];
                        });
                        console.log(events)
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
            eventDidMount: function(info) {
                const el = info.el;

                if (requestedEventId && String(info.event.id) === String(requestedEventId)) {
                    el.classList.add('calendar-event-focus');
                }

                // رویدادهای نقطه ای نمای ماه پس زمینه رنگی ندارند
                if (el.classList.contains('fc-daygrid-dot-event')) {
                    el.setAttribute('title', buildEventTooltip(info.event));
                    return;
                }

                // رنگ متن بر اساس نسبت کنتراست استاندارد (WCAG) انتخاب می شود
                // تا روی رنگ های روشن (مثل سبز فسفری) هم متن خوانا بماند
                const parts = (window.getComputedStyle(el).backgroundColor || '').match(/\d+(\.\d+)?/g);
                if (parts && parts.length >= 3 && !(parts.length >= 4 && Number(parts[3]) === 0)) {
                    const bgLum = relativeLuminance(Number(parts[0]), Number(parts[1]), Number(parts[2]));

                    const onWhite = contrastRatio(bgLum, 1);
                    const onDark  = contrastRatio(bgLum, DARK_TEXT_LUM);
                    el.style.color = (onDark >= onWhite) ? '#2f3245' : '#ffffff';

                    // رنگ های خیلی روشن روی زمینه سفید گم می شوند، یک خط دور نازک می گیرند
                    if (bgLum > 0.72) {
                        el.style.boxShadow = 'inset 0 0 0 1px rgba(47,50,69,.18), 0 1px 2px rgba(31,33,51,.14)';
                    }
                }

                el.setAttribute('title', buildEventTooltip(info.event));
            },
            datesSet: function(info) { applyTransposedMode(info); },
            // رویدادها بعد از datesSet می رسند، پس نمای جابجاشده دوباره ساخته می شود
            eventsSet: function() {
                // بعد از هر بار رسیدن رویدادها (تعویض نما یا بازه) جستجو دوباره اعمال می شود
                var stats = applyCalendarSearchDisplay();
                updateCalendarSearchInfo(stats);
                var v = calendar.view;
                if (v && (v.type === 'timeGridWeek' || v.type === 'timeGridDay')) {
                    renderTimeView(v, visibleCalendarEvents());
                }
                focusRequestedEvent();
            },
            eventClick: function(info) { showEventDetail(info.event); },
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
            calendar.render();
        });

        // ------------------------------ اتصال کادر جستجو ------------------------------
        const calendarSearchInput = document.getElementById('calendarSearch');
        const calendarSearchClear = document.getElementById('calendarSearchClear');
        if (calendarSearchInput) {
            let searchTimer = null;
            const runSearch = function () {
                calendarSearchTerm = normalizeSearchText(calendarSearchInput.value);
                if (calendarSearchClear) {
                    calendarSearchClear.style.display = calendarSearchInput.value ? 'block' : 'none';
                }
                refreshCalendarSearch();
            };

            // تایپ پشت سر هم، رندر تقویم را پشت سر هم اجرا نکند
            calendarSearchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(runSearch, 180);
            });

            calendarSearchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    calendarSearchInput.value = '';
                    clearTimeout(searchTimer);
                    runSearch();
                }
            });

            if (calendarSearchClear) {
                calendarSearchClear.addEventListener('click', function () {
                    calendarSearchInput.value = '';
                    clearTimeout(searchTimer);
                    runSearch();
                    calendarSearchInput.focus();
                });
            }
        }
        // ------------------------------ Datepickers ------------------------------
        // Add event modal datepickers – date only (time is separate)
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        if (startDateInput && typeof AzarDatepicker !== 'undefined' && !startDateInput._hasAzarPicker) {
            window.startPicker = new AzarDatepicker({
                selector: '#start_date',
                mode: 'date',
                calendar: 'jalali',
                inputFormat: 'YYYY/MM/DD',    // ← date only
                outputFormat: 'YYYY/MM/DD'
            });
            startDateInput.removeAttribute('readonly');
            startDateInput._hasAzarPicker = true;
        }
        if (endDateInput && typeof AzarDatepicker !== 'undefined' && !endDateInput._hasAzarPicker) {
            window.endPicker = new AzarDatepicker({
                selector: '#end_date',
                mode: 'date',
                calendar: 'jalali',
                inputFormat: 'YYYY/MM/DD',    // ← date only
                outputFormat: 'YYYY/MM/DD'
            });
            endDateInput.removeAttribute('readonly');
            endDateInput._hasAzarPicker = true;
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
        document.getElementById('start_date').addEventListener('change', updateCombinedDateTime);
        document.getElementById('start_time').addEventListener('change', updateCombinedDateTime);
        document.getElementById('end_date').addEventListener('change', updateCombinedDateTime);
        document.getElementById('end_time').addEventListener('change', updateCombinedDateTime);
        updateCombinedDateTime();

        // ------------------------------ رویداد هفتگی ------------------------------
        const isWeeklyCheckbox = document.getElementById('isWeekly');
        const weeklyDayContainer = document.getElementById('weeklyDayContainer');
        const weeklyEventBlock = document.getElementById('weeklyEventBlock');

        // دراپ‌داون روز هفته فقط وقتی تیک خورده باشد دیده می شود
        function syncWeeklyDayVisibility() {
            if (!isWeeklyCheckbox || !weeklyDayContainer) return;
            weeklyDayContainer.style.display = isWeeklyCheckbox.checked ? 'block' : 'none';
        }
        if (isWeeklyCheckbox) {
            isWeeklyCheckbox.addEventListener('change', syncWeeklyDayVisibility);
            syncWeeklyDayVisibility();
        }

        // فرم بین «افزودن» و «ویرایش» مشترک است؛ تکرار هفتگی فقط هنگام ساختن رویداد معنی دارد
        window.__setWeeklyBlockVisible = function (visible) {
            if (!weeklyEventBlock) return;
            weeklyEventBlock.style.display = visible ? 'block' : 'none';
            if (!visible && isWeeklyCheckbox) {
                isWeeklyCheckbox.checked = false;
                syncWeeklyDayVisibility();
            }
        };

        // دکمه ی «افزودن رویداد جدید» فرم را به حالت افزودن بر می گرداند
        // (پیش از این، بعد از یک ویرایش، فرم روی حالت ویرایش می ماند)
        const openAddEventBtn = document.getElementById('openAddEventBtn');
        if (openAddEventBtn) {
            openAddEventBtn.addEventListener('click', function () {
                document.getElementById('addEventModalLabel').innerText = 'افزودن رویداد جدید';
                document.getElementById('formAction').value = 'add_event';
                document.getElementById('formEventId').value = '';
                document.getElementById('editEventId').value = '';
                document.getElementById('title').value = '';
                document.getElementById('description').value = '';
                document.getElementById('personal_note').value = '';
                document.getElementById('start_date').value = '';
                document.getElementById('end_date').value = '';
                document.getElementById('start_time').value = '00:00';
                document.getElementById('end_time').value = '00:00';
                updateCombinedDateTime();
                window.__setWeeklyBlockVisible(true);
            });
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

                // کادر پایه ها تا این لحظه مخفی بوده و select2 عرضش را صفر حساب می کند،
                // پس هر بار که نمایان می شود دوباره ساخته می شود تا placeholder درست دیده شود.
                if (userContainer.style.display !== 'none'
                    && typeof window.__buildCourseSelect2 === 'function') {
                    window.__buildCourseSelect2();
                }
            });
            initPills();
            // Trigger once to set initial visibility
            targetTypeSelect.dispatchEvent(new Event('change'));
        }

        function updateCombinedDateTime() {
            // Start date
            const startDate = document.getElementById('start_date').value.trim();
            const startTime = document.getElementById('start_time').value.trim();
            const startCombined = startDate ? startDate + ' ' + (startTime || '00:00') : '';
            document.getElementById('start_combined').value = startCombined;

            // End date
            const endDate = document.getElementById('end_date').value.trim();
            const endTime = document.getElementById('end_time').value.trim();
            const endCombined = endDate ? endDate + ' ' + (endTime || '00:00') : '';
            document.getElementById('end_combined').value = endCombined;
        }
        // ------------------------------ Form submit (add / edit) ------------------------------
        if (form) {
            form.addEventListener('submit', function(e) {
                updateCombinedDateTime();

                // رویداد هفتگی بدون تاریخ پایان بازه ای برای تکرار ندارد
                const weeklyChecked = isWeeklyCheckbox && isWeeklyCheckbox.checked
                    && weeklyEventBlock && weeklyEventBlock.style.display !== 'none';
                if (weeklyChecked && !document.getElementById('end_date').value.trim()) {
                    e.preventDefault();
                    alert('برای رویداد هفتگی، وارد کردن تاریخ پایان الزامی است.');
                    return false;
                }

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
                const optionsData = [];
                const optionRows = document.querySelectorAll('#optionsList .row');
                optionRows.forEach(row => {
                    const text = row.querySelector('.option-text').value.trim();
                    if (text !== '') {
                        const toggleBtn = row.querySelector('.toggle-reason');
                        const needsReason = toggleBtn ? parseInt(toggleBtn.dataset.reason) : 0;
                        optionsData.push({ text, needs_reason: needsReason });
                    }
                });
                const optionsInput = document.createElement('input');
                optionsInput.type = 'hidden';
                optionsInput.name = 'active_options';
                optionsInput.value = JSON.stringify(optionsData);
                form.appendChild(optionsInput);

                if (isAdminGroup && isAllMode) {
                    document.getElementById('selectedAdminRoles').value = '[]';
                    document.getElementById('selectedAdminUsers').value = '[]';
                }

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
                return true;
            });
        }
        if (addEventModal && typeof $ !== 'undefined') {
            $('#addEventModal').on('shown.bs.modal', function() {
                if (adminContainer && (!roleSelect || !adminSelect)) {
                    initPills();
                }
                // ساخت/بازسازی ویجت ها حالا که مودال قابل اندازه گیری است
                if (typeof window.__buildTargetSelect2 === 'function') window.__buildTargetSelect2();
                if (typeof window.__buildCourseSelect2 === 'function') window.__buildCourseSelect2();
                if (typeof AzarDatepicker !== 'undefined') {
                    const startInput = document.getElementById('start_date');
                    const endInput = document.getElementById('end_date');
                    if (startInput && !startInput._hasAzarPicker) {
                        new AzarDatepicker({
                            selector: '#start_date',
                            mode: 'date',
                            calendar: 'jalali',
                            inputFormat: 'YYYY/MM/DD',
                            outputFormat: 'YYYY/MM/DD'
                        });
                        startInput.removeAttribute('readonly');
                        startInput._hasAzarPicker = true;
                    }
                    if (endInput && !endInput._hasAzarPicker) {
                        new AzarDatepicker({
                            selector: '#end_date',
                            mode: 'date',
                            calendar: 'jalali',
                            inputFormat: 'YYYY/MM/DD',
                            outputFormat: 'YYYY/MM/DD'
                        });
                        endInput.removeAttribute('readonly');
                        endInput._hasAzarPicker = true;
                    }
                }            });
        }

        // ------------------------------ Export modal logic (server‑side Excel) ------------------------------
        let exportRolesSelect = null;
        let exportAdminsSelect = null;
        let exportCoursesSelect = null;

        function exportSelectValues(sel) {
            return sel ? Array.from(sel.selectedOptions).map(o => o.value) : [];
        }

        function exportSelectLabels(sel) {
            return sel ? Array.from(sel.selectedOptions).map(o => o.textContent.trim()) : [];
        }

        function updateExportSummary() {
            const eventType = document.getElementById('export_event_type').value;
            const wantsAdmin = (eventType === 'admin' || eventType === 'both');
            const wantsUser  = (eventType === 'user'  || eventType === 'both');

            const roles   = wantsAdmin ? exportSelectValues(exportRolesSelect) : [];
            const admins  = wantsAdmin ? exportSelectValues(exportAdminsSelect) : [];
            const courses = wantsUser  ? exportSelectValues(exportCoursesSelect) : [];

            document.getElementById('exportSelectedRoles').value = JSON.stringify(roles);
            document.getElementById('exportSelectedUsers').value = JSON.stringify(admins);
            document.getElementById('exportSelectedCourses').value = JSON.stringify(courses);
            document.getElementById('exportIsAllAdmins').value = '0';

            const parts = [];
            if (roles.length)   parts.push('نقش‌ها: ' + exportSelectLabels(exportRolesSelect).join('، '));
            if (admins.length)  parts.push('ادمین‌ها: ' + exportSelectLabels(exportAdminsSelect).join('، '));
            if (courses.length) parts.push('پایه‌ها: ' + exportSelectLabels(exportCoursesSelect).join('، '));

            const summaryDiv = document.getElementById('exportSelectedSummary');
            if (summaryDiv) {
                summaryDiv.innerText = parts.length ? parts.join(' | ') : 'هیچکدام (تمام رویدادهای بازه)';
            }
        }

        function buildExportSelect2() {
            [exportRolesSelect, exportAdminsSelect, exportCoursesSelect].filter(Boolean).forEach(function (el) {
                if (!(window.jQuery && jQuery.fn.select2)) return;
                if (jQuery(el).data('select2')) {
                    jQuery(el).select2('destroy');
                }
                jQuery(el).select2({
                    width: '100%',
                    dir: 'rtl',
                    dropdownParent: jQuery('#exportModal').length ? jQuery('#exportModal') : jQuery(document.body),
                    placeholder: el.getAttribute('data-placeholder') || '',
                    allowClear: true,
                    closeOnSelect: false
                });
                syncEmptySelectState(el);
            });
        }

        function initExportSelects() {
            exportRolesSelect = document.getElementById('exportRolesSelect');
            exportAdminsSelect = document.getElementById('exportAdminsSelect');
            exportCoursesSelect = document.getElementById('exportCoursesSelect');

            [exportRolesSelect, exportAdminsSelect, exportCoursesSelect].filter(Boolean).forEach(function (el) {
                if (window.jQuery && jQuery.fn.select2) {
                    jQuery(el).off('change.export').on('change.export', function () {
                        syncEmptySelectState(el);
                        updateExportSummary();
                    });
                } else {
                    el.addEventListener('change', updateExportSummary);
                }
            });
        }

        function resetExportSelects() {
            [exportRolesSelect, exportAdminsSelect, exportCoursesSelect].filter(Boolean).forEach(function (el) {
                Array.from(el.options).forEach(o => { o.selected = false; });
                if (window.jQuery && jQuery(el).data('select2')) {
                    jQuery(el).trigger('change.select2');
                }
                syncEmptySelectState(el);
            });
        }

        function applyExportFilterVisibility() {
            const value = document.getElementById('export_event_type').value;
            const adminFilters = document.getElementById('exportAdminFilters');
            const userFilters = document.getElementById('exportUserFilters');
            if (adminFilters) adminFilters.style.display = (value === 'admin' || value === 'both') ? 'block' : 'none';
            if (userFilters) userFilters.style.display = (value === 'user' || value === 'both') ? 'block' : 'none';
        }

        const exportEventType = document.getElementById('export_event_type');

        if (exportEventType) {
            exportEventType.addEventListener('change', function() {
                applyExportFilterVisibility();
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
                if (!exportRolesSelect) initExportSelects();
                resetExportSelects();
                buildExportSelect2();

                exportEventType.value = 'admin';
                applyExportFilterVisibility();

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
                updateExportSummary();
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
                const exportUrl = '<?php echo url('admin/Bill/calendar-excel.php'); ?>?calendar-history=1';
                fetch(exportUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(t => { throw new Error(t || 'Server error'); });
                        }
                        const disposition = response.headers.get('Content-Disposition') || '';
                        const match = disposition.match(/filename="?([^";]+)"?/);
                        const rows = response.headers.get('X-Export-Rows');
                        return response.blob().then(blob => ({
                            blob: blob,
                            name: match ? match[1] : 'calendar_events.xlsx',
                            rows: rows
                        }));
                    })
                    .then(result => {
                        if (result.rows === '0') {
                            alert('رویدادی با این فیلترها پیدا نشد');
                            return;
                        }
                        const url = window.URL.createObjectURL(result.blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = result.name;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);
                        bootstrap.Modal.getInstance(exportModal).hide();
                    })
                    .catch(error => {
                        console.error(error);
                        alert('خطا در تولید فایل اکسل: ' + error.message);
                    });
            });
        }



    });



</script>
