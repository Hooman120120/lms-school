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

if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

if ((int)($_SESSION['admin']['adminrole'] ?? -1) !== 0) {
    echo 'دسترسی ندارید';
    die();
}

function academicStudentsFaToEnDigits(string $value): string
{
    return str_replace(
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        $value
    );
}

function academicStudentsToGregorian(?string $input): ?string
{
    $value = academicStudentsFaToEnDigits(trim((string)$input));
    $value = str_replace('-', '/', $value);

    if (!preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $value)) {
        return null;
    }

    [$year, $month, $day] = array_map('intval', explode('/', $value));
    if ($year >= 1700) {
        return checkdate($month, $day, $year)
            ? sprintf('%04d-%02d-%02d', $year, $month, $day)
            : null;
    }

    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
        return null;
    }

    $gregorian = jalali_to_gregorian($year, $month, $day);
    return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
}

$academicYear = getSchoolAcademicYearRange();
$startJalali = trim($_POST['startdate'] ?? $academicYear['start_jalali']);
$endJalali = trim($_POST['enddate'] ?? $academicYear['end_jalali']);
$selectedCourse = filter_input(INPUT_POST, 'course_code', FILTER_VALIDATE_INT);
$selectedClass = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
$selectedCourse = $selectedCourse && $selectedCourse > 0 ? $selectedCourse : 0;
$selectedClass = $selectedClass && $selectedClass > 0 ? $selectedClass : 0;
$applied = isset($_POST['apply']);
$errorMessage = '';
$users = [];

$coursesStmt = $conn->query('SELECT codecourse, coursename, paye FROM codtbl_course ORDER BY paye ASC, coursename ASC');
$courses = $coursesStmt->fetchAll(PDO::FETCH_OBJ);
$classesStmt = $conn->query('SELECT id, codecourse, name FROM classes ORDER BY codecourse ASC, name ASC');
$classes = $classesStmt->fetchAll(PDO::FETCH_OBJ);

$startGregorian = academicStudentsToGregorian($startJalali);
$endGregorian = academicStudentsToGregorian($endJalali);
$endExclusive = $endGregorian ? date('Y-m-d', strtotime($endGregorian . ' +1 day')) : null;

if ($startGregorian && $endGregorian && $startGregorian > $endGregorian) {
    $errorMessage = 'تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.';
} elseif (!$startGregorian || !$endGregorian) {
    $errorMessage = 'بازه تاریخ را به‌صورت صحیح وارد کنید.';
}

if ($selectedClass > 0) {
    if ($selectedCourse <= 0) {
        $errorMessage = 'برای انتخاب کلاس، ابتدا پایه را انتخاب کنید.';
    } else {
        $classValidation = $conn->prepare('SELECT COUNT(*) FROM classes WHERE id = ? AND codecourse = ?');
        $classValidation->execute([$selectedClass, $selectedCourse]);
        if ((int)$classValidation->fetchColumn() === 0) {
            $errorMessage = 'کلاس انتخاب‌شده متعلق به پایه انتخاب‌شده نیست.';
        }
    }
}

// Show the current academic year's registrations immediately on first load.
if ($errorMessage === '') {
    $sql = '
        SELECT p.*
        FROM dbpersonal p
        WHERE p.datecreated >= ? AND p.datecreated < ?
    ';
    $params = [$startGregorian, $endExclusive];

    if ($selectedCourse > 0) {
        $sql .= '
            AND EXISTS (
                SELECT 1
                FROM dbcourse dc
                WHERE dc.username = p.codemeli
                  AND dc.codecourse = ?
        ';
        $params[] = $selectedCourse;

        if ($selectedClass > 0) {
            $sql .= ' AND dc.class_id = ? ';
            $params[] = $selectedClass;
        }

        $sql .= ') ';
    }

    $sql .= ' ORDER BY p.datecreated DESC, p.idpersonal DESC ';
    $usersStmt = $conn->prepare($sql);
    $usersStmt->execute($params);
    $users = $usersStmt->fetchAll(PDO::FETCH_OBJ);
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="دانش‌آموزان سال تحصیلی جاری">
    <title>دانش‌آموزان سال تحصیلی جاری | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-12">
                        <div class="card custom-card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">دانش‌آموزان سال تحصیلی <?= htmlspecialchars($academicYear['label']) ?></h6>
                                    <small class="text-muted">فهرست بر اساس تاریخ ثبت‌نام دانش‌آموزان</small>
                                </div>
                                <span class="badge badge-primary"><?= number_format(count($users)) ?> نفر</span>
                            </div>
                            <form method="post">
                                <div class="card-body">
                                    <div class="row align-items-end">
                                        <div class="col-xl-2 col-md-4 mb-3">
                                            <label class="form-label d-block">وضعیت</label>
                                            <button type="button" class="btn btn-success w-100 active">همه</button>
                                        </div>
                                        <div class="col-xl-3 col-md-4 mb-3">
                                            <label class="form-label" for="courseCode">پایه</label>
                                            <select class="form-control" name="course_code" id="courseCode">
                                                <option value="">همه پایه‌ها</option>
                                                <?php foreach ($courses as $course) {
                                                    $courseTitle = trim((string)$course->coursename);
                                                    if (!empty($course->paye) && strpos($courseTitle, (string)$course->paye) === false) {
                                                        $courseTitle .= ' — پایه ' . $course->paye;
                                                    }
                                                    ?>
                                                    <option value="<?= (int)$course->codecourse ?>" <?= $selectedCourse === (int)$course->codecourse ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($courseTitle ?: ('پایه ' . $course->codecourse)) ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-xl-3 col-md-4 mb-3">
                                            <label class="form-label" for="classId">کلاس</label>
                                            <select class="form-control" name="class_id" id="classId" <?= $selectedCourse <= 0 ? 'disabled' : '' ?>>
                                                <option value="">همه کلاس‌ها</option>
                                                <?php foreach ($classes as $class) { ?>
                                                    <option value="<?= (int)$class->id ?>"
                                                            data-course="<?= (int)$class->codecourse ?>"
                                                            <?= $selectedClass === (int)$class->id ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($class->name) ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-xl-2 col-md-4 mb-3">
                                            <label class="form-label">از تاریخ</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fe fe-calendar"></i></span>
                                                <input class="form-control fc-datepicker" type="text" name="startdate" value="<?= htmlspecialchars($startJalali) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-xl-2 col-md-4 mb-3">
                                            <label class="form-label">تا تاریخ</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fe fe-calendar"></i></span>
                                                <input class="form-control fc-datepicker" type="text" name="enddate" value="<?= htmlspecialchars($endJalali) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-12 text-center">
                                            <button type="submit" name="apply" value="1" class="btn btn-primary px-5">
                                                <i class="fe fe-filter ml-1"></i> اعمال فیلتر
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <?php if ($errorMessage !== '') { ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
                        <?php } ?>

                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>نام و نام خانوادگی</th>
                                            <th>کد ملی</th>
                                            <th>تاریخ ثبت‌نام</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if ($errorMessage !== '') { ?>
                                            <tr><td colspan="4">ابتدا خطای فیلتر را اصلاح کنید.</td></tr>
                                        <?php } elseif (empty($users)) { ?>
                                            <tr><td colspan="4">موردی یافت نشد.</td></tr>
                                        <?php } else { ?>
                                            <?php foreach ($users as $user) {
                                                $jalaliCreated = '';
                                                if (!empty($user->datecreated)) {
                                                    [$gy, $gm, $gd] = array_map('intval', explode('-', $user->datecreated));
                                                    $jalaliCreated = implode('/', gregorian_to_jalali($gy, $gm, $gd));
                                                }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open('suporting.php?Id=<?= rawurlencode($user->codemeli) ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=1,left=20,top=30,status=0,scrollbars=1,location=0')">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars(trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')) ?: '-') ?></td>
                                                    <td><?= htmlspecialchars($user->codemeli ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($jalaliCreated ?: '-') ?></td>
                                                </tr>
                                            <?php } ?>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../footers.php'; ?>
<script>
    (function () {
        const courseSelect = document.getElementById('courseCode');
        const classSelect = document.getElementById('classId');
        const classOptions = Array.from(classSelect.querySelectorAll('option[data-course]'));

        function refreshClasses(resetSelection) {
            const courseCode = courseSelect.value;
            if (resetSelection) {
                classSelect.value = '';
            }

            classOptions.forEach(function (option) {
                const visible = courseCode !== '' && option.dataset.course === courseCode;
                option.hidden = !visible;
                option.disabled = !visible;
            });

            classSelect.disabled = courseCode === '';
        }

        courseSelect.addEventListener('change', function () {
            refreshClasses(true);
        });
        refreshClasses(false);
    })();
</script>
</body>
</html>
