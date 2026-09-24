<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (CheckBlacklist_ip(GetIp())) { include_once '../../blackList.html'; exit; }

if (!isset($_SESSION['admin'])) { header('location: ../login.php'); exit; }

// Protect against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() || $_SESSION['admin']['os'] !== GetOs()) {
    session_unset(); header('location: ../login.php'); exit;
}

// Ensure only one active admin session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset(); header('location: ' . url('admin/login.php')); exit;
}
$viewerUsername = (string)($_SESSION['admin']['user'] ?? '');
$viewerSchoolRole = SchoolStaffRoleKey($viewerUsername);
$viewerMayManageCalls = in_array($viewerSchoolRole, ['manager', 'general_deputy', 'counselor'], true)
    || Permision('permision_viewalert') || Permision('permision_suporter');
if (!$viewerMayManageCalls) {
    http_response_code(403);
    exit('دسترسی اعلان تماس برای سمت شما تعریف نشده است.');
}

function secToHMS($seconds): string {
    $seconds = is_numeric($seconds) ? (int)$seconds : 0;
    if ($seconds < 0) $seconds = 0;
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// --- Handle inline thread creation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['codemeli'])) {

    // CSRF check
    // if ($_POST['token'] !== ($_SESSION['token'] ?? '')) {
    //     die('خطا: اعتبارسنجی توکن CSRF انجام نشد.');
    // }

    $codemeli = trim($_POST['codemeli']);
    $reason_code = intval($_POST['reason_code'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $operator_username = $_SESSION['admin']['user'] ?? null;

    if (!$operator_username || !$codemeli) {
        die('اطلاعات ناقص: شناسه کاربر یا کدملی موجود نیست.');
    }

    if ($reason_code < 1 || $reason_code > 4) {
        die('کد دلیل نامعتبر است.');
    }

    if ($reason_code === 4 && $reason === '') {
        die('برای گزینه "سایر" توضیح الزامی است.');
    }

    // Reason titles
    $titles = [
        1 => 'دانش‌آموز جدید',
        2 => 'شماره تماس اشتباه',
        3 => 'شماره تماس مشترک',
        4 => 'سایر'
    ];

    $title = $titles[$reason_code];
    if ($reason !== '') {
        $title .= ' - ' . mb_substr($reason, 0, 250);
    }

    // 🔒 7-day cooldown guard (hard-coded)
    $COOLDOWN_DAYS = 7;

    $chk = $conn->prepare("
      SELECT id
      FROM report_threads
      WHERE codemeli = ? AND operator_username = ?
        AND updated_at >= (NOW() - INTERVAL $COOLDOWN_DAYS DAY)
        AND status IN ('open','chatting','resolved')  -- 'rejected' does NOT block
      ORDER BY updated_at DESC
      LIMIT 1
    ");
    $chk->execute([$codemeli, $operator_username]);
    if ($chk->fetchColumn()) {
        $_SESSION['flash'] = "برای این دانش‌آموز در $COOLDOWN_DAYS روز گذشته تیکت فعال/اختتام‌یافته‌ای ثبت شده است.";
        $_SESSION['flash_type'] = 'warning';
        header('Location: '.$_SERVER['REQUEST_URI']);
        exit;
    }

    // ✅ No recent ticket → Insert thread
    $sql = "INSERT INTO report_threads (codemeli, operator_username, title, status)
            VALUES (?, ?, ?, 'open')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codemeli, $operator_username, $title]);

    $_SESSION['flash'] = 'گزارش با موفقیت ثبت شد.';
    header('Location: notifications.php');
    exit;
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اعلان‌ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>

    <style>
        .alert-row {
            background-color: #ffe5e5 !important;
            color: #b30000;
            font-weight: bold;
            border: 1px solid #ffb3b3;
        }
        .blinking-bg { animation: blinkBackground 1.5s infinite; }
        @keyframes blinkBackground { 0%,100%{background-color:#ffe5e5;} 50%{background-color:#ff0000;} }

        .ok-row {
            background-color: #e9f7ef !important;
            color: #0f5132;
            border: 1px solid #badbcc;
            font-weight: 500;
        }
        .notif-card { transition: all 0.2s ease-in-out; }
        .notif-card:hover { transform: scale(1.01); box-shadow: 0 0 1f5px rgba(0,0,0,0.08); }
        .review-row{
            background-color:#ecfdf5 !important; /* soft green */
            border:1px solid #a7f3d0 !important;
            color:#065f46 !important;            /* dark teal-ish */
            font-weight:500;
        }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">

    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <!-- Page header -->
                <div class="card custom-card">
                    <?php if (!empty($_SESSION['flash'])):
                        $type = $_SESSION['flash_type'] ?? 'success';
                        $msg  = $_SESSION['flash'];
                        unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
                        <div class="alert alert-<?= htmlspecialchars($type) ?> text-center mb-0 rounded-0" role="alert">
                            <?= htmlspecialchars($msg) ?>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <?php
//                        $operator = isset($_GET['Op']) && $_GET['Op'] !== '' ? trim($_GET['Op']) : trim($_SESSION['admin']['user']);
//
//                        $rule = $conn->query("
//                          SELECT period_days, min_calls, min_talk_time
//                          FROM notification_rules
//                          WHERE active=1
//                          ORDER BY created_at DESC, id DESC
//                          LIMIT 1
//                        ")->fetch(PDO::FETCH_ASSOC);
//
//                        if (!$rule || $rule['period_days'] === null || $rule['min_calls'] === null || $rule['min_talk_time'] === null) {
//                            die('خطا: قوانین اعلان ناقص است (period_days/min_calls/min_talk_time نباید NULL باشد).');
//                        }
//
//                        $periodDays = max(1, (int)($rule['period_days'] ?? 1));
//                        $MIN_CALLS  = isset($rule['min_calls']) && $rule['min_calls'] !== null ? (int)$rule['min_calls'] : 5;
//                        $MIN_TALK   = isset($rule['min_talk_time']) && $rule['min_talk_time'] !== null ? (int)$rule['min_talk_time'] : 300;
//
//                        $startDay = date('Y-m-d', strtotime('-' . ($periodDays - 1) . ' days'));
//                        $endDay   = date('Y-m-d');
//
//                        $sql = "
//                    WITH ar AS (
//                      SELECT period_days, min_calls, min_talk_time
//                      FROM notification_rules
//                      WHERE active=1
//                      ORDER BY created_at DESC, id DESC
//                      LIMIT 1
//                    ),
//                    ranked AS (
//                      SELECT
//                        s.codemeli,
//                        s.operator_username,
//                        FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days) AS block_idx,
//                        COALESCE(s.total_calls, 0)        AS total_calls,
//                        COALESCE(s.unanswered_calls, 0)   AS unanswered_calls,
//                        COALESCE(s.total_talk_time, 0)    AS total_talk_time,
//                        ar.min_calls,
//                        ar.min_talk_time,
//                        ROW_NUMBER() OVER (
//                          PARTITION BY
//                            s.codemeli,
//                            s.operator_username,
//                            FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days)
//                          ORDER BY s.last_updated DESC, s.id DESC
//                        ) AS rn
//                      FROM simotel_calls_data s
//                      CROSS JOIN ar
//                      WHERE s.operator_username = ?
//                        AND FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days) >= 1
//                    ),
//                    rc AS (
//                      SELECT
//                        codemeli,
//                        operator_username,
//                        SUM(
//                          CASE
//                            WHEN ((total_calls - unanswered_calls) < min_calls OR total_talk_time < min_talk_time)
//                              THEN 1 ELSE 0
//                          END
//                        ) AS total_threads
//                      FROM ranked
//                      WHERE rn = 1
//                      GROUP BY codemeli, operator_username
//                    )
//                    SELECT
//                      a.codemeli,
//                      a.operator_username,
//                      a.total_calls,
//                      a.total_talk_time,
//                      a.unanswered_calls,
//                      a.last_updated,
//                      p.fname,
//                      p.lname,
//                      p.paye AS paye,
//
//                      /* 7-day flags from report_threads */
//                      COALESCE(rt.active_recent,   0) AS has_active_recent,   -- open/chatting in last 7 days
//                      COALESCE(rt.resolved_recent, 0) AS has_resolved_recent, -- resolved in last 7 days
//                      COALESCE(rt.rejected_recent, 0) AS has_rejected_recent, -- rejected in last 7 days
//
//                      /* historical fails across past blocks (excluding current block) */
//                      COALESCE(rc.total_threads, 0)   AS total_threads
//                    FROM (
//                      SELECT
//                        s.codemeli,
//                        s.operator_username,
//                        s.total_calls     AS total_calls,
//                        s.total_talk_time  AS total_talk_time,
//                        s.unanswered_calls AS unanswered_calls,
//                        s.last_updated     AS last_updated
//                      FROM simotel_calls_data s
//                      WHERE s.operator_username = ?
//                           AND s.snapshot_day = (
//                                SELECT MAX(snapshot_day)
//                                FROM simotel_calls_data
//                                WHERE operator_username = ?
//                            )
//                        AND s.codemeli IN (
//                          SELECT DISTINCT d.username
//                          FROM dbcourse d
//                          JOIN codtbl_course c ON c.codecourse = d.codecourse
//                          JOIN dbfactor f      ON f.idfactor   = d.numfactor
//                          WHERE d.suporter     = ?
//                            AND d.activityuser = 1
//                            AND c.activecourse = 1
//                            AND f.status       = 'موفق'
//                        )
//                    ) a
//                    JOIN dbpersonal p
//                      ON p.codemeli = a.codemeli
//                    LEFT JOIN (
//                      SELECT
//                        codemeli,
//                        operator_username,
//                        MAX(CASE WHEN status IN ('open','chatting') THEN 1 ELSE 0 END) AS active_recent,
//                        MAX(CASE WHEN status = 'resolved'           THEN 1 ELSE 0 END) AS resolved_recent,
//                        MAX(CASE WHEN status = 'rejected'           THEN 1 ELSE 0 END) AS rejected_recent
//                      FROM report_threads
//                      WHERE updated_at >= (NOW() - INTERVAL 7 DAY)
//                      GROUP BY codemeli, operator_username
//                    ) rt
//                      ON rt.codemeli = a.codemeli
//                     AND rt.operator_username = a.operator_username
//
//                    LEFT JOIN rc
//                      ON rc.codemeli = a.codemeli
//                     AND rc.operator_username = a.operator_username
//
//                    ORDER BY p.lname ASC, p.fname ASC, a.codemeli ASC
//                    ";
//                        $stmt = $conn->prepare($sql);
//                        $stmt->execute([$operator, $operator, $operator, $operator]);
//                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
//
//                        $items = [];
//                        $cntBad = 0; $cntOk = 0;
//
//                        foreach ($rows as $r) {
//                            if (function_exists('isNotificationIgnored') && isNotificationIgnored($r['codemeli'], $operator, $conn)) {
//                                continue;
//                            }
//                            $sql = 'SELECT * FROM dbcourse WHERE username = ? AND has_operator = 0';
//                            $stmt = $conn->prepare($sql);
//                            $stmt->execute([$r['codemeli']]);
//                            if ($stmt->rowCount() > 0) {
//                                continue;
//                            }
//                            $sql = 'SELECT * FROM dbpersonal WHERE codemeli = ? AND suspension_mode = 1';
//                            $stmt = $conn->prepare($sql);
//                            $stmt->execute([$r['codemeli']]);
//                            if ($stmt->rowCount() > 0) {
//                                continue;
//                            }
//
//                            $isViolation = false; $reasons = [];
//                            if ((int)$r['total_calls'] < $MIN_CALLS)    { $isViolation = true; $reasons[] = 'تعداد تماس کمتر از حد'; }
//                            if ((int)$r['total_talk_time'] < $MIN_TALK) { $isViolation = true; $reasons[] = 'زمان مکالمه کمتر از حد'; }
//
//                            if (!$isViolation) { $cntOk++; continue; }
//
//                            // 7-day ticket rules
//                            $hasActiveRecent   = !empty($r['has_active_recent']);   // open/chatting in last 7 days
//                            $hasResolvedRecent = !empty($r['has_resolved_recent']); // resolved in last 7 days
//                            $hasRejectedRecent = !empty($r['has_rejected_recent']); // rejected in last 7 days
//
//                            // If recently resolved -> hide this row entirely for 7 days
//                            if ($hasResolvedRecent) {
//                                continue;
//                            }
//                            $threadCount = (int)($r['total_threads'] ?? 0);
//                            // In-review flag: only if there is an active (open/chatting) ticket in last 7 days
//                            $inReview = $hasActiveRecent;
//
//                            $items[] = [
//                                'codemeli'    => $r['codemeli'],
//                                'name'        => trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? '')),
//                                'calls'       => (int)$r['total_calls'],
//                                'unanswered'  => (int)$r['unanswered_calls'],
//                                'talk'        => (int)$r['total_talk_time'],
//                                'updated'     => $r['last_updated'],
//                                'is_bad'      => $isViolation,
//                                'has_recent'  => $inReview, // used by renderer to show ‘در حال بررسی’ state
//                                'reasons'     => $inReview ? ['در حال بررسی'] : ($isViolation ? $reasons : ['بدون مشکل']),
//                                'thread_count'=> $threadCount,
//                                'paye' => $r['paye'] ?? '',
//                            ];
//
//                            if ($isViolation) $cntBad++; else $cntOk++;
//                        }
//                        $shownCount = count($items);
                        $operator = isset($_GET['Op']) && $_GET['Op'] !== '' ? trim($_GET['Op']) : trim($_SESSION['admin']['user']);
                        $operatorRoleKey = SchoolStaffRoleKey($operator);
                        $operatorIsCounselor = $operatorRoleKey === 'counselor';

                        $rule = $conn->query("
                          SELECT period_days, min_calls, min_talk_time
                          FROM notification_rules
                          WHERE active=1
                          ORDER BY created_at DESC, id DESC
                          LIMIT 1
                        ")->fetch(PDO::FETCH_ASSOC);

                        if (!$rule || $rule['period_days'] === null || $rule['min_calls'] === null || $rule['min_talk_time'] === null) {
                            die('خطا: قوانین اعلان ناقص است (period_days/min_calls/min_talk_time نباید NULL باشد).');
                        }

                        $periodDays = max(1, (int)($rule['period_days'] ?? 1));
                        $MIN_CALLS  = isset($rule['min_calls']) && $rule['min_calls'] !== null ? (int)$rule['min_calls'] : 5;
                        $MIN_TALK   = isset($rule['min_talk_time']) && $rule['min_talk_time'] !== null ? (int)$rule['min_talk_time'] : 300;

                        $startDay = date('Y-m-d', strtotime('-' . ($periodDays - 1) . ' days'));
                        $endDay   = date('Y-m-d');

                        $sql = "
                    WITH ar AS (
                      SELECT period_days, min_calls, min_talk_time
                      FROM notification_rules
                      WHERE active=1
                      ORDER BY created_at DESC, id DESC
                      LIMIT 1
                    ),
                    ranked AS (
                      SELECT
                        s.codemeli,
                        s.operator_username,
                        FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days) AS block_idx,
                        COALESCE(s.total_calls, 0)        AS total_calls,
                        COALESCE(s.unanswered_calls, 0)   AS unanswered_calls,
                        COALESCE(s.total_talk_time, 0)    AS total_talk_time,
                        ar.min_calls,
                        ar.min_talk_time,
                        ROW_NUMBER() OVER (
                          PARTITION BY
                            s.codemeli,
                            s.operator_username,
                            FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days)
                          ORDER BY s.last_updated DESC, s.id DESC
                        ) AS rn
                      FROM simotel_calls_data s
                      CROSS JOIN ar
                      WHERE s.operator_username = ?
                        AND FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days) >= 1
                    ),
                    rc AS (
                      SELECT
                        codemeli,
                        operator_username,
                        SUM(
                          CASE
                            WHEN ((total_calls - unanswered_calls) < min_calls OR total_talk_time < min_talk_time)
                              THEN 1 ELSE 0
                          END
                        ) AS total_threads
                      FROM ranked
                      WHERE rn = 1
                      GROUP BY codemeli, operator_username
                    ),
                 
                    olympiad_enrollment AS (
                        SELECT
                            dc.username AS codemeli,
                            MAX(CASE 
                                WHEN ctc.course_type = 2 AND ctc.courseactive = 1 THEN 1 
                                ELSE 0 
                            END) AS has_active_olympiad_course
                        FROM
                            dbcourse dc
                        JOIN
                            codtbl_course ctc ON dc.codecourse = ctc.codecourse
                        WHERE
                            ctc.courseactive = 1 -- Optimization: only check active course mapping
                        GROUP BY
                            dc.username
                    )
                    
                    SELECT 
                      a.codemeli,
                      a.operator_username,
                      a.total_calls,
                      a.total_talk_time,   
                      a.unanswered_calls,
                      a.last_updated,
                      p.fname, 
                      p.lname,
                      p.paye AS paye,
                    
                      /* 7-day flags from report_threads */
                      COALESCE(rt.active_recent,   0) AS has_active_recent,   -- open/chatting in last 7 days
                      COALESCE(rt.resolved_recent, 0) AS has_resolved_recent, -- resolved in last 7 days
                      COALESCE(rt.rejected_recent, 0) AS has_rejected_recent, -- rejected in last 7 days
                    
                      /* historical fails across past blocks (excluding current block) */
                      COALESCE(rc.total_threads, 0)   AS total_threads,
                      
                     
                      COALESCE(oe.has_active_olympiad_course, 0) AS is_olympiad_enrolled

                    FROM (
                      SELECT 
                        s.codemeli,
                        s.operator_username,
                        s.total_calls     AS total_calls,
                        s.total_talk_time  AS total_talk_time,
                        s.unanswered_calls AS unanswered_calls,
                        s.last_updated     AS last_updated
                      FROM simotel_calls_data s
                      WHERE s.operator_username = ?
                           AND s.snapshot_day = (
                                SELECT MAX(snapshot_day)
                                FROM simotel_calls_data
                                WHERE operator_username = ?
                            )                   
                        AND s.codemeli IN (
                          SELECT DISTINCT d.username
                          FROM dbcourse d
                          JOIN codtbl_course c ON c.codecourse = d.codecourse
                          JOIN dbfactor f      ON f.idfactor   = d.numfactor
                          WHERE (
                                d.suporter = ?
                                OR (? = 1 AND EXISTS (
                                    SELECT 1 FROM school_staff_courses ssc
                                    WHERE ssc.admin_username = ?
                                      AND ssc.codecourse = d.codecourse
                                ))
                            )
                            AND d.activityuser = 1
                            AND c.activecourse = 1
                            AND f.status       = 'موفق'
                        )
                    ) a
                    JOIN dbpersonal p 
                      ON p.codemeli = a.codemeli
                    LEFT JOIN (
                      SELECT 
                        codemeli, 
                        operator_username,
                        MAX(CASE WHEN status IN ('open','chatting') THEN 1 ELSE 0 END) AS active_recent,
                        MAX(CASE WHEN status = 'resolved'           THEN 1 ELSE 0 END) AS resolved_recent,
                        MAX(CASE WHEN status = 'rejected'           THEN 1 ELSE 0 END) AS rejected_recent
                      FROM report_threads
                      WHERE updated_at >= (NOW() - INTERVAL 7 DAY)
                      GROUP BY codemeli, operator_username
                    ) rt
                      ON rt.codemeli = a.codemeli
                     AND rt.operator_username = a.operator_username
                    
                    LEFT JOIN rc
                      ON rc.codemeli = a.codemeli
                     AND rc.operator_username = a.operator_username
                     
                    
                    LEFT JOIN olympiad_enrollment oe
                      ON oe.codemeli = a.codemeli
                    
                    ORDER BY p.lname ASC, p.fname ASC, a.codemeli ASC
                    ";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $operator,
                            $operator,
                            $operator,
                            $operator,
                            $operatorIsCounselor ? 1 : 0,
                            $operator
                        ]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $items = [];
                        $cntBad = 0; $cntOk = 0;

                        foreach ($rows as $r) {
                            // ... (PHP logic remains largely the same, but now accesses $r['is_olympiad_enrolled']) ...
                            if (function_exists('isNotificationIgnored') && isNotificationIgnored($r['codemeli'], $operator, $conn)) {
                                continue;
                            }
                            $sql = 'SELECT * FROM dbcourse WHERE username = ? AND has_operator = 0';
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$r['codemeli']]);
                            if ($stmt->rowCount() > 0) {
                                continue;
                            }
                            $sql = 'SELECT * FROM dbpersonal WHERE codemeli = ? AND suspension_mode = 1';
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$r['codemeli']]);
                            if ($stmt->rowCount() > 0) {
                                continue;
                            }

                            $isViolation = false; $reasons = [];
                            if ((int)$r['total_calls'] < $MIN_CALLS)    { $isViolation = true; $reasons[] = 'تعداد تماس کمتر از حد'; }
                            if ((int)$r['total_talk_time'] < $MIN_TALK) { $isViolation = true; $reasons[] = 'زمان مکالمه کمتر از حد'; }

                            if (!$isViolation) { $cntOk++; continue; }

                            // 7-day ticket rules
                            $hasActiveRecent   = !empty($r['has_active_recent']);   // open/chatting in last 7 days
                            $hasResolvedRecent = !empty($r['has_resolved_recent']); // resolved in last 7 days
                            $hasRejectedRecent = !empty($r['has_rejected_recent']); // rejected in last 7 days

                            // If recently resolved -> hide this row entirely for 7 days
                            if ($hasResolvedRecent) {
                                continue;
                            }
                            $threadCount = (int)($r['total_threads'] ?? 0);
                            // In-review flag: only if there is an active (open/chatting) ticket in last 7 days
                            $inReview = $hasActiveRecent;

                            $items[] = [
                                'codemeli'    => $r['codemeli'],
                                'name'        => trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? '')),
                                'calls'       => (int)$r['total_calls'],
                                'unanswered'  => (int)$r['unanswered_calls'],
                                'talk'        => (int)$r['total_talk_time'],
                                'updated'     => $r['last_updated'],
                                'is_bad'      => $isViolation,
                                'has_recent'  => $inReview, // used by renderer to show ‘در حال بررسی’ state
                                'reasons'     => $inReview ? ['در حال بررسی'] : ($isViolation ? $reasons : ['بدون مشکل']),
                                'thread_count'=> $threadCount,
                                'paye' => $r['paye'] ?? '',
                                'is_olympiad' => !empty($r['is_olympiad_enrolled']) ? 1 : 0 
                            ];

                            if ($isViolation) $cntBad++; else $cntOk++;
                        }
                        $shownCount = count($items);
                        
                          $alerted = 0;
                           foreach ($items as $it):
                        $tc = (int)$it['thread_count'];
                        if (!empty($it['has_recent'])) {
                            $rowClass = 'review-row';
                            $icon     = '<i class="fe fe-clock text-success"></i>';
                        } elseif($tc > 2) {

                            $alerted++;
                        }
                           endforeach;

                        ?>

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0">لیست اعلان‌ها</h5>
                            </div>
                                                     <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger">تعداد سطر های قرمز : <?= $alerted; ?></span>
                            </div>

                        </div>

                        <?php if ($shownCount === 0): ?>
                            <div class="alert alert-success mb-0">هیچ ردی برای نمایش وجود ندارد. ✅</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <div class="col-12 d-flex flex-row justify-content-center gap-2 mb-3" id="payeFilters">

                                    <button type="button" class="btn btn-info shadow rounded active" data-filter="all">همه</button>
                                    <button type="button" class="btn btn-info shadow rounded" data-filter="olympiad" data-olympiad="1">المپیاد</button>

                                    <!-- Elementary -->
                                    <div class="btn-group dropdown">
                                        <button type="button" class="btn btn-info shadow rounded" data-range="1-6">
                                            <i class="fa fa-graduation-cap"></i> گروه ابتدایی
                                        </button>
                                        <button type="button" class="btn btn-info shadow rounded dropdown-toggle dropdown-toggle-split"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><button class="dropdown-item" type="button" data-paye="1">کلاس اول دبستان</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="2">کلاس دوم دبستان</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="3">کلاس سوم دبستان</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="4">کلاس چهارم دبستان</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="5">کلاس پنجم دبستان</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="6">کلاس ششم دبستان</button></li>
                                        </ul>
                                    </div>

                                    <!-- Middle School -->
                                    <div class="btn-group dropdown">
                                        <button type="button" class="btn btn-success shadow rounded" data-range="7-9">
                                            <i class="fa fa-book"></i> متوسطه اول
                                        </button>
                                        <button type="button" class="btn btn-success shadow rounded dropdown-toggle dropdown-toggle-split"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><button class="dropdown-item" type="button" data-paye="7">هفتم</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="8">هشتم</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="9">نهم</button></li>
                                        </ul>
                                    </div>

                                    <!-- High School -->
                                    <div class="btn-group dropdown">
                                        <button type="button" class="btn btn-warning shadow rounded" data-range="10-12">
                                            <i class="fa fa-star"></i> متوسطه دوم
                                        </button>
                                        <button type="button" class="btn btn-warning shadow rounded dropdown-toggle dropdown-toggle-split"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><button class="dropdown-item" type="button" data-paye="10">دهم</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="11">یازدهم</button></li>
                                            <li><button class="dropdown-item" type="button" data-paye="12">دوازدهم</button></li>
                                        </ul>
                                    </div>

                                </div>
                                <table id="example1" class="table align-middle">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نام</th>
                                        <th class="text-nowrap">کدملی</th>
                                        <th>پایه</th>
                                        <th>وضعیت</th>
                                        <th class="text-nowrap">اقدامات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($items as $it): ?>
                                        <?php
                                        $tc = (int)$it['thread_count'];

                                        if (!empty($it['has_recent'])) {
                                            $rowClass = 'review-row';
                                            $icon     = '<i class="fe fe-clock text-success"></i>';
                                        } else {
                                            // red if tc > 3, otherwise green
                                            $rowClass = ($tc > 2) ? 'blinking-bg alert-row' : 'ok-row';
                                            $icon     = ($tc > 2) ? '<i class="fe fe-bell text-danger"></i>' : '<i class="fe fe-check-circle text-success"></i>';
                                        }
                                        ?>

                                        <tr class="<?= $rowClass ?> notif-row"
                                            data-paye="<?= htmlspecialchars($it['paye'], ENT_QUOTES, 'UTF-8') ?>"
                                            title="آخرین بروزرسانی: <?= htmlspecialchars($it['updated']) ?>">
                                            <td data-olympiad-flag="<?= (int)$it['is_olympiad'] ?>">
                                                <span
                                                        class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger text-white fw-bold"
                                                        style="width: 28px; height: 28px;"
                                                ><?= $tc; ?></span>
                                            </td>

                                            <td>
                                                <div class="fw-bold d-flex align-items-center gap-2 ltr justify-content-end">
                                                    <?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?>

                                                </div>
                                            </td>
                                            <td class="text-nowrap"><?= htmlspecialchars($it['codemeli']) ?></td>

                                            <td><?= htmlspecialchars($it['paye'] ) ?></td>
                                            <td>
                                              <span class="badge <?= $it['is_bad'] ? 'bg-danger' : 'bg-success' ?>">
                                                <?= htmlspecialchars(implode('، ', $it['reasons'])) ?>
                                              </span>
                                                <?= $icon ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <div class="btn-group" role="group" aria-label="actions">
                                                    <button type="button"
                                                            class="btn btn-sm btn-info btn-icon"
                                                            title="نمایه"
                                                            onclick="openSupportProfile('<?= htmlspecialchars($it['codemeli']) ?>')">
                                                        <i class="fa fa-key"></i>
                                                    </button>
                                                    <?php if (!empty($it['has_recent'])): ?>
                                                        <button type="button" class="btn btn-sm btn-secondary" title="در حال بررسی" disabled>
                                                            در حال بررسی
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button"
                                                                class="btn btn-sm btn-warning"
                                                                title="اقدام"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#ignoreModal"
                                                                data-codemeli="<?= htmlspecialchars($it['codemeli']) ?>">
                                                            اقدام
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>

<div class="modal fade" id="ignoreModal" tabindex="-1" aria-labelledby="ignoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="" class="modal-content" id="createThreadForm">
            <div class="modal-header">
                <h5 class="modal-title" id="ignoreModalLabel">گزارش</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="codemeli" id="ignoreCodemeli" value="">

                <?php if (empty($_SESSION['token'])) { $_SESSION['token'] = bin2hex(random_bytes(32)); } ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']) ?>">

                <div class="mb-3">
                    <label class="form-label">دلیل:</label>
                    <select name="reason_code" id="reason_code" class="form-select" required>
                        <option value="1">دانش آموز جدید</option>
                        <option value="2">شماره تماس اشتباه</option>
                        <option value="3">شماره تماس مشترک</option>
                        <option value="4">سایر</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">توضیحات (برای «سایر» اجباری است)</label>
                    <textarea name="reason" id="reason_text" class="form-control" rows="3" placeholder="توضیح..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-info">ثبت</button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">بستن</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Enforce "سایر" requires text (client-side)
    (function(){
        const form   = document.getElementById('createThreadForm');
        const select = document.getElementById('reason_code');
        const text   = document.getElementById('reason_text');

        if (form && select && text) {
            // submit guard
            form.addEventListener('submit', function(e){
                if (select.value === '4' && text.value.trim() === '') {
                    e.preventDefault();
                    alert('برای گزینه «سایر» درج توضیحات الزامی است.');
                    text.focus();
                }
            });

            // toggle required attribute
            const toggleReq = () => { text.required = (select.value === '4'); };
            select.addEventListener('change', toggleReq);
            toggleReq();
        }
    })();

    // Open student profile
    function openSupportProfile(codemeli) {
        window.open(
            '../panel/suporting.php?Id=' + encodeURIComponent(codemeli),
            'پشتیبانی',
            'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
        );
    }

    // Fill hidden codemeli when opening modal
    const ignoreModal = document.getElementById('ignoreModal');
    if (ignoreModal) {
        ignoreModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const cm = button?.getAttribute('data-codemeli') || '';
            document.getElementById('ignoreCodemeli').value = cm;
        });
    }

    $(function () {
        // Make sure DataTable exists
        const table = $('#example1').DataTable(); // if it's already initialized, this returns the instance

        let filterState = {
            paye: 'all',
            range: 'all',
            olympiad: 'all' 
        };

        // Global DataTables filter (applies to ALL rows, across all pages)
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            // apply only to our table
            if (settings.nTable && settings.nTable.id !== 'example1') return true;

            const rowNode = table.row(dataIndex).node();
            if (!rowNode) return true;

 
            const payeValue = parseInt(rowNode.dataset.paye || '0', 10);
            const olympiadFlag = $(rowNode).find('td:first').data('olympiad-flag'); 

            let isVisible = true;

            if (filterState.olympiad !== 'all') {
                if (String(olympiadFlag) !== filterState.olympiad) {
                    isVisible = false;
                }
            }

          
            if (isVisible && filterState.paye !== 'all') {
                if (filterState.paye === 'exact') {
                    if (payeValue !== filterState.exact) isVisible = false;
                }
                if (filterState.paye === 'range') {
                    if (!(payeValue >= filterState.min && payeValue <= filterState.max)) isVisible = false;
                }
            }

  
            return isVisible;
        });

        // --- NEW: Unified UI Management ---
        function updateActiveUI() {
            const container = document.getElementById('payeFilters');
            if (!container) return;

            // Remove active class from ALL filter buttons first
            container.querySelectorAll('button').forEach(x => x.classList.remove('active'));

            let activeFilterButton = null;

            if (filterState.olympiad !== 'all') {
               
                activeFilterButton = container.querySelector(`[data-filter="olympiad"]`);
            } else if (filterState.paye !== 'all') {
                // Paye filter is active
                if (filterState.paye === 'exact') {
                    activeFilterButton = container.querySelector(`[data-paye="${filterState.exact}"]`);
                } else if (filterState.paye === 'range') {
                    activeFilterButton = container.querySelector(`[data-range="${filterState.range}"]`);
                }
            }

            if (activeFilterButton) {
                activeFilterButton.classList.add('active');
            } else {
                // If nothing is active, set 'All' button as active (assuming it exists)
                const allBtn = container.querySelector('[data-filter="all"]');
                if (allBtn) allBtn.classList.add('active');
            }
        }


        function setUrlParam() {
            const url = new URL(window.location.href);
            let paramSet = false;

        
            if (filterState.olympiad !== 'all') {
                url.searchParams.set('olympiad', filterState.olympiad);
                paramSet = true;
            } else {
                url.searchParams.delete('olympiad');
            }

            if (filterState.olympiad === 'all') {
                if (filterState.paye === 'exact') {
                    url.searchParams.set('paye', String(filterState.exact));
                    paramSet = true;
                } else if (filterState.paye === 'range') {
                    url.searchParams.set('paye', filterState.range);
                    paramSet = true;
                } else {
                    url.searchParams.delete('paye');
                }
            }

            if (!paramSet) url.searchParams.delete('paye');

            window.history.replaceState({}, '', url);
        }

        // --- NEW: Reset functions to handle mutual exclusivity ---
        function applyAll(el) {
            filterState.paye = 'all';
            filterState.range = 'all';
            filterState.olympiad = 'all';
            // payeFilter.exact/min/max are technically deprecated but we clear them for safety
            filterState.exact = filterState.min = filterState.max = null;

            setUrlParam();
            updateActiveUI();
            table.draw();
        }

        function applyPayeExact(n, el) {
            filterState.paye = 'exact';
            filterState.exact = n;
            filterState.range = 'all';
            filterState.olympiad = 'all';

            setUrlParam();
            updateActiveUI();
            table.draw();
        }

        function applyPayeRange(min, max, raw, el) {
            filterState.paye = 'range';
            filterState.min = min;
            filterState.max = max;
            filterState.range = raw; // Store range string like '1-6'
            filterState.exact = null;
            filterState.olympiad = 'all';
            setUrlParam();
            updateActiveUI();
            table.draw();
        }


        function applyOlympiadFilter(value, el) {
            filterState.olympiad = value;
            filterState.paye = 'all'; // Reset Paye/Range filters
            filterState.range = 'all';
            filterState.exact = null;

            setUrlParam();
            updateActiveUI();
            table.draw();
        }
        // --- END NEW ---


        // Hook UI clicks
        const container = document.getElementById('payeFilters');
        if (container) {
            container.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-filter],[data-paye],[data-range],[data-olympiad]');
                if (!btn) return;

                if (btn.dataset.filter === 'all') {
                    applyAll(btn);
                    return;
                }

                if (btn.dataset.olympiad) {

                    const value = btn.dataset.olympiad;
                    applyOlympiadFilter(value, btn);
                    return;
                }

                if (btn.dataset.paye) {
                    const n = parseInt(btn.dataset.paye, 10);
                    if (Number.isFinite(n)) applyPayeExact(n, btn);
                    return;
                }

                if (btn.dataset.range) {
                    const [a,b] = btn.dataset.range.split('-').map(x => parseInt(x, 10));
                    if (Number.isFinite(a) && Number.isFinite(b)) applyPayeRange(a, b, btn.dataset.range, btn);
                }
            });
        }

        const initial = new URLSearchParams(window.location.search).get('paye') || new URLSearchParams(window.location.search).get('olympiad');

        if (!initial || initial === 'all') {
            applyAll(container?.querySelector('[data-filter="all"]') || null);
        } else if (initial === '1' || initial === '0') {
            const el = container?.querySelector(`[data-olympiad="${CSS.escape(initial)}"]`) || null;
            applyOlympiadFilter(initial, el);
        } else if (/^\d+$/.test(initial)) {
            // Existing Paye Exact
            const el = container?.querySelector(`[data-paye="${CSS.escape(initial)}"]`) || null;
            applyPayeExact(parseInt(initial, 10), el);
        } else if (/^\d+\-\d+$/.test(initial)) {
            // Existing Paye Range
            const el = container?.querySelector(`[data-range="${CSS.escape(initial)}"]`) || null;
            const [a,b] = initial.split('-').map(x => parseInt(x, 10));
            applyPayeRange(a, b, initial, el);
        } else {
            // Fallback to All if URL param exists but doesn't match known formats
            applyAll(container?.querySelector('[data-filter="all"]') || null);
        }

        // Initialize UI state once table is ready
        updateActiveUI();
    });
</script>

</body>
</html>
