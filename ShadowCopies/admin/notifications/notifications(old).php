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
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">

    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <!-- Page header -->
                <div class="card custom-card">
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
                          SELECT a.codemeli,
                                 a.total_calls,
                                 a.total_talk_time,
                                 a.unanswered_calls,
                                 a.last_updated,
                                 p.fname, p.lname
                          FROM (
                            SELECT s.codemeli,
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
                                WHERE d.suporter    = ?
                                  AND d.activityuser = 1
                                  AND c.activecourse = 1
                                  AND f.status       = 'موفق'
                              )
                            GROUP BY s.codemeli
                          ) a
                        JOIN dbpersonal p ON p.codemeli = a.codemeli
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
                            if ((int)$r['total_calls'] < $MIN_CALLS)     { $isViolation = true; $reasons[] = 'تعداد تماس کمتر از حد'; }
                            if ((int)$r['total_talk_time'] < $MIN_TALK)  { $isViolation = true; $reasons[] = 'زمان مکالمه کمتر از حد'; }

                            if (!$isViolation) {
                                $cntOk++;
                                continue;
                            }

                            $items[] = [
                                'codemeli'    => $r['codemeli'],
                                'name'        => trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? '')),
                                'calls'       => (int)$r['total_calls'],
                                'unanswered'  => (int)$r['unanswered_calls'],
                                'talk'        => (int)$r['total_talk_time'],
                                'updated'     => $r['last_updated'],
                                'is_bad'      => $isViolation,
                                'reasons'     => $isViolation ? $reasons : ['بدون مشکل'],
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
                                        $rowClass = $it['is_bad'] ? 'blinking-bg alert-row' : 'ok-row';
                                        $icon     = $it['is_bad'] ? '<i class="fe fe-bell text-danger"></i>' : '<i class="fe fe-check-circle text-success"></i>';
                                        ?>
                                        <tr class=" <?= $rowClass ?> notif-row" title="آخرین بروزرسانی: <?= htmlspecialchars($it['updated']) ?>">
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($it['name']) ?></div>
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
                                                    <button type="button"
                                                            class="btn btn-sm btn-warning"
                                                            title="اقدام"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#ignoreModal"
                                                            data-codemeli="<?= htmlspecialchars($it['codemeli']) ?>">
                                                        اقدام
                                                    </button>
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
        <form method="post" action="thread_create.php" class="modal-content" id="createThreadForm">
            <div class="modal-header">
                <h5 class="modal-title" id="ignoreModalLabel">گزارش</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <!-- you must set this before opening the modal -->
                <input type="hidden" name="codemeli" id="ignoreCodemeli" value="">

                <!-- CSRF -->
                <?php
                if (empty($_SESSION['token'])) { $_SESSION['token'] = bin2hex(random_bytes(32)); }
                ?>
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
    (function(){
        const form = document.getElementById('createThreadForm');
        const select = document.getElementById('reason_code');
        const text = document.getElementById('reason_text');

        form.addEventListener('submit', function(e){
            if (select.value === '4' && text.value.trim() === '') {
                e.preventDefault();
                alert('برای گزینه «سایر» درج توضیحات الزامی است.');
                text.focus();
            }
        });
    })();
    function openSupportProfile(codemeli) {
        window.open(
            '../panel/suporting.php?Id=' + encodeURIComponent(codemeli),
            'پشتیبانی',
            'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
        );
    }

    function openNotifAction(codemeli) {
        console.log('Action clicked for:', codemeli);
    }

    const ignoreModal = document.getElementById('ignoreModal');
    ignoreModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const cm = button?.getAttribute('data-codemeli') || '';
        document.getElementById('ignoreCodemeli').value = cm;
    });
</script>
</body>
</html>
