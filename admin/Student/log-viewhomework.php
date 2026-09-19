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
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['user']) and !isset($_GET['course'])) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}

$user = retPerson($_GET['user']);
$course = returnCourse($_GET['course']);

if (!$user) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!$course) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}

$student_username = $_GET['user'];
$codecourse = $_GET['course'];

/* ===================== Helper Functions ===================== */

function deadline_to_jalali_display($raw) {
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

function deadline_to_ts($raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') return false;
    return strtotime($raw);
}

// Get current admin role
$adminRole = $_SESSION['admin']['adminrole'] ?? null;
$currentAdmin = $_SESSION['admin']['user'] ?? null;
$fullAccessRoles = [0, 51, 52];
$hasFullAccess = in_array((int)$adminRole, $fullAccessRoles, true);

/* ===================== Step 1: Get all classes for this course ===================== */
$classesStmt = $conn->prepare("
    SELECT id, name 
    FROM classes 
    WHERE codecourse = ?
    ORDER BY id
");
$classesStmt->execute([$codecourse]);
$allClasses = $classesStmt->fetchAll(PDO::FETCH_OBJ);

/* ===================== Step 2: Get classes where student is enrolled ===================== */
$enrolledStmt = $conn->prepare("
    SELECT DISTINCT d.class_id, c.name
    FROM dbcourse d
    INNER JOIN classes c ON c.id = d.class_id
    WHERE d.username = ? 
      AND c.codecourse = ?
    ORDER BY c.id
");
$enrolledStmt->execute([$student_username, $codecourse]);
$studentClasses = $enrolledStmt->fetchAll(PDO::FETCH_OBJ);

// Create array of class IDs the student is enrolled in
$enrolledClassIds = array_map(function($c) { return $c->class_id; }, $studentClasses);

/* ===================== Step 3: Get all homeworks for this course's classes ===================== */
// Build the query based on admin role
if ($hasFullAccess) {
    // Full access admins see all homeworks
    $hwStmt = $conn->prepare("
        SELECT 
            h.*,
            c.name AS class_name,
            s.id AS submission_id,
            s.submission_text,
            s.file_path AS student_file_path,
            s.submitted_at,
            s.updated_at,
            s.professors_answer,
            phs.username AS private_assigned
        FROM classes c
        INNER JOIN homework_admin h ON h.class_id = c.id
        LEFT JOIN homework_student s ON s.homework_id = h.id 
            AND s.student_username = ?
        LEFT JOIN private_homework_students phs ON phs.homework_id = h.id 
            AND phs.username = ?
        WHERE c.codecourse = ?
          AND h.status = 'approved'
          AND (
              h.homework_type = 0 
              OR 
              (h.homework_type = 1 AND phs.username IS NOT NULL)
          )
        ORDER BY c.id, h.deadline DESC, h.created_at DESC
    ");
    $hwStmt->execute([$student_username, $student_username, $codecourse]);
} else {
    // Limited admins only see homeworks they created
    $hwStmt = $conn->prepare("
        SELECT 
            h.*,
            c.name AS class_name,
            s.id AS submission_id,
            s.submission_text,
            s.file_path AS student_file_path,
            s.submitted_at,
            s.updated_at,
            s.professors_answer,
            phs.username AS private_assigned
        FROM classes c
        INNER JOIN homework_admin h ON h.class_id = c.id
        LEFT JOIN homework_student s ON s.homework_id = h.id 
            AND s.student_username = ?
        LEFT JOIN private_homework_students phs ON phs.homework_id = h.id 
            AND phs.username = ?
        WHERE c.codecourse = ?
          AND h.status = 'approved'
          AND h.admin_username = ?
          AND (
              h.homework_type = 0 
              OR 
              (h.homework_type = 1 AND phs.username IS NOT NULL)
          )
        ORDER BY c.id, h.deadline DESC, h.created_at DESC
    ");
    $hwStmt->execute([$student_username, $student_username, $codecourse, $currentAdmin]);
}

$homeworks = $hwStmt->fetchAll(PDO::FETCH_OBJ);

// Group homeworks by class
$homeworksByClass = [];
foreach ($homeworks as $hw) {
    $classId = $hw->class_id;
    if (!isset($homeworksByClass[$classId])) {
        $homeworksByClass[$classId] = [
            'class_name' => $hw->class_name,
            'is_enrolled' => in_array($classId, $enrolledClassIds),
            'homeworks' => []
        ];
    }
    $homeworksByClass[$classId]['homeworks'][] = $hw;
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title> گزارش تکالیف | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .class-section {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .class-header {
            background: #f5f5f5;
            padding: 12px 20px;
            font-weight: bold;
            font-size: 16px;
            border-bottom: 1px solid #e0e0e0;
        }
        .class-header.enrolled {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        .class-header.not-enrolled {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
        }
        .badge-enrolled {
            background: #4caf50;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 10px;
        }
        .badge-not-enrolled {
            background: #ff9800;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 10px;
        }
        .table-container {
            padding: 15px;
        }
        .hw-row {
            transition: background-color 0.2s;
        }
        .hw-row:hover {
            background-color: #f8f9fa;
        }
        .status-submitted {
            color: #4caf50;
            font-weight: bold;
        }
        .status-not-submitted {
            color: #f44336;
            font-weight: bold;
        }
        .status-deadline-passed {
            color: #9e9e9e;
            font-weight: bold;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .accordion-button:focus {
            box-shadow: none;
            border-color: #c8e6c9;
        }
        .submission-detail-label {
            font-weight: bold;
            color: #555;
            min-width: 120px;
            display: inline-block;
        }
        .submission-detail-row {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .submission-detail-row:last-child {
            border-bottom: none;
        }
        .text-break {
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">

                    <!-- Student & Course Info -->
                    <div class="card custom-card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">گزارش تکالیف</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>دانش آموز:</strong>
                                        <?= htmlspecialchars($user->fname . ' ' . $user->lname, ENT_QUOTES, 'UTF-8') ?>
                                        (<?= htmlspecialchars($student_username, ENT_QUOTES, 'UTF-8') ?>)
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>پایه:</strong>
                                        <?= htmlspecialchars($course->coursename ?? 'نامشخص', ENT_QUOTES, 'UTF-8') ?>
                                        (کد: <?= htmlspecialchars($codecourse, ENT_QUOTES, 'UTF-8') ?>)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Homeworks by Class -->
                    <?php if (empty($homeworksByClass)): ?>
                        <div class="alert alert-info text-center">
                            هیچ تکلیفی برای کلاس‌های این پایه تعریف نشده است.
                        </div>
                    <?php else: ?>
                        <?php
                        $homeworksByClass = array_filter($homeworksByClass, function($classId) use ($enrolledClassIds) {
                            return in_array($classId, $enrolledClassIds);
                        }, ARRAY_FILTER_USE_KEY);
                        foreach ($homeworksByClass as $classId => $classData): ?>
                            <div class="class-section">
                                <div class="class-header <?= $classData['is_enrolled'] ? 'enrolled' : 'not-enrolled' ?>">
                                    📚 کلاس: <?= htmlspecialchars($classData['class_name'], ENT_QUOTES, 'UTF-8') ?>

                                </div>

                                <div class="table-container">
                                    <?php if (empty($classData['homeworks'])): ?>
                                        <p class="text-center text-muted py-3">تکلیفی برای این کلاس تعریف نشده است.</p>
                                    <?php else: ?>
                                        <div class="accordion" id="accordionClass<?= $classId ?>">
                                            <?php $i = 1; foreach ($classData['homeworks'] as $hw): ?>
                                                <?php
                                                $hasSubmitted = !empty($hw->submission_id);
                                                $deadlineTs = deadline_to_ts($hw->deadline ?? '');
                                                $accordionId = 'hw_' . $classId . '_' . $hw->id;
                                                $isPrivate = ($hw->homework_type ?? 0) == 1;
                                                ?>

                                                <div class="accordion-item mb-2 border">
                                                    <!-- Header Row - Always visible -->
                                                    <div class="accordion-header d-flex align-items-center p-3 bg-light">
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex align-items-center flex-wrap">
                                                                <span class="fw-bold ms-2"><?= $i++ ?>.</span>
                                                                <span class="fw-bold"><?= htmlspecialchars($hw->title ?? '', ENT_QUOTES, 'UTF-8') ?></span>

                                                                <?php if ($isPrivate): ?>
                                                                    <span class="badge bg-info me-2" title="تکلیف اختصاصی - فقط برای دانش آموزان انتخاب شده">🔒 اختصاصی</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-light text-dark me-2" title="تکلیف عمومی - برای همه دانش آموزان کلاس">🌐 عمومی</span>
                                                                <?php endif; ?>

                                                                <?php if ($hasSubmitted): ?>
                                                                    <span class="badge bg-success me-2">✅ ارسال شده</span>
                                                                <?php else: ?>
                                                                    <?php if ($deadlineTs !== false && $deadlineTs < time()): ?>
                                                                        <span class="badge bg-danger me-2">❌ ارسال نشده</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning me-2">⏳ در انتظار ارسال</span>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="mt-2 text-muted small">
                                                                <span>📅 مهلت: <?= htmlspecialchars(deadline_to_jalali_display($hw->deadline ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php if ($hasSubmitted): ?>
                                                                    <span class="me-3">| 📤 ارسال: <?= htmlspecialchars(deadline_to_jalali_display($hw->submitted_at ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <?php if ($hasSubmitted): ?>
                                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>" aria-expanded="false">
                                                                مشاهده پاسخ ▼
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Collapsible Detail Section (only if submitted) -->
                                                    <?php if ($hasSubmitted): ?>
                                                        <div id="<?= $accordionId ?>" class="collapse">
                                                            <div class="p-3 bg-white">
                                                                <h6 class="text-primary mb-3">📋 جزئیات پاسخ ارسال شده</h6>

                                                                <div class="submission-detail-row">
                                                                    <span class="submission-detail-label">📝 متن پاسخ:</span>
                                                                    <span><?= !empty($hw->submission_text) ? htmlspecialchars($hw->submission_text, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>' ?></span>
                                                                </div>

                                                                <div class="submission-detail-row">
                                                                    <span class="submission-detail-label">📎 فایل پاسخ:</span>
                                                                    <?php if (!empty($hw->student_file_path)): ?>
                                                                        <a href="<?= url($hw->student_file_path) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                                            📥 دانلود فایل
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">—</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <div class="submission-detail-row">
                                                                    <span class="submission-detail-label">📅 تاریخ ارسال:</span>
                                                                    <span><?= htmlspecialchars(deadline_to_jalali_display($hw->submitted_at ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                                </div>

                                                                <?php if (!empty($hw->updated_at) && $hw->updated_at !== $hw->submitted_at): ?>
                                                                    <div class="submission-detail-row">
                                                                        <span class="submission-detail-label">🔄 آخرین ویرایش:</span>
                                                                        <span><?= htmlspecialchars(deadline_to_jalali_display($hw->updated_at ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div class="submission-detail-row">
                                                                    <span class="submission-detail-label">📄 فایل تکلیف اصلی:</span>
                                                                    <?php if (!empty($hw->file_path)): ?>
                                                                        <a href="<?= url($hw->file_path) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                            📥 دانلود فایل تکلیف
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">—</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if (!empty($hw->video_link)): ?>
                                                                    <div class="submission-detail-row">
                                                                        <span class="submission-detail-label">🎥 ویدیو تکلیف:</span>
                                                                        <a href="<?= htmlspecialchars($hw->video_link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-sm btn-outline-warning">
                                                                            ▶️ مشاهده ویدیو
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div class="submission-detail-row">
                                                                    <span class="submission-detail-label">🏆 نمره:</span>
                                                                    <?php
                                                                    $answer = $hw->professors_answer ?? '';
                                                                    if ($answer !== '' && $answer !== null): ?>
                                                                        <span class="badge bg-success fs-6"><?= htmlspecialchars($answer, ENT_QUOTES, 'UTF-8') ?></span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">هنوز نمره‌ای ثبت نشده است</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if (!empty($hw->description)): ?>
                                                                    <div class="submission-detail-row">
                                                                        <span class="submission-detail-label">ℹ️ توضیحات تکلیف:</span>
                                                                        <span class="text-break"><?= htmlspecialchars($hw->description, ENT_QUOTES, 'UTF-8') ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->
</body>
</html>