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
// Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

$idpersonal = isset($_GET['idpersonal']) ? (int)$_GET['idpersonal'] : 0;
if ($idpersonal <= 0) {
    echo '<strong>404</strong>';
    echo '<script>window.close()</script>';
    die();
}

// ✅ NEW: optional class_id (used to scope which class/course to show)
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($class_id < 0) $class_id = 0;

RequireTeacherPermission('teacher_perm_students', url('admin/index.php'));
if ((int)($_SESSION['admin']['adminrole'] ?? -1) === 3 && (
    $class_id <= 0 || !TeacherHasClassAccess($class_id)
)) {
    http_response_code(403);
    exit('شما به پرونده دانش‌آموزان این کلاس دسترسی ندارید.');
}

// Fetch student by idpersonal from dbpersonal
$stmt = $conn->prepare("SELECT * FROM dbpersonal WHERE idpersonal = ? LIMIT 1");
$stmt->execute([$idpersonal]);
$user = $stmt->fetch(PDO::FETCH_OBJ);

if (!$user) {
    echo '<strong>404</strong>';
    echo '<script>window.close()</script>';
    die();
}

/**
 * IMPORTANT:
 * dbcourse.username is your enrollment key, so studentUsername MUST match it.
 */
$studentUsername =
    ($user->codemeli ?? null) ? (string)$user->codemeli :
        (($user->user ?? null) ? (string)$user->user :
            (($user->username ?? null) ? (string)$user->username :
                (($user->student_username ?? null) ? (string)$user->student_username : null)));

if ($studentUsername !== null) {
    $studentUsername = trim($studentUsername);
    if ($studentUsername === '') $studentUsername = null;
}

if ((int)($_SESSION['admin']['adminrole'] ?? -1) === 3) {
    $studentClassStmt = $conn->prepare('
        SELECT 1
        FROM dbcourse
        WHERE username = ?
          AND activityuser = 1
          AND (class_id = ? OR (class_id IS NULL AND codecourse = (SELECT codecourse FROM classes WHERE id = ?)))
        LIMIT 1
    ');
    $studentClassStmt->execute([$studentUsername, $class_id, $class_id]);
    if (!$studentUsername || !$studentClassStmt->fetchColumn()) {
        http_response_code(403);
        exit('این دانش‌آموز عضو کلاس تخصیص‌یافته شما نیست.');
    }
}

// Tables
$ENROLL_TABLE       = 'dbcourse';
$HOMEWORKS_TABLE    = 'homework_admin';
$SUBMISSIONS_TABLE  = 'homework_student';

$classes = [];

if ($studentUsername) {

    // ✅ MODE 1: if class_id provided => ONLY show that class (and its course)
    if ($class_id > 0) {

        // Preferred: dc.class_id is set
        $stcOne = $conn->prepare("
            SELECT
                c.id AS class_id,
                c.codecourse,
                c.name AS class_title,
                COALESCE(co.coursename, CONCAT('درس ', c.codecourse)) AS course_title
            FROM {$ENROLL_TABLE} dc
            INNER JOIN classes c ON c.id = dc.class_id
            LEFT JOIN codtbl_course co ON co.codecourse = c.codecourse
            WHERE dc.username = ?
              AND dc.activityuser = 1
              AND c.id = ?
              AND EXISTS (SELECT 1 FROM {$HOMEWORKS_TABLE} h WHERE h.class_id = c.id  AND h.status = 'approved')
            LIMIT 1
        ");
        $stcOne->execute([$studentUsername, $class_id]);
        $one = $stcOne->fetch(PDO::FETCH_ASSOC);

        // Fallback: dc.class_id is NULL, link by codecourse -> classes
        if (!$one) {
            $stcOne2 = $conn->prepare("
                SELECT
                    c.id AS class_id,
                    c.codecourse,
                    c.name AS class_title,
                    COALESCE(co.coursename, CONCAT('درس ', c.codecourse)) AS course_title
                FROM {$ENROLL_TABLE} dc
                INNER JOIN classes c ON c.codecourse = dc.codecourse
                LEFT JOIN codtbl_course co ON co.codecourse = c.codecourse
                WHERE dc.username = ?
                  AND dc.activityuser = 1
                  AND c.id = ?
                  AND EXISTS (SELECT 1 FROM {$HOMEWORKS_TABLE} h WHERE h.class_id = c.id  AND h.status = 'approved')
                LIMIT 1
            ");
            $stcOne2->execute([$studentUsername, $class_id]);
            $one = $stcOne2->fetch(PDO::FETCH_ASSOC);
        }

        if ($one) {
            $classes = [$one];
        } else {
            $classes = [];
        }
    }
}

function gdate_to_jalali_ymd($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') return '—';
    $gY = (int)substr($raw, 0, 4);
    $gM = (int)substr($raw, 5, 2);
    $gD = (int)substr($raw, 8, 2);
    if ($gY > 1900 && $gM >= 1 && $gM <= 12 && $gD >= 1 && $gD <= 31 && function_exists('gregorian_to_jalali')) {
        [$jY, $jM, $jD] = gregorian_to_jalali($gY, $gM, $gD);
        return sprintf('%04d/%02d/%02d', $jY, $jM, $jD);
    }
    return substr($raw, 0, 10);
}

$selectedClass = (!empty($classes) && is_array($classes)) ? $classes[0] : null;

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>پروفایل دانش آموز |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<div class="page">
    <div class="container-fluid">
        <div class="inner-body">

            <!-- ===== TOP HEADER CARD ===== -->
            <div class="row row-sm mt-3">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xl-11">
                                    <div>
                                        <h6 class="main-content-label mb-1">
                                            دانش آموز :
                                            <span class="text-primary">
                                                <?php echo htmlspecialchars($user->fname ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                <?php echo htmlspecialchars($user->lname ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            |
                                            پایه تحصیلی و رشته:
                                            <span class="badge badge-success-light">
                                                <?php echo htmlspecialchars(($user->paye ?? '---') . ' - ' . ($user->reshtestudent ?? '---'), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            |
                                            استان:
                                            <span class="badge badge-success-light">
                                                <?php echo htmlspecialchars($user->state ?? '---', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            |
                                            شهر:
                                            <span class="badge badge-success-light">
                                                <?php echo htmlspecialchars($user->city ?? '---', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </h6>

                                        <!-- ✅ Optional: show which class/course is currently scoped -->
                                        <?php if ($class_id > 0): ?>
                                            <div class="mt-2">
                                                <?php if ($selectedClass): ?>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($selectedClass['course_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($selectedClass['class_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>

                            <hr class="my-3" style="height:4px; background:red; border:0; opacity:1;">

                            <?php if (!$studentUsername): ?>
                                <div class="alert alert-warning mb-0">
                                    نام کاربری دانش‌آموز برای اتصال به جدول dbcourse پیدا نشد (codemeli / user / username / student_username).
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== HOMEWORK ACCORDION ===== -->
            <div class="row row-sm">
                <div class="col-12">
                    <div class="card custom-card">
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-purple">
                            تکالیف دانش‌آموز
                        </div>

                        <div class="card-body">

                            <?php if (!$studentUsername): ?>

                                <div class="alert alert-warning mb-0">
                                    برای نمایش تکالیف باید مقدار $studentUsername دقیقا با dbcourse.username یکی باشد.
                                </div>

                            <?php elseif (!$classes): ?>

                                <div class="alert alert-info mb-0">
                                    <?php if ($class_id > 0): ?>
                                        این دانش‌آموز در این کلاس ثبت‌نام نیست یا این کلاس تکلیفی ندارد.
                                    <?php else: ?>
                                        کلاسی که تکلیف داشته باشد برای این دانش‌آموز پیدا نشد.
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>

                                <div class="accordion" id="hwAccordion">
                                    <?php foreach ($classes as $idx => $cl): ?>
                                        <?php
                                        $classId = (int)($cl['class_id'] ?? 0);
                                        if ($classId <= 0) continue;

                                        // Pull all homeworks for this class + student's submission if exists
                                        $q = $conn->prepare("
                                            SELECT
                                                h.id AS hw_id,
                                                h.title,
                                                h.description,
                                                h.deadline,
                                                h.file_path AS hw_file,
                                                h.video_link,
                                                h.created_at,
                                                hs.id AS sub_id,
                                                hs.submission_text,
                                                hs.file_path AS sub_file,
                                                hs.professors_answer,
                                                hs.submitted_at
                                            FROM {$HOMEWORKS_TABLE} h
                                            LEFT JOIN {$SUBMISSIONS_TABLE} hs
                                                ON hs.homework_id = h.id
                                               AND hs.student_username = ?
                                            WHERE h.class_id = ?
                                             AND h.status = 'approved'
                                            ORDER BY h.created_at DESC
                                        ");
                                        $q->execute([$studentUsername, $classId]);
                                        $rows = $q->fetchAll(PDO::FETCH_ASSOC);

                                        // extra safety: if somehow no homework rows, skip rendering this accordion item
                                        if (!$rows) continue;

                                        $answered = [];
                                        $notAnswered = [];
                                        foreach ($rows as $r) {
                                            if (!empty($r['sub_id'])) $answered[] = $r;
                                            else $notAnswered[] = $r;
                                        }

                                        $collapseId = "hwCollapse" . ($idx + 1);
                                        $headingId  = "hwHeading" . ($idx + 1);

                                        $courseTitle = $cl['course_title'] ?? ('درس ' . (int)($cl['codecourse'] ?? 0));
                                        $classTitle  = $cl['class_title'] ?? ('کلاس ' . $classId);
                                        ?>

                                        <div class="accordion-item mb-2">
                                            <h2 class="accordion-header" id="<?= $headingId ?>">
                                                <!-- smaller text + smaller padding (Bootstrap only) -->
                                                <button class="accordion-button collapsed small py-2 lh-sm" type="button" style="font-size: medium;"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#<?= $collapseId ?>"
                                                        aria-expanded="false"
                                                        aria-controls="<?= $collapseId ?>">

                                                    <?= htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') ?>
                                                    <span class="mx-2">|</span>
                                                    <?= htmlspecialchars($classTitle, ENT_QUOTES, 'UTF-8') ?>

                                                    <span class="ms-auto">
                                                        <!-- smaller badges (Bootstrap only) -->
                                                        <span class="badge bg-success ms-2 small py-1 px-2">
                                                            پاسخ داده: <?= count($answered) ?>
                                                        </span>
                                                        <span class="badge bg-danger ms-2 small py-1 px-2">
                                                            بدون پاسخ: <?= count($notAnswered) ?>
                                                        </span>
                                                    </span>
                                                </button>
                                            </h2>

                                            <div id="<?= $collapseId ?>" class="accordion-collapse collapse"
                                                 aria-labelledby="<?= $headingId ?>" data-bs-parent="#hwAccordion">
                                                <!-- smaller inside text too -->
                                                <div class="accordion-body small">

                                                    <!-- Answered -->
                                                    <div class="mb-4">
                                                        <h6 class="mb-2">✅ تکالیف پاسخ داده شده</h6>

                                                        <?php if (!$answered): ?>
                                                            <div class="text-muted">موردی ندارد.</div>
                                                        <?php else: ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered text-nowrap mb-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th>عنوان</th>
                                                                        <th>مهلت (شمسی)</th>
                                                                        <th>فایل تکلیف</th>
                                                                        <th>ویدیو تکلیف</th>
                                                                        <th>پاسخ دانش‌آموز</th>
                                                                        <th>فایل پاسخ</th>
                                                                        <th>جواب استاد</th>
                                                                        <th>زمان ارسال</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <?php foreach ($answered as $r): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <?= htmlspecialchars($r['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                                                <?php if (!empty($r['description'])): ?>
                                                                                    <div class="small text-muted mt-1">
                                                                                        <?= htmlspecialchars($r['description'], ENT_QUOTES, 'UTF-8') ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <span style="direction:ltr; unicode-bidi:embed;">
                                                                                    <?= htmlspecialchars(gdate_to_jalali_ymd($r['deadline'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                                                </span>
                                                                            </td>
                                                                            <td>
                                                                                <?php if (!empty($r['hw_file'])): ?>
                                                                                    <a href="<?= url($r['hw_file']) ?>" target="_blank" rel="noopener">دانلود</a>
                                                                                <?php else: ?>—<?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php
                                                                                $v = trim((string)($r['video_link'] ?? ''));
                                                                                if ($v !== ''):
                                                                                    ?>
                                                                                    <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                                        تماشا
                                                                                    </a>
                                                                                <?php else: ?>
                                                                                    <span class="text-muted">بدون ویدیو</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <?= (trim((string)($r['submission_text'] ?? '')) !== '')
                                                                                    ? htmlspecialchars((string)$r['submission_text'], ENT_QUOTES, 'UTF-8')
                                                                                    : '—'; ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php if (!empty($r['sub_file'])): ?>
                                                                                    <a href="<?= url($r['sub_file']) ?>" target="_blank" rel="noopener">دانلود</a>
                                                                                <?php else: ?>—<?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <?= (trim((string)($r['professors_answer'] ?? '')) !== '')
                                                                                    ? htmlspecialchars((string)$r['professors_answer'], ENT_QUOTES, 'UTF-8')
                                                                                    : '—'; ?>
                                                                            </td>
                                                                            <td>
                                                                                <span style="direction:ltr; unicode-bidi:embed;">
                                                                                    <?= htmlspecialchars(gdate_to_jalali_ymd($r['submitted_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <hr class="my-3" style="height:2px; background:#ddd; border:0; opacity:1;">

                                                    <!-- Not answered -->
                                                    <div>
                                                        <h6 class="mb-2">❌ تکالیف بدون پاسخ</h6>

                                                        <?php if (!$notAnswered): ?>
                                                            <div class="text-muted">موردی ندارد.</div>
                                                        <?php else: ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered text-nowrap mb-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th>عنوان</th>
                                                                        <th>مهلت (شمسی)</th>
                                                                        <th>فایل تکلیف</th>
                                                                        <th>ویدیو تکلیف</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <?php foreach ($notAnswered as $r): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <?= htmlspecialchars($r['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                                                <?php if (!empty($r['description'])): ?>
                                                                                    <div class="small text-muted mt-1">
                                                                                        <?= htmlspecialchars($r['description'], ENT_QUOTES, 'UTF-8') ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <span style="direction:ltr; unicode-bidi:embed;">
                                                                                    <?= htmlspecialchars(gdate_to_jalali_ymd($r['deadline'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                                                </span>
                                                                            </td>
                                                                            <td>
                                                                                <?php if (!empty($r['hw_file'])): ?>
                                                                                    <a href="<?= url($r['hw_file']) ?>" target="_blank" rel="noopener">دانلود</a>
                                                                                <?php else: ?>—<?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php
                                                                                $v = trim((string)($r['video_link'] ?? ''));
                                                                                if ($v !== ''):
                                                                                    ?>
                                                                                    <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                                        تماشا
                                                                                    </a>
                                                                                <?php else: ?>
                                                                                    <span class="text-muted">بدون ویدیو</span>
                                                                                <?php endif; ?>
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
                                    <?php endforeach; ?>
                                </div>

                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>

        </div><!-- inner-body -->
    </div><!-- container-fluid -->

    <?php include '../../footers.php'; ?>
</div><!-- page -->
</body>
</html>
