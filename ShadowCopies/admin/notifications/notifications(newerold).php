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
    if ($_POST['token'] !== ($_SESSION['token'] ?? '')) {
        die('خطا: اعتبارسنجی توکن CSRF انجام نشد.');
    }

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
    if ($reason_code === 4 && $reason) {
        $title .= ' - ' . mb_substr($reason, 0, 50);
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
        .notif-card:hover { transform: scale(1.01); box-shadow: 0 0 15px rgba(0,0,0,0.08); }
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
                        $operator = isset($_GET['Op']) && $_GET['Op'] !== '' ? trim($_GET['Op']) : trim($_SESSION['admin']['user']);

                        $rule = $conn->query("
  SELECT period_days, min_calls, min_talk_time
  FROM notification_rules
  WHERE active=1
  ORDER BY created_at DESC, id DESC
  LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

                        $periodDays = max(1, (int)($rule['period_days'] ?? 1));
                        $MIN_CALLS  = isset($rule['min_calls']) && $rule['min_calls'] !== null ? (int)$rule['min_calls'] : 5;
                        $MIN_TALK   = isset($rule['min_talk_time']) && $rule['min_talk_time'] !== null ? (int)$rule['min_talk_time'] : 300;

                        $startDay = date('Y-m-d', strtotime('-' . ($periodDays - 1) . ' days'));
                        $endDay   = date('Y-m-d');

                        $sql = "
                SELECT 
                  a.codemeli,
                  a.operator_username,
                  a.total_calls,
                  a.total_talk_time,
                  a.unanswered_calls,
                  a.last_updated,
                  p.fname, 
                  p.lname,
                
                  /* 7-day flags from report_threads */
                  COALESCE(rt.active_recent,   0) AS has_active_recent,   -- open/chatting in last 7 days
                  COALESCE(rt.resolved_recent, 0) AS has_resolved_recent, -- resolved in last 7 days
                  COALESCE(rt.rejected_recent, 0) AS has_rejected_recent, -- rejected in last 7 days
                
                  /* all-time counter */
                  COALESCE(rc.total_threads, 0)   AS total_threads
                FROM (
                  SELECT 
                    s.codemeli,
                    s.operator_username,
                    SUM(s.total_calls)      AS total_calls,
                    SUM(s.total_talk_time)  AS total_talk_time,
                    SUM(s.unanswered_calls) AS unanswered_calls,
                    MAX(s.last_updated)     AS last_updated
                  FROM simotel_calls_data s
                  WHERE s.operator_username = ?
                    AND s.snapshot_day BETWEEN ? AND ?
                    AND s.codemeli IN (
                      SELECT DISTINCT d.username
                      FROM dbcourse d
                      JOIN codtbl_course c ON c.codecourse = d.codecourse
                      JOIN dbfactor f      ON f.idfactor   = d.numfactor
                      WHERE d.suporter     = ?
                        AND d.activityuser = 1
                        AND c.activecourse = 1
                        AND f.status       = 'موفق'
                    )
                  GROUP BY s.codemeli, s.operator_username
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
                
                /* for counter */
                LEFT JOIN (
                  SELECT codemeli, operator_username, COUNT(*) AS total_threads
                  FROM report_threads
                  GROUP BY codemeli, operator_username
                ) rc
                  ON rc.codemeli = a.codemeli
                 AND rc.operator_username = a.operator_username
                
                ORDER BY p.lname ASC, p.fname ASC, a.codemeli ASC
";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$operator, $startDay, $endDay, $operator]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $items = [];
                        $cntBad = 0; $cntOk = 0;

                        foreach ($rows as $r) {
                            if (function_exists('isNotificationIgnored') && isNotificationIgnored($r['codemeli'], $operator, $conn)) {
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

                            ];

                            if ($isViolation) $cntBad++; else $cntOk++;
                        }
                        $shownCount = count($items);
                        ?>


                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0">لیست اعلان‌ها</h5>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary">تعداد نمایش: <?= $shownCount ?></span>
                            </div>
                        </div>

                        <?php if ($shownCount === 0): ?>
                            <div class="alert alert-success mb-0">هیچ ردی برای نمایش وجود ندارد. ✅</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="example1" class="table align-middle">
                                    <thead>
                                    <tr>
                                        <th>نام</th>
                                        <th class="text-nowrap">کدملی</th>
                                        <th>وضعیت</th>
                                        <th class="text-nowrap">اقدامات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($items as $it): ?>
                                        <?php
                                        if (!empty($it['has_recent'])) {           // now means "active recent" (open/chatting)
                                            $rowClass = 'review-row';
                                            $icon     = '<i class="fe fe-clock text-success"></i>';
                                        } else {
                                            $rowClass = $it['is_bad'] ? 'blinking-bg alert-row' : 'ok-row';
                                            $icon     = $it['is_bad'] ? '<i class="fe fe-bell text-danger"></i>' : '<i class="fe fe-check-circle text-success"></i>';
                                        }
                                        ?>
                                        <tr class=" <?= $rowClass ?> notif-row" title="آخرین بروزرسانی: <?= htmlspecialchars($it['updated']) ?>">
                                            <td>
                                                <div class="fw-bold d-flex align-items-center gap-2">
                                                    <?= htmlspecialchars($it['name']) ?>
                                                    <span class="badge bg-light text-dark border">تیکت‌ها: <?= (int)$it['thread_count'] ?></span>
                                                </div>
                                            </td>
                                            <td class="text-nowrap"><?= htmlspecialchars($it['codemeli']) ?></td>
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
</script>


</body>
</html>
