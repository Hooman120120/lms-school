<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';

if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    exit;
}
if ($_SESSION['auth']['ip'] != GetIp() && $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    exit;
}
$sessionRow = sessionid($_SESSION['auth']['user']);
if (session_id() != ($sessionRow['session_id'] ?? null)) {
    session_unset();
    header('location: ' . url('login.php'));
    exit;
}
if ((int)($_SESSION['auth']['role'] ?? 0) !== 1) {
    header('location: ' . url('index.php'));
    exit;
}

$studentUsername = (string)$_SESSION['auth']['user'];
$codecourse = isset($_GET['code']) ? (int)$_GET['code'] : 0;
$courseTitle = '';
if ($codecourse > 0) {
    $courseStmt = $conn->prepare("SELECT c.coursename
        FROM dbcourse dc
        INNER JOIN classes cl ON cl.id = dc.class_id
        INNER JOIN codtbl_course c ON c.codecourse = cl.codecourse
        WHERE dc.username = ? AND dc.activityuser = 1 AND cl.codecourse = ?
        LIMIT 1");
    $courseStmt->execute([$studentUsername, $codecourse]);
    $courseTitle = (string)($courseStmt->fetchColumn() ?: '');
    if ($courseTitle === '') {
        http_response_code(403);
        exit('این پایه در فهرست پایه‌های فعال شما نیست.');
    }
}
$grades = GetStudentVisibleClassGrades($studentUsername, 300, $codecourse > 0 ? $codecourse : null);
$qualityOptions = registerClassQualityOptions();
include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>نمرات کلاسی من | <?php echo __TITLE; ?></title>
    <?php include_once '../inc/link.php'; ?>
    <style>
        .grade-card {border:1px solid #e7e9f2;border-right:4px solid #7657e8;border-radius:12px;box-shadow:0 5px 16px rgba(52,45,110,.06);}
        .grade-score {min-width:76px;height:76px;border-radius:18px;background:linear-gradient(135deg,#7657e8,#9a7cf3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;}
        .grade-meta {display:flex;flex-wrap:wrap;gap:8px 16px;color:#6b7280;font-size:13px;}
        .grade-quality {display:inline-block;padding:5px 10px;border-radius:20px;background:#eef8f1;color:#287a42;font-weight:600;}
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="page-header">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">نمرات کلاسی<?php echo $courseTitle !== '' ? ' - ' . htmlspecialchars($courseTitle) : ' من'; ?></h2>
                        <p class="text-muted mb-0">نمره‌هایی که استاد برای نمایش به شما منتشر کرده است.</p>
                    </div>
                </div>

                <?php if ($grades === []): ?>
                    <div class="card custom-card"><div class="card-body text-center py-5 text-muted">
                        هنوز نمره کلاسی قابل نمایشی برای شما ثبت نشده است.
                    </div></div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($grades as $grade):
                            $score = $grade['score'] !== null ? rtrim(rtrim(number_format((float)$grade['score'], 2, '.', ''), '0'), '.') : '—';
                            $quality = isset($qualityOptions[(int)($grade['quality'] ?? 0)])
                                ? $qualityOptions[(int)$grade['quality']] : '';
                            $dateValue = (string)($grade['event_date'] ?: $grade['created_at']);
                            $teacherName = trim((string)($grade['teacher_name'] ?? ''));
                        ?>
                            <div class="col-12 col-xl-6 mb-3">
                                <div class="card custom-card grade-card h-100 mb-0">
                                    <div class="card-body d-flex align-items-start" style="gap:16px">
                                        <div class="grade-score"><?php echo htmlspecialchars($score); ?></div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap mb-2" style="gap:8px">
                                                <div>
                                                    <span class="badge badge-primary mb-1">نمره کلاسی</span>
                                                    <h5 class="mb-1"><?php echo htmlspecialchars((string)$grade['title']); ?></h5>
                                                    <div class="text-primary font-weight-semibold">
                                                        <?php echo htmlspecialchars((string)($grade['dars_name'] ?: 'درس نامشخص')); ?>
                                                    </div>
                                                </div>
                                                <?php if ($quality !== ''): ?><span class="grade-quality"><?php echo htmlspecialchars($quality); ?></span><?php endif; ?>
                                            </div>
                                            <div class="grade-meta">
                                                <span><i class="fe fe-layers"></i> <?php echo htmlspecialchars((string)$grade['coursename']); ?></span>
                                                <span><i class="fe fe-users"></i> <?php echo htmlspecialchars((string)$grade['class_name']); ?></span>
                                                <span><i class="fe fe-user"></i> <?php echo htmlspecialchars($teacherName !== '' ? $teacherName : (string)$grade['teacher_username']); ?></span>
                                                <span><i class="fe fe-calendar"></i> <?php echo htmlspecialchars(toJalali($dateValue, 'Y/m/d H:i', 'fa')); ?></span>
                                            </div>
                                            <?php if (trim((string)($grade['note'] ?? '')) !== ''): ?>
                                                <div class="alert alert-light mt-3 mb-0 py-2"><?php echo nl2br(htmlspecialchars((string)$grade['note'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include '../footers.php'; ?>
</div>
</body>
</html>
