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
// Protecting against SESSION Hijacking
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
if (!Permision('permision_viewcourse')) {
    die("شما به این بخش دسترسی ندارید");
}

// Ensure code parameter exists in URL
$courseCode = isset($_GET['code']) ? intval($_GET['code']) : 0;
if ($courseCode <= 0) {
    die("کد پایه معتبر نیست.");
}

// Fetch course information
$stmtCourse = $conn->prepare("SELECT idcourse, coursename FROM codtbl_course WHERE codecourse = ?");
$stmtCourse->execute([$courseCode]);
$course = $stmtCourse->fetch(PDO::FETCH_OBJ);

if (!$course) {
    die("پایه مورد نظر یافت نشد.");
}
if (StaffCourseScopeRequired() && !StaffHasCourseAccess($courseCode)) {
    http_response_code(403);
    exit('شما به این پایه دسترسی ندارید.');
}

// Fetch all classes under this course code
$stmtClasses = $conn->prepare("SELECT id, name FROM classes WHERE codecourse = ?");
$stmtClasses->execute([$courseCode]);
$classList = $stmtClasses->fetchAll(PDO::FETCH_OBJ);

$stmtDars = $conn->prepare("SELECT d.id, d.dars, GROUP_CONCAT(cd.class_id ORDER BY cd.class_id) AS class_ids FROM dbdars d LEFT JOIN class_dars cd ON cd.dars_id = d.id WHERE d.codecourse = ? GROUP BY d.id, d.dars ORDER BY d.dars ASC");
$stmtDars->execute([$courseCode]);
$darsList = $stmtDars->fetchAll(PDO::FETCH_OBJ);

// Current administrator id for tracking who assigned the teacher
$currentAdminUser = $_SESSION['admin']['idadmin'];

// Handle Teacher Assignment Logic (PRG Pattern with ON DUPLICATE KEY UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher'])) {
    $teacherUsername = trim($_POST['teacher_username']);
    $selectedClasses = $_POST['class_ids'] ?? [];
    $selectedDars = array_values(array_unique(array_map('intval', $_POST['dars_ids'] ?? [])));

    $teacherStmt = $conn->prepare('SELECT 1 FROM dbadmin WHERE username = ? AND adminrole = 3 AND adminactive = 1 LIMIT 1');
    $teacherStmt->execute([$teacherUsername]);
    $validTeacher = (bool)$teacherStmt->fetchColumn();
    $validClassIds = array_map('intval', array_column($classList, 'id'));
    $validDarsIds = array_map('intval', array_column($darsList, 'id'));
    $selectedDars = array_values(array_intersect($selectedDars, $validDarsIds));

    if ($validTeacher && !empty($selectedDars)) {
        $targetClassIds = in_array('ALL', $selectedClasses, true)
            ? $validClassIds
            : array_values(array_intersect(array_map('intval', $selectedClasses), $validClassIds));

        if (empty($targetClassIds)) {
            $_SESSION['flash_message'] = "لطفاً حداقل یک کلاس یا گزینه تخصیص به کل پایه را انتخاب کنید.";
            $_SESSION['flash_type'] = "warning";
            header("Location: teacher-assign.php?code=" . $courseCode);
            exit();
        }

        $upsertStmt = $conn->prepare("INSERT INTO course_teacher (class_id, teacher_username, created_by, created_at) 
                                            VALUES (?, ?, ?, NOW())
                                            ON DUPLICATE KEY UPDATE id = id;    ");
        $darsUpsertStmt = $conn->prepare("INSERT INTO course_teacher_dars (class_id, dars_id, teacher_username, created_by, created_at)
                                          VALUES (?, ?, ?, ?, NOW())
                                          ON DUPLICATE KEY UPDATE created_by = VALUES(created_by)");
        $validClassDarsStmt = $conn->prepare('SELECT 1 FROM class_dars WHERE class_id = ? AND dars_id = ? LIMIT 1');
        try {
            $conn->beginTransaction();
            foreach ($targetClassIds as $classId) {
                $upsertStmt->execute([$classId, $teacherUsername, $currentAdminUser]);
                foreach ($selectedDars as $darsId) {
                    $validClassDarsStmt->execute([$classId, $darsId]);
                    if ($validClassDarsStmt->fetchColumn()) {
                        $darsUpsertStmt->execute([$classId, $darsId, $teacherUsername, $currentAdminUser]);
                    }
                }
            }
            $conn->commit();
            $_SESSION['flash_message'] = "معلم با موفقیت به کلاس‌ها و درس‌های انتخابی تخصیص داده شد.";
            $_SESSION['flash_type'] = "success";
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['flash_message'] = "ثبت تخصیص انجام نشد؛ لطفاً دوباره تلاش کنید.";
            $_SESSION['flash_type'] = "danger";
        }
    } elseif (!$validTeacher) {
        $_SESSION['flash_message'] = "لطفاً یک معلم فعال و معتبر انتخاب نمایید.";
        $_SESSION['flash_type'] = "danger";
    } else {
        $_SESSION['flash_message'] = "لطفاً حداقل یک درس را انتخاب نمایید.";
        $_SESSION['flash_type'] = "warning";
    }

    // Redirect to prevent duplicate submission on page refresh
    header("Location: teacher-assign.php?code=" . $courseCode);
    exit();
}

// Handle Removal of Teacher Assignment (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_assignment'])) {
    $assignmentId = intval($_POST['assignment_id']);
    if ($assignmentId > 0) {
        $delStmt = $conn->prepare("
            DELETE ct FROM course_teacher ct
            INNER JOIN classes c ON c.id = ct.class_id
            WHERE ct.id = ? AND c.codecourse = ?
        ");
        $delStmt->execute([$assignmentId, $courseCode]);
        if ($delStmt->rowCount() > 0) {
            $_SESSION['flash_message'] = "تخصیص معلم با موفقیت حذف گردید.";
            $_SESSION['flash_type'] = "info";
        }
    }

    // Redirect to prevent duplicate submission on page refresh
    header("Location: teacher-assign.php?code=" . $courseCode);
    exit();
}

// Read and clear flash message
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$stmtTeachers = $conn->prepare("SELECT username, fname, lname FROM dbadmin WHERE adminrole = 3 AND adminactive = 1 ORDER BY lname, fname");
$stmtTeachers->execute();
$teacherList = $stmtTeachers->fetchAll(PDO::FETCH_OBJ);

$stmtOverview = $conn->prepare("
    SELECT 
        c.id AS class_id, 
        c.name AS class_name, 
        ct.id AS assignment_id, 
        ct.teacher_username, 
        ct.created_by,
        a.fname, 
        a.lname 
        ,(SELECT GROUP_CONCAT(DISTINCT d.dars ORDER BY d.dars SEPARATOR '، ')
          FROM course_teacher_dars ctd
          INNER JOIN dbdars d ON d.id = ctd.dars_id
          INNER JOIN class_dars cd ON cd.class_id = ctd.class_id AND cd.dars_id = ctd.dars_id
          WHERE ctd.class_id = ct.class_id AND ctd.teacher_username = ct.teacher_username) AS dars_names
    FROM classes c
    LEFT JOIN course_teacher ct ON c.id = ct.class_id
    LEFT JOIN dbadmin a ON ct.teacher_username = a.username
    WHERE c.codecourse = ?
    ORDER BY c.id ASC
");
$stmtOverview->execute([$courseCode]);
$rawAssignments = $stmtOverview->fetchAll(PDO::FETCH_OBJ);

// Format retrieved rows into grouped classes
$classesWithTeachers = [];
foreach ($rawAssignments as $row) {
    if (!isset($classesWithTeachers[$row->class_id])) {
        $classesWithTeachers[$row->class_id] = [
            'class_id' => $row->class_id,
            'class_name' => $row->class_name,
            'teachers' => []
        ];
    }
    if ($row->assignment_id) {

        $classesWithTeachers[$row->class_id]['teachers'][] = [
            'assignment_id' => $row->assignment_id,
            'username' => $row->teacher_username,
            'name' => trim($row->fname . ' ' . $row->lname) ?: $row->teacher_username,
            'created_by' => $row->created_by ?? '-'
            ,'dars_names' => $row->dars_names ?? ''
        ];
    }
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="تخصیص معلم به پایه، کلاس و درس">
    <meta name="author" content="hamsepar.ir">
    <title> تخصیص معلم | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">

<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <!-- Page Header -->
                <div class="page-header my-3">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">تخصیص معلم به کلاس و درس</h2>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">پایه</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($course->coursename); ?> </li>
                        </ol>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Form Card -->
                <div class="card custom-card mb-4">
                    <div class="card-header border-bottom">
                        <h6 class="main-content-label mb-0">فرم تخصیص معلم</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-row">
                                <!-- Dropdown 1: Teachers -->
                                <div class="form-group col-md-4">
                                    <label for="teacher_username" class="font-weight-bold">انتخاب معلم:</label>
                                    <select name="teacher_username" id="teacher_username" class="form-control" required>
                                        <option value="">-- معلم مورد نظر را انتخاب کنید --</option>
                                        <?php foreach ($teacherList as $teacher): ?>
                                            <option value="<?php echo htmlspecialchars($teacher->username); ?>">
                                                <?php echo htmlspecialchars($teacher->fname . ' ' . $teacher->lname . ' (' . $teacher->username . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Dropdown 2: Classes -->
                                <div class="form-group col-md-4">
                                    <label for="class_ids" class="font-weight-bold">انتخاب کلاس‌ها:</label>
                                    <select name="class_ids[]" id="class_ids" class="form-control p-2" multiple size="6" required style="border-radius: 8px; overflow-y: auto;">
                                        <option value="ALL" class="font-weight-bold border-bottom pb-1 mb-1" style="border-radius: 4px; padding: 6px 10px;">
                                             -- تخصیص به کل پایه (همه کلاس‌ها) --
                                        </option>
                                        <?php foreach ($classList as $cls): ?>
                                            <option value="<?php echo $cls->id; ?>" style="border-radius: 4px; padding: 6px 10px; margin-top: 2px;">
                                                <?php echo htmlspecialchars($cls->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted mt-2">
                                        برای انتخاب یا لغو انتخاب هر کلاس، کافیست روی آن کلیک کنید.
                                    </small>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="dars_ids" class="font-weight-bold">انتخاب درس‌ها:</label>
                                    <select name="dars_ids[]" id="dars_ids" class="form-control p-2" multiple size="6" required style="border-radius: 8px; overflow-y: auto;">
                                        <?php foreach ($darsList as $dars): ?>
                                            <option value="<?php echo (int)$dars->id; ?>"
                                                    data-class-ids="<?php echo htmlspecialchars((string)$dars->class_ids); ?>"
                                                    style="border-radius: 4px; padding: 6px 10px; margin-top: 2px;">
                                                <?php echo htmlspecialchars($dars->dars); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted mt-2">می‌توانید یک یا چند درس را هم‌زمان انتخاب کنید.</small>
                                </div>

                                <!-- Submit Button -->
                                <div class="form-group col-md-12 d-flex justify-content-end">
                                    <button type="submit" name="assign_teacher" class="btn btn-primary btn-block">
                                        ثبت تخصیص
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Classes & Assigned Teachers Table -->
                <div class="card custom-card">
                    <div class="card-header border-bottom">
                        <h6 class="main-content-label mb-0">لیست کلاس‌ها، معلمان و درس‌های تخصیص داده شده</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example1" class="table table-bordered table-striped text-center align-middle">
                                <thead class="thead-light">
                                <tr>
                                    <th style="width: 80px;">#</th>
                                    <th>نام کلاس</th>
                                    <th>معلمان و درس‌های تخصیص داده شده</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($classesWithTeachers)): ?>
                                    <?php $i = 1; foreach ($classesWithTeachers as $classData): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td class="font-weight-bold"><?php echo htmlspecialchars($classData['class_name']); ?></td>
                                            <td>
                                                <?php if (!empty($classData['teachers'])): ?>
                                                    <div class="d-flex flex-wrap justify-content-center" style="gap: 8px;">
                                                        <?php foreach ($classData['teachers'] as $t): ?>
                                                            <span class="badge badge-light border p-2 d-inline-flex align-items-center"
                                                                  title="تخصیص توسط: <?php echo htmlspecialchars($t['created_by']); ?>"
                                                                  data-toggle="tooltip">
                                                                    <span class="ml-2">
                                                                        <?php echo htmlspecialchars($t['name']); ?>
                                                                        <small class="d-block text-muted mt-1">
                                                                            <?php echo $t['dars_names'] !== '' ? htmlspecialchars($t['dars_names']) : 'درس مشخص نشده (دسترسی قدیمی)'; ?>
                                                                        </small>
                                                                    </span>
                                                                    <form method="post" action="" class="d-inline mb-0 ml-1">
                                                                        <input type="hidden" name="assignment_id" value="<?php echo $t['assignment_id']; ?>">
                                                                        <button type="submit" name="remove_assignment" class="close text-danger border-0 bg-transparent p-0" style="font-size: 1.2rem; line-height: 1;" onclick="return confirm('آیا از حذف این استاد مطمئن هستید؟');">
                                                                            &times;
                                                                        </button>
                                                                    </form>
                                                                </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                <span class="text-muted small">معلمی تخصیص داده نشده است</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-muted">هیچ کلاسی برای این کد پایه یافت نشد.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('class_ids');
        const darsSelect = document.getElementById('dars_ids');

        function updateOptionStyles() {
            Array.from(select.options).forEach(opt => {
                if (opt.selected) {
                    if (opt.value === 'ALL') {
                        opt.style.backgroundColor = '#28a745';
                        opt.style.color = '#ffffff';
                    } else {
                        opt.style.backgroundColor = '#0d6efd';
                        opt.style.color = '#ffffff';
                    }
                } else {
                    opt.style.backgroundColor = 'transparent';
                    opt.style.color = '#333333';
                }
            });
        }

        function filterDarsOptions() {
            const selectedValues = Array.from(select.selectedOptions).map(opt => opt.value);
            const allSelected = selectedValues.includes('ALL');
            const selectedClassIds = allSelected
                ? Array.from(select.options).filter(opt => opt.value !== 'ALL').map(opt => opt.value)
                : selectedValues;

            Array.from(darsSelect.options).forEach(opt => {
                const classIds = (opt.dataset.classIds || '').split(',').filter(Boolean);
                const visible = selectedClassIds.length === 0 || selectedClassIds.some(id => classIds.includes(id));
                opt.hidden = !visible;
                opt.disabled = !visible;
                if (!visible) opt.selected = false;
            });
        }

        select.addEventListener('mousedown', function (e) {
            if (e.target.tagName === 'OPTION') {
                e.preventDefault();

                const option = e.target;
                const isAllOption = option.value === 'ALL';

                if (isAllOption) {
                    const newState = !option.selected;
                    Array.from(select.options).forEach(opt => {
                        opt.selected = newState;
                    });
                } else {
                    option.selected = !option.selected;

                    const allOption = select.querySelector('option[value="ALL"]');
                    if (allOption && !option.selected) {
                        allOption.selected = false;
                    }
                }

                select.focus();
                updateOptionStyles();

                const event = new Event('change', { bubbles: true });
                select.dispatchEvent(event);
            }
        });

        select.addEventListener('change', filterDarsOptions);

        updateOptionStyles();
        filterDarsOptions();

        darsSelect.addEventListener('mousedown', function (e) {
            if (e.target.tagName === 'OPTION') {
                e.preventDefault();
                e.target.selected = !e.target.selected;
                darsSelect.focus();
            }
        });
    });
</script>

</body>
</html>
