<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (!isset($_SESSION['admin'])) { die('لطفا ابتدا وارد پنل شوید.'); }

// Define the operators to compare
$operators = ['op204', 'op205']; 
$comparison_results = [];

// 1. Fetch Rules (done once)
$rule = $conn->query("
    SELECT period_days, min_calls, min_talk_time
    FROM notification_rules
    WHERE active=1
    ORDER BY created_at DESC, id DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$rule) {
    die('خطا: قوانین اعلان یافت نشد.');
}

$MIN_CALLS  = isset($rule['min_calls']) ? (int)$rule['min_calls'] : 5;
$MIN_TALK   = isset($rule['min_talk_time']) ? (int)$rule['min_talk_time'] : 300;

echo "<div style='background: #1e1e1e; color: #0f0; padding: 20px; font-family: monospace; direction: ltr; text-align: left;'>";
echo "<h3>=== OPERATOR DIAGNOSTIC COMPARISON ===</h3>";
echo "Rule: Min Calls = $MIN_CALLS, Min Talk = $MIN_TALK sec<br><br>";

foreach ($operators as $operator) {
    $stats = [
        '1_total_sql_results' => 0,
        '2_dropped_ignored_function' => 0,
        '3_dropped_has_operator_0' => 0,
        '4_dropped_suspended' => 0,
        '5_dropped_met_requirements (Ok)' => 0,
        '6_dropped_resolved_recent_ticket' => 0,
        '7_FINAL_NOTIFICATIONS_SHOWN' => 0
    ];

    // Your exact SQL query
    $sql = "
    WITH ar AS (
        SELECT period_days, min_calls, min_talk_time FROM notification_rules WHERE active=1 ORDER BY created_at DESC, id DESC LIMIT 1
    ),
    ranked AS (
        SELECT s.codemeli, s.operator_username, FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days) AS block_idx,
        COALESCE(s.total_calls, 0) AS total_calls, COALESCE(s.unanswered_calls, 0) AS unanswered_calls, COALESCE(s.total_talk_time, 0) AS total_talk_time,
        ar.min_calls, ar.min_talk_time, ROW_NUMBER() OVER (PARTITION BY s.codemeli, s.operator_username, FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days) ORDER BY s.last_updated DESC, s.id DESC) AS rn
        FROM simotel_calls_data s CROSS JOIN ar
        WHERE s.operator_username = ? AND FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / ar.period_days) >= 1
    ),
    rc AS (
        SELECT codemeli, operator_username, SUM(CASE WHEN ((total_calls - unanswered_calls) < min_calls OR total_talk_time < min_talk_time) THEN 1 ELSE 0 END) AS total_threads
        FROM ranked WHERE rn = 1 GROUP BY codemeli, operator_username
    ),
    olympiad_enrollment AS (
        SELECT dc.username AS codemeli, MAX(CASE WHEN ctc.course_type = 2 AND ctc.courseactive = 1 THEN 1 ELSE 0 END) AS has_active_olympiad_course
        FROM dbcourse dc JOIN codtbl_course ctc ON dc.codecourse = ctc.codecourse WHERE ctc.courseactive = 1 GROUP BY dc.username
    )
    SELECT 
        a.codemeli, a.operator_username, a.total_calls, a.total_talk_time, a.unanswered_calls, a.last_updated, p.fname, p.lname, p.paye AS paye,
        COALESCE(rt.active_recent, 0) AS has_active_recent, COALESCE(rt.resolved_recent, 0) AS has_resolved_recent, COALESCE(rt.rejected_recent, 0) AS has_rejected_recent,
        COALESCE(rc.total_threads, 0) AS total_threads, COALESCE(oe.has_active_olympiad_course, 0) AS is_olympiad_enrolled
    FROM (
        SELECT s.codemeli, s.operator_username, s.total_calls AS total_calls, s.total_talk_time AS total_talk_time, s.unanswered_calls AS unanswered_calls, s.last_updated AS last_updated
        FROM simotel_calls_data s
        WHERE s.operator_username = ? AND s.snapshot_day = (SELECT MAX(snapshot_day) FROM simotel_calls_data WHERE operator_username = ?)                   
        AND s.codemeli IN (
            SELECT DISTINCT d.username FROM dbcourse d JOIN codtbl_course c ON c.codecourse = d.codecourse JOIN dbfactor f ON f.idfactor = d.numfactor
            WHERE d.suporter = ? AND d.activityuser = 1 AND c.activecourse = 1 AND f.status = 'موفق'
        )
    ) a
    JOIN dbpersonal p ON p.codemeli = a.codemeli
    LEFT JOIN (
        SELECT codemeli, operator_username, MAX(CASE WHEN status IN ('open','chatting') THEN 1 ELSE 0 END) AS active_recent, MAX(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_recent, MAX(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_recent
        FROM report_threads WHERE updated_at >= (NOW() - INTERVAL 7 DAY) GROUP BY codemeli, operator_username
    ) rt ON rt.codemeli = a.codemeli AND rt.operator_username = a.operator_username
    LEFT JOIN rc ON rc.codemeli = a.codemeli AND rc.operator_username = a.operator_username
    LEFT JOIN olympiad_enrollment oe ON oe.codemeli = a.codemeli
    ORDER BY p.lname ASC, p.fname ASC, a.codemeli ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$operator, $operator, $operator, $operator]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats['1_total_sql_results'] = count($rows);

    foreach ($rows as $r) {
        // 1. Ignored Function
        if (function_exists('isNotificationIgnored') && isNotificationIgnored($r['codemeli'], $operator, $conn)) {
            $stats['2_dropped_ignored_function']++;
            continue;
        }

        // 2. Has Operator = 0 check
        $stmt_op = $conn->prepare('SELECT * FROM dbcourse WHERE username = ? AND has_operator = 0');
        $stmt_op->execute([$r['codemeli']]);
        if ($stmt_op->rowCount() > 0) {
            $stats['3_dropped_has_operator_0']++;
            continue;
        }

        // 3. Suspended check
        $stmt_sus = $conn->prepare('SELECT * FROM dbpersonal WHERE codemeli = ? AND suspension_mode = 1');
        $stmt_sus->execute([$r['codemeli']]);
        if ($stmt_sus->rowCount() > 0) {
            $stats['4_dropped_suspended']++;
            continue;
        }

        // 4. Met requirements (Violation check)
        $isViolation = false;
        if ((int)$r['total_calls'] < $MIN_CALLS)    { $isViolation = true; }
        if ((int)$r['total_talk_time'] < $MIN_TALK) { $isViolation = true; }

        if (!$isViolation) { 
            $stats['5_dropped_met_requirements (Ok)']++;
            continue; 
        }

        // 5. 7-Day Ticket Rules
        $hasResolvedRecent = !empty($r['has_resolved_recent']);
        if ($hasResolvedRecent) {
            $stats['6_dropped_resolved_recent_ticket']++;
            continue;
        }

        // If it reaches here, it's a notification shown on screen
        $stats['7_FINAL_NOTIFICATIONS_SHOWN']++;
    }

    $comparison_results[$operator] = $stats;
}

echo "<pre>";
print_r($comparison_results);
echo "</pre></div>";
?>
