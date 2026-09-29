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
$allGrades = GetStudentVisibleClassGrades($studentUsername, 300, $codecourse > 0 ? $codecourse : null);
$lessonFilters = [];
$studentLessons = GetStudentActiveClassLessons($studentUsername, $codecourse > 0 ? $codecourse : null);
foreach ($studentLessons as $lessonRow) {
    $lessonId = (int)($lessonRow['id'] ?? 0);
    if ($lessonId > 0) {
        $lessonFilters[$lessonId] = trim((string)($lessonRow['dars'] ?? '')) ?: 'درس بدون نام';
    }
}
// نمره‌های قدیمی نیز حتی اگر اتصال درس کلاس بعداً تغییر کرده باشد در فیلتر باقی می‌مانند.
foreach ($allGrades as $gradeRow) {
    $lessonId = (int)($gradeRow['dars_id'] ?? 0);
    if ($lessonId > 0) {
        $lessonFilters[$lessonId] = trim((string)($gradeRow['dars_name'] ?? '')) ?: 'درس بدون نام';
    }
}
asort($lessonFilters, SORT_NATURAL | SORT_FLAG_CASE);
$selectedDarsId = isset($_GET['dars']) ? (int)$_GET['dars'] : 0;
if ($selectedDarsId > 0 && !isset($lessonFilters[$selectedDarsId])) {
    $selectedDarsId = 0;
}
$grades = $selectedDarsId > 0
    ? array_values(array_filter($allGrades, static fn(array $row): bool => (int)($row['dars_id'] ?? 0) === $selectedDarsId))
    : [];
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
        .grades-hero {background:linear-gradient(135deg,#fff 0%,#f6f2ff 100%);border:1px solid #ebe5ff;border-radius:18px;padding:22px 24px;margin:20px 0;display:flex;align-items:center;justify-content:space-between;gap:16px;}
        .grades-count {background:#7657e8;color:#fff;border-radius:14px;padding:9px 14px;white-space:nowrap;font-weight:700;}
        .lesson-filters {background:#fff;border:1px solid #ebe7f8;border-radius:15px;padding:13px 15px;margin:-7px 0 20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px;}
        .lesson-filter {border:1px solid #dcd5f6;border-radius:22px;padding:7px 14px;color:#514778;background:#faf9ff;transition:.15s ease;}
        .lesson-filter:hover,.lesson-filter.active {background:#7657e8;border-color:#7657e8;color:#fff;text-decoration:none;}
        .grade-card {border:1px solid #e7e9f2;border-right:5px solid #7657e8;border-radius:16px;box-shadow:0 7px 22px rgba(52,45,110,.07);transition:transform .18s ease,box-shadow .18s ease;overflow:hidden;}
        .grade-card:hover {transform:translateY(-2px);box-shadow:0 11px 28px rgba(52,45,110,.11);}
        .grade-score {min-width:82px;height:82px;border-radius:20px;background:linear-gradient(135deg,#7657e8,#9a7cf3);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:25px;font-weight:800;box-shadow:0 8px 18px rgba(118,87,232,.25);}
        .grade-score small {font-size:10px;font-weight:500;opacity:.85;margin-top:2px;}
        .grade-lesson {font-size:18px;font-weight:700;color:#312e81;}
        .grade-meta {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 14px;margin-top:14px;}
        .grade-meta-item {background:#f8f9fc;border-radius:10px;padding:9px 11px;color:#606579;font-size:12px;overflow-wrap:anywhere;}
        .grade-meta-item i {color:#7657e8;margin-left:5px;}
        .grade-meta-item strong {display:block;color:#25283a;font-size:13px;margin-top:3px;}
        .grade-quality {display:inline-block;padding:6px 12px;border-radius:20px;background:#eaf8ef;color:#24733d;font-weight:700;}
        .grade-actions {display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid #eeeaf8;}
        .grade-teacher-brief {color:#65697b;font-size:13px;}
        .grade-detail-list {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
        .grade-detail-item {background:#f8f7fc;border:1px solid #eeebf8;border-radius:12px;padding:11px 13px;}
        .grade-detail-item span {display:block;color:#818596;font-size:12px;margin-bottom:4px;}
        .grade-detail-item strong {color:#292b3a;font-size:14px;overflow-wrap:anywhere;}
        .grade-detail-item.full {grid-column:1/-1;}
        .grades-empty {border:1px dashed #cfc5f5;border-radius:16px;background:#faf9ff;}
        @media (max-width:575px) {.grades-hero{align-items:flex-start;flex-direction:column}.grade-card .card-body{flex-direction:column}.grade-meta,.grade-detail-list{grid-template-columns:1fr}.grade-detail-item.full{grid-column:auto}.grade-score{width:100%;height:68px;flex-direction:row;gap:5px}}
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="grades-hero">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">نمرات کلاسی<?php echo $courseTitle !== '' ? ' - ' . htmlspecialchars($courseTitle) : ' من'; ?></h2>
                        <p class="text-muted mb-0">نمره‌هایی که استاد برای نمایش به شما منتشر کرده است.</p>
                    </div>
                    <div class="d-flex align-items-center flex-wrap" style="gap:8px">
                        <span class="grades-count"><?php echo count($grades); ?> نمره</span>
                        <?php if ($codecourse > 0): ?>
                            <a class="btn btn-outline-primary" href="mycourse.php?code=<?php echo $codecourse; ?>"><i class="fe fe-arrow-right ml-1"></i>بازگشت به پایه</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($lessonFilters !== []): ?>
                    <div class="lesson-filters">
                        <strong class="ml-2"><i class="fe fe-book-open ml-1"></i>انتخاب درس:</strong>
                        <?php $baseQuery = $codecourse > 0 ? ['code' => $codecourse] : []; ?>
                        <?php foreach ($lessonFilters as $lessonId => $lessonLabel):
                            $lessonQuery = $baseQuery + ['dars' => $lessonId];
                        ?>
                            <a class="lesson-filter<?php echo $selectedDarsId === (int)$lessonId ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($lessonQuery)); ?>"><?php echo htmlspecialchars($lessonLabel); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($grades === []): ?>
                    <div class="card custom-card grades-empty"><div class="card-body text-center py-5 text-muted">
                        <i class="fe <?php echo $selectedDarsId === 0 ? 'fe-book-open' : 'fe-award'; ?> tx-40 d-block mb-3" style="opacity:.45"></i>
                        <?php if ($selectedDarsId === 0): ?>
                            برای مشاهده نمرات، یکی از درس‌های بالا را انتخاب کنید.
                        <?php else: ?>
                            هنوز نمره کلاسی قابل نمایشی برای این درس ثبت نشده است.
                        <?php endif; ?>
                    </div></div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($grades as $grade):
                            $score = $grade['score'] !== null ? rtrim(rtrim(number_format((float)$grade['score'], 2, '.', ''), '0'), '.') : '—';
                            $maxScore = rtrim(rtrim(number_format((float)($grade['max_score'] ?? 20), 2, '.', ''), '0'), '.');
                            $quality = isset($qualityOptions[(int)($grade['quality'] ?? 0)])
                                ? $qualityOptions[(int)$grade['quality']] : '';
                            $assessmentMode = (string)($grade['assessment_mode'] ?? 'score');
                            $isQualityAssessment = $assessmentMode === 'quality';
                            $dateValue = (string)($grade['event_date'] ?: $grade['created_at']);
                            $teacherFirstName = trim((string)($grade['teacher_fname'] ?? ''));
                            $teacherLastName = trim((string)($grade['teacher_lname'] ?? ''));
                            $teacherName = $teacherFirstName !== '' && $teacherFirstName === $teacherLastName
                                ? $teacherFirstName
                                : trim($teacherFirstName . ' ' . $teacherLastName);
                            if ($teacherName === '') $teacherName = (string)($grade['teacher_username'] ?? 'استاد نامشخص');
                            $courseName = trim((string)($grade['coursename'] ?? ''));
                            $className = trim((string)($grade['class_name'] ?? ''));
                            $lessonName = trim((string)($grade['dars_name'] ?? '')) ?: 'درس نامشخص';
                            $gradeModalId = 'gradeDetails' . (int)$grade['id'];
                        ?>
                            <div class="col-12 col-xl-6 mb-3">
                                <div class="card custom-card grade-card h-100 mb-0">
                                    <div class="card-body d-flex align-items-start" style="gap:16px">
                                        <?php if ($isQualityAssessment): ?>
                                            <div class="grade-score"><span><i class="fe fe-check-circle"></i></span><small>ارزیابی کیفی</small></div>
                                        <?php else: ?>
                                            <div class="grade-score"><span><?php echo htmlspecialchars($score); ?></span><small>از <?php echo htmlspecialchars($maxScore); ?></small></div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap mb-2" style="gap:8px">
                                                <div>
                                                    <span class="badge badge-primary mb-2"><?php echo htmlspecialchars((string)$grade['title']); ?></span>
                                                    <div class="grade-lesson">
                                                        <?php echo htmlspecialchars($lessonName); ?>
                                                    </div>
                                                </div>
                                                <?php if ($isQualityAssessment): ?><span class="grade-quality"><?php echo htmlspecialchars($quality !== '' ? $quality : 'ثبت نشده'); ?></span><?php endif; ?>
                                            </div>
                                            <div class="grade-actions">
                                                <span class="grade-teacher-brief"><i class="fe fe-user ml-1"></i>استاد: <strong><?php echo htmlspecialchars($teacherName); ?></strong></span>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?php echo $gradeModalId; ?>">
                                                    مشاهده جزئیات
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="<?php echo $gradeModalId; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">جزئیات نمره <?php echo htmlspecialchars($lessonName); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="grade-detail-list">
                                                <?php if ($isQualityAssessment): ?>
                                                    <div class="grade-detail-item"><span>ارزیابی توصیفی</span><strong><?php echo htmlspecialchars($quality !== '' ? $quality : 'ثبت نشده'); ?></strong></div>
                                                <?php else: ?>
                                                    <div class="grade-detail-item"><span>نمره</span><strong><?php echo htmlspecialchars($score); ?> از <?php echo htmlspecialchars($maxScore); ?></strong></div>
                                                <?php endif; ?>
                                                <div class="grade-detail-item"><span>درس</span><strong><?php echo htmlspecialchars($lessonName); ?></strong></div>
                                                <div class="grade-detail-item"><span>استاد</span><strong><?php echo htmlspecialchars($teacherName); ?></strong></div>
                                                <div class="grade-detail-item full"><span>عنوان پایه</span><strong><?php echo htmlspecialchars($courseName); ?></strong></div>
                                                <?php if ($className !== '' && $className !== $courseName): ?>
                                                    <div class="grade-detail-item full"><span>کلاس</span><strong><?php echo htmlspecialchars($className); ?></strong></div>
                                                <?php endif; ?>
                                                <div class="grade-detail-item full"><span>تاریخ ثبت</span><strong><?php echo htmlspecialchars(toJalali($dateValue, 'Y/m/d H:i', 'fa')); ?></strong></div>
                                                <div class="grade-detail-item full"><span>توضیحات استاد</span><strong><?php echo trim((string)($grade['note'] ?? '')) !== '' ? nl2br(htmlspecialchars((string)$grade['note'])) : 'توضیحی ثبت نشده است.'; ?></strong></div>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button></div>
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
