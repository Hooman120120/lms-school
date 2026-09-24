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
    header('location: ' . url('admin/login.php'));
    exit;
}
if ($_SESSION['admin']['ip'] !== GetIp() && $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}
$session = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($session['session_id'] ?? null)) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}

$currentUser = (string)$_SESSION['admin']['user'];
$isTeacher = (int)($_SESSION['admin']['adminrole'] ?? -1) === 3;
$academicYear = getSchoolAcademicYearRange();
$lockedCourseCode = !$isTeacher ? (int)($_GET['course'] ?? 0) : 0;
$lockedCourse = null;
if (!$isTeacher) {
    if ($lockedCourseCode <= 0) {
        header('Location: ' . url('admin/course/Active-course.php'));
        exit;
    }
    if (!StaffHasCourseAccess($lockedCourseCode, $currentUser)) {
        http_response_code(403);
        exit('شما به طرح درس‌های این پایه دسترسی ندارید.');
    }
    $lockedCourseStmt = $conn->prepare('SELECT codecourse, coursename FROM codtbl_course WHERE codecourse = ? LIMIT 1');
    $lockedCourseStmt->execute([$lockedCourseCode]);
    $lockedCourse = $lockedCourseStmt->fetch(PDO::FETCH_OBJ);
    if (!$lockedCourse) {
        http_response_code(404);
        exit('پایه مورد نظر یافت نشد.');
    }
}

function lessonPlanToEnglishDigits(string $value): string
{
    return strtr(trim($value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
}

function lessonPlanJalaliToGregorian(string $value): ?string
{
    $value = str_replace('-', '/', lessonPlanToEnglishDigits($value));
    if (!preg_match('~^(\d{4})/(\d{1,2})/(\d{1,2})$~', $value, $parts)) {
        return null;
    }
    $jy = (int)$parts[1]; $jm = (int)$parts[2]; $jd = (int)$parts[3];
    if (!jcheckdate($jm, $jd, $jy)) {
        return null;
    }
    $date = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf('%04d-%02d-%02d', $date[0], $date[1], $date[2]);
}

function lessonPlanGregorianToJalali(string $value): string
{
    $parts = array_map('intval', explode('-', $value));
    if (count($parts) !== 3) return '';
    $date = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
    return sprintf('%04d/%02d/%02d', $date[0], $date[1], $date[2]);
}

function lessonPlanTeacherOptions(PDO $conn, string $teacher): array
{
    $stmt = $conn->prepare("
        SELECT DISTINCT c.id AS class_id, c.name AS class_name, c.codecourse,
               cr.coursename, d.id AS dars_id, d.dars AS dars_name,
               l.id AS lesson_id, l.name AS lesson_name
        FROM course_teacher ct
        INNER JOIN classes c ON c.id = ct.class_id
        INNER JOIN codtbl_course cr ON cr.codecourse = c.codecourse
        INNER JOIN class_dars cd ON cd.class_id = c.id
        INNER JOIN dbdars d ON d.id = cd.dars_id AND d.codecourse = c.codecourse
        INNER JOIN class_lesson cl ON cl.class_id = c.id
        INNER JOIN dblesson l ON l.id = cl.lesson_id AND l.dars_id = d.id AND l.codecourse = c.codecourse
        WHERE ct.teacher_username = ?
          AND (
              NOT EXISTS (SELECT 1 FROM course_teacher_dars x WHERE x.class_id = c.id AND x.teacher_username = ct.teacher_username)
              OR EXISTS (SELECT 1 FROM course_teacher_dars x WHERE x.class_id = c.id AND x.dars_id = d.id AND x.teacher_username = ct.teacher_username)
          )
        ORDER BY cr.coursename, c.name, d.dars, l.name
    ");
    $stmt->execute([$teacher]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$teacherOptions = $isTeacher ? lessonPlanTeacherOptions($conn, $currentUser) : [];
$message = $_SESSION['lesson_plan_message'] ?? '';
$messageType = $_SESSION['lesson_plan_message_type'] ?? 'success';
unset($_SESSION['lesson_plan_message'], $_SESSION['lesson_plan_message_type']);

if ($isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_SESSION['token'], $_POST['token']) || !hash_equals((string)$_SESSION['token'], (string)$_POST['token'])) {
            throw new RuntimeException('اعتبار فرم منقضی شده است؛ صفحه را تازه‌سازی کنید.');
        }
        if (isset($_POST['delete_plan'])) {
            $delete = $conn->prepare('DELETE FROM teacher_lesson_plans WHERE id = ? AND teacher_username = ?');
            $delete->execute([(int)$_POST['plan_id'], $currentUser]);
            $_SESSION['lesson_plan_message'] = 'طرح درس حذف شد.';
            $_SESSION['lesson_plan_message_type'] = 'info';
        } else {
            $classId = (int)($_POST['class_id'] ?? 0);
            $darsId = (int)($_POST['dars_id'] ?? 0);
            $lessonId = (int)($_POST['lesson_id'] ?? 0);
            $planDate = lessonPlanJalaliToGregorian((string)($_POST['plan_date'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            if (mb_strlen($description, 'UTF-8') > 2000) {
                throw new RuntimeException('توضیحات طرح درس نباید بیشتر از ۲۰۰۰ نویسه باشد.');
            }
            if (!$planDate) {
                throw new RuntimeException('تاریخ شمسی واردشده معتبر نیست.');
            }
            $valid = false;
            foreach ($teacherOptions as $option) {
                if ((int)$option['class_id'] === $classId && (int)$option['dars_id'] === $darsId && (int)$option['lesson_id'] === $lessonId) {
                    $valid = true; break;
                }
            }
            if (!$valid) {
                throw new RuntimeException('ترکیب کلاس، درس و سرفصل انتخاب‌شده در تخصیص‌های شما وجود ندارد.');
            }
            $planId = (int)($_POST['plan_id'] ?? 0);
            if ($planId > 0) {
                $stmt = $conn->prepare('UPDATE teacher_lesson_plans SET class_id = ?, dars_id = ?, lesson_id = ?, plan_date = ?, description = ? WHERE id = ? AND teacher_username = ?');
                $stmt->execute([$classId, $darsId, $lessonId, $planDate, $description !== '' ? $description : null, $planId, $currentUser]);
                if ($stmt->rowCount() === 0) {
                    $owned = $conn->prepare('SELECT 1 FROM teacher_lesson_plans WHERE id = ? AND teacher_username = ?');
                    $owned->execute([$planId, $currentUser]);
                    if (!$owned->fetchColumn()) throw new RuntimeException('طرح درس مورد نظر یافت نشد.');
                }
                $_SESSION['lesson_plan_message'] = 'طرح درس ویرایش شد.';
            } else {
                $stmt = $conn->prepare('INSERT INTO teacher_lesson_plans (teacher_username, class_id, dars_id, lesson_id, plan_date, description) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$currentUser, $classId, $darsId, $lessonId, $planDate, $description !== '' ? $description : null]);
                $_SESSION['lesson_plan_message'] = 'طرح درس با موفقیت ثبت شد.';
            }
        }
        header('Location: lesson-plans.php');
        exit;
    } catch (PDOException $exception) {
        $message = $exception->getCode() === '23000' ? 'این طرح درس با همین تاریخ، کلاس، درس و سرفصل قبلاً ثبت شده است.' : 'ثبت اطلاعات انجام نشد.';
        $messageType = 'danger';
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        $messageType = 'danger';
    }
}

$editingPlan = null;
if ($isTeacher && isset($_GET['edit'])) {
    $stmt = $conn->prepare('SELECT * FROM teacher_lesson_plans WHERE id = ? AND teacher_username = ?');
    $stmt->execute([(int)$_GET['edit'], $currentUser]);
    $editingPlan = $stmt->fetch(PDO::FETCH_OBJ) ?: null;
}

$conditions = ['lp.plan_date >= ?', 'lp.plan_date < ?'];
$params = [$academicYear['start_gregorian'], $academicYear['end_exclusive_gregorian']];
if ($isTeacher) {
    $conditions[] = 'lp.teacher_username = ?';
    $params[] = $currentUser;
} elseif (StaffCourseScopeRequired($currentUser)) {
    $conditions[] = 'EXISTS (SELECT 1 FROM school_staff_courses ssc WHERE ssc.codecourse = c.codecourse AND ssc.admin_username = ?)';
    $params[] = $currentUser;
}

if (!$isTeacher) {
    $conditions[] = 'c.codecourse = ?';
    $params[] = $lockedCourseCode;
}

$filterMap = ['teacher' => 'lp.teacher_username', 'class' => 'lp.class_id', 'dars' => 'lp.dars_id'];
if (!$isTeacher) {
    foreach ($filterMap as $key => $column) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $conditions[] = "$column = ?";
            $params[] = $key === 'teacher' ? (string)$_GET[$key] : (int)$_GET[$key];
        }
    }
}
foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
    if (!empty($_GET[$key])) {
        $date = lessonPlanJalaliToGregorian((string)$_GET[$key]);
        if ($date) {
            $conditions[] = "lp.plan_date $operator ?";
            $params[] = $date;
        }
    }
}

$plansSql = "
    SELECT lp.*, c.name AS class_name, c.codecourse, cr.coursename,
           d.dars AS dars_name, l.name AS lesson_name,
           CONCAT(COALESCE(a.fname, ''), ' ', COALESCE(a.lname, '')) AS teacher_name
    FROM teacher_lesson_plans lp
    INNER JOIN dbadmin a ON a.username = lp.teacher_username
    INNER JOIN classes c ON c.id = lp.class_id
    INNER JOIN codtbl_course cr ON cr.codecourse = c.codecourse
    INNER JOIN dbdars d ON d.id = lp.dars_id
    INNER JOIN dblesson l ON l.id = lp.lesson_id
    WHERE " . implode(' AND ', $conditions) . "
    ORDER BY lp.plan_date DESC, lp.id DESC
";
$plansStmt = $conn->prepare($plansSql);
$plansStmt->execute($params);
$plans = $plansStmt->fetchAll(PDO::FETCH_OBJ);

$filterRows = [];
if (!$isTeacher) {
    $scopeSql = ' WHERE c.codecourse = ?';
    $scopeParams = [$lockedCourseCode];
    if (StaffCourseScopeRequired($currentUser)) {
        $scopeSql .= ' AND EXISTS (SELECT 1 FROM school_staff_courses ssc WHERE ssc.codecourse = c.codecourse AND ssc.admin_username = ?)';
        $scopeParams[] = $currentUser;
    }
    $filterStmt = $conn->prepare("SELECT DISTINCT a.username, a.fname, a.lname, c.id AS class_id, c.name AS class_name, c.codecourse, cr.coursename, d.id AS dars_id, d.dars AS dars_name FROM teacher_lesson_plans lp INNER JOIN dbadmin a ON a.username=lp.teacher_username INNER JOIN classes c ON c.id=lp.class_id INNER JOIN codtbl_course cr ON cr.codecourse=c.codecourse INNER JOIN dbdars d ON d.id=lp.dars_id" . $scopeSql . ' ORDER BY cr.coursename, c.name, d.dars');
    $filterStmt->execute($scopeParams);
    $filterRows = $filterStmt->fetchAll(PDO::FETCH_OBJ);
}

$token = bin2hex(random_bytes(32));
$_SESSION['token'] = $token;
$todayJalali = jdate('Y/m/d', time(), '', '', 'en');
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo m('page.lesson_plans') ?> | <?= __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div id="global-loader"><img src="<?= url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری"></div>
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid"><div class="inner-body">
            <div class="page-header"><div>
                <h2 class="main-content-title tx-24"><?= $isTeacher ? 'طرح درس من' : m('label.lesson_plan_of_teachers_prefix') . htmlspecialchars($lockedCourse->coursename) . '»' ?></h2>
                <p class="text-muted mb-0">سال تحصیلی <?= htmlspecialchars($academicYear['label']) ?> — برنامه سرفصل‌های تدریس در هر کلاس</p>
            </div></div>

            <?php if ($message): ?><div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show"><?= htmlspecialchars($message) ?><button class="close" data-bs-dismiss="alert">×</button></div><?php endif; ?>

            <?php if ($isTeacher): ?>
            <div class="card custom-card mb-4"><div class="card-header"><h6 class="main-content-label mb-0"><?= $editingPlan ? 'ویرایش طرح درس' : 'ثبت طرح درس جدید' ?></h6></div><div class="card-body">
                <?php if (!$teacherOptions): ?>
                    <div class="alert alert-warning mb-0">برای کلاس‌ها و درس‌های تخصیص‌یافته شما هنوز سرفصلی تعریف نشده است. ابتدا سرفصل درس را در اطلاعات کلاس تعریف کنید.</div>
                <?php else: ?>
                <form method="post"><input type="hidden" name="token" value="<?= $token ?>"><input type="hidden" name="plan_id" value="<?= (int)($editingPlan->id ?? 0) ?>">
                    <div class="row">
                        <div class="col-md-3 form-group"><label>تاریخ تدریس</label><input class="form-control fc-datepicker" name="plan_date" placeholder="1405/07/15" required value="<?= htmlspecialchars($editingPlan ? lessonPlanGregorianToJalali($editingPlan->plan_date) : $todayJalali) ?>"></div>
                        <div class="col-md-3 form-group"><label>پایه و کلاس</label><select class="form-control" id="plan-class" name="class_id" required><option value="">انتخاب کنید</option></select></div>
                        <div class="col-md-3 form-group"><label>درس</label><select class="form-control" id="plan-dars" name="dars_id" required disabled><option value="">ابتدا کلاس را انتخاب کنید</option></select></div>
                        <div class="col-md-3 form-group"><label>سرفصل</label><select class="form-control" id="plan-lesson" name="lesson_id" required disabled><option value="">ابتدا درس را انتخاب کنید</option></select></div>
                        <div class="col-12 form-group"><label>توضیحات تکمیلی <span class="text-muted">(اختیاری)</span></label><textarea class="form-control" name="description" rows="3" maxlength="2000" placeholder="در صورت نیاز، توضیحات یا نکات تکمیلی این جلسه را بنویسید."><?= htmlspecialchars((string)($editingPlan->description ?? '')) ?></textarea><small class="text-muted">حداکثر ۲۰۰۰ نویسه</small></div>
                    </div>
                    <button class="btn btn-primary" type="submit" name="save_plan"><?= $editingPlan ? 'ذخیره تغییرات' : 'ثبت طرح درس' ?></button>
                    <?php if ($editingPlan): ?><a class="btn btn-outline-secondary" href="lesson-plans.php">انصراف</a><?php endif; ?>
                </form>
                <?php endif; ?>
            </div></div>
            <?php else: ?>
            <div class="card custom-card mb-4"><div class="card-body"><form method="get"><input type="hidden" name="course" value="<?= $lockedCourseCode ?>"><div class="row">
                <div class="col-lg-2 col-md-4 form-group"><label><?php echo t('teacher') ?></label><select name="teacher" class="form-control"><option value="">همه</option><?php $seen=[]; foreach($filterRows as $r): if(isset($seen[$r->username]))continue; $seen[$r->username]=1; ?><option value="<?= htmlspecialchars($r->username) ?>"<?= ($_GET['teacher']??'')===$r->username?' selected':'' ?>><?= htmlspecialchars(trim($r->fname.' '.$r->lname) ?: $r->username) ?></option><?php endforeach; ?></select></div>
                <div class="col-lg-2 col-md-4 form-group"><label>کلاس</label><select name="class" class="form-control"><option value="">همه</option><?php $seen=[]; foreach($filterRows as $r): if(isset($seen[$r->class_id]))continue; $seen[$r->class_id]=1; ?><option value="<?= (int)$r->class_id ?>"<?= (string)($_GET['class']??'')===(string)$r->class_id?' selected':'' ?>><?= htmlspecialchars($r->class_name) ?></option><?php endforeach; ?></select></div>
                <div class="col-lg-2 col-md-4 form-group"><label>درس</label><select name="dars" class="form-control"><option value="">همه</option><?php $seen=[]; foreach($filterRows as $r): if(isset($seen[$r->dars_id]))continue; $seen[$r->dars_id]=1; ?><option value="<?= (int)$r->dars_id ?>"<?= (string)($_GET['dars']??'')===(string)$r->dars_id?' selected':'' ?>><?= htmlspecialchars($r->dars_name) ?></option><?php endforeach; ?></select></div>
                <div class="col-lg-2 col-md-4 form-group"><label>از تاریخ</label><input class="form-control fc-datepicker" name="from" value="<?= htmlspecialchars($_GET['from']??'') ?>" placeholder="<?= $academicYear['start_jalali'] ?>"></div>
                <div class="col-lg-2 col-md-4 form-group"><label>تا تاریخ</label><input class="form-control fc-datepicker" name="to" value="<?= htmlspecialchars($_GET['to']??'') ?>" placeholder="<?= $academicYear['end_jalali'] ?>"></div>
            </div><button class="btn btn-primary">اعمال فیلتر</button> <a class="btn btn-outline-secondary" href="lesson-plans.php?course=<?= $lockedCourseCode ?>">پاک کردن</a> <a class="btn btn-outline-info" href="Active-course.php">بازگشت به پایه‌ها</a></form></div></div>
            <?php endif; ?>

            <div class="card custom-card"><div class="card-header"><h6 class="main-content-label mb-0">فهرست طرح درس‌ها</h6></div><div class="card-body"><div class="table-responsive">
                <table class="table table-bordered text-center" id="example1"><thead><tr><th>ردیف</th><?php if(!$isTeacher):?><th><?php echo t('teacher') ?></th><?php endif;?><th>تاریخ</th><th>پایه</th><th>کلاس</th><th>درس</th><th>سرفصل</th><th style="min-width:220px">توضیحات</th><?php if($isTeacher):?><th>اقدامات</th><?php endif;?></tr></thead><tbody>
                <?php if($plans): foreach($plans as $i=>$plan): ?><tr><td><?= $i+1 ?></td><?php if(!$isTeacher):?><td><?= htmlspecialchars(trim($plan->teacher_name) ?: $plan->teacher_username) ?></td><?php endif;?><td><?= htmlspecialchars(lessonPlanGregorianToJalali($plan->plan_date)) ?></td><td><?= htmlspecialchars($plan->coursename) ?></td><td><?= htmlspecialchars($plan->class_name) ?></td><td><?= htmlspecialchars($plan->dars_name) ?></td><td><?= htmlspecialchars($plan->lesson_name) ?></td><td class="text-right" style="white-space:normal"><?= $plan->description ? nl2br(htmlspecialchars($plan->description)) : '<span class="text-muted">—</span>' ?></td><?php if($isTeacher):?><td><a class="btn btn-sm btn-warning" href="?edit=<?= (int)$plan->id ?>"><i class="fe fe-edit"></i></a> <form method="post" class="d-inline"><input type="hidden" name="token" value="<?= $token ?>"><input type="hidden" name="plan_id" value="<?= (int)$plan->id ?>"><button class="btn btn-sm btn-danger" name="delete_plan" onclick="return confirm('طرح درس حذف شود؟')"><i class="fe fe-trash"></i></button></form></td><?php endif;?></tr><?php endforeach; else: ?><tr><td colspan="<?= $isTeacher?8:8 ?>">طرح درسی در این بازه ثبت نشده است.</td></tr><?php endif; ?>
                </tbody></table>
            </div></div></div>
        </div></div>
    </div>
    <?php include '../../footers.php'; ?>
</div>
<?php if($isTeacher && $teacherOptions): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = <?= json_encode($teacherOptions, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const classSelect = document.getElementById('plan-class'), darsSelect = document.getElementById('plan-dars'), lessonSelect = document.getElementById('plan-lesson');
    const selected = {classId:'<?= (int)($editingPlan->class_id??0) ?>', darsId:'<?= (int)($editingPlan->dars_id??0) ?>', lessonId:'<?= (int)($editingPlan->lesson_id??0) ?>'};
    function unique(items, key){const out=new Map(); items.forEach(x=>out.set(String(x[key]),x)); return Array.from(out.values());}
    function fill(select, items, valueKey, label, placeholder, wanted){select.innerHTML='<option value="">'+placeholder+'</option>'; items.forEach(item=>{const o=document.createElement('option');o.value=item[valueKey];o.textContent=label(item);if(String(o.value)===String(wanted))o.selected=true;select.appendChild(o);});select.disabled=false;}
    fill(classSelect, unique(rows,'class_id'), 'class_id', x=>x.coursename+' — '+x.class_name, 'انتخاب کنید', selected.classId);
    function loadDars(wanted){const list=rows.filter(x=>String(x.class_id)===classSelect.value);fill(darsSelect,unique(list,'dars_id'),'dars_id',x=>x.dars_name,'انتخاب درس',wanted);loadLessons(selected.lessonId);}
    function loadLessons(wanted){const list=rows.filter(x=>String(x.class_id)===classSelect.value&&String(x.dars_id)===darsSelect.value);fill(lessonSelect,unique(list,'lesson_id'),'lesson_id',x=>x.lesson_name,'انتخاب سرفصل',wanted);}
    classSelect.addEventListener('change',()=>{selected.lessonId='';loadDars('');}); darsSelect.addEventListener('change',()=>loadLessons(''));
    if(classSelect.value) loadDars(selected.darsId); else {darsSelect.disabled=true;lessonSelect.disabled=true;}
});
</script>
<?php endif; ?>
</body></html>
