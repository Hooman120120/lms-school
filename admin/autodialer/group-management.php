<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once 'autodialer_functions.php';

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

if (!permision('autodialer')){
    echo "دسترسی ندارید.";
    die();
}
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$selectedCourses = [];
$numbers = [];
// helper: normalize phones so duplicates across different persons / formats are removed
function normalizePhone(string $n): string
{
    $n = trim($n);

    // convert Persian/Arabic digits to English
    $map = [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ];
    $n = strtr($n, $map);

    // keep only digits and +
    $n = preg_replace('/[^\d\+]/', '', $n) ?? '';

    // 09xxxxxxxxx ->
    if (preg_match('/^09\d{9}$/', $n)) {
        $n = '0' . substr($n, 1);
    }
    // 9xxxxxxxxx ->
    if (preg_match('/^9\d{9}$/', $n)) {
        $n = '0' . $n;
    }
    // 0098... ->
    if (strpos($n, '0098') === 0) {
        $n = '0' . substr($n, 4);
    }

    return $n;
}
$numbers = [];
$finalNumbers = [];
$createGroupResult = null;

$contacts = autodialerContactsSearch(0, 500000); // adjust count if needed
if (!is_array($contacts)) $contacts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_submit'])) {
    $cntContacts = 0;
    $cntCourseRaw = 0;
    $cntBeforeBlacklist = 0;
    $cntAfterBlacklist = 0;
    // selected courses
    $selectedCourses = array_values(array_unique(array_filter(explode(',', (string)($_POST['selectedCourses'] ?? '')))));

    // selected contact IDs
    $selectedContactNumbers = array_values(array_unique(
        array_filter(explode(',', (string)($_POST['selectedContactNumbers'] ?? '')))
    ));

// add contact numbers directly
    foreach ($selectedContactNumbers as $cn) {
        $numbers[] = $cn;
        $cntContacts++;

    }

    // 1) numbers from courses
    if (!empty($selectedCourses)) {
        $placeholders = rtrim(str_repeat('?,', count($selectedCourses)), ',');

        $sqlNums = "
            SELECT DISTINCT p.tel1, p.tel2, p.tel3
            FROM dbcourse c
            INNER JOIN dbpersonal p ON p.codemeli = c.username
            WHERE c.activityuser = 1
              AND c.codecourse IN ($placeholders)
        ";

        $st = $conn->prepare($sqlNums);
        $st->execute($selectedCourses);
        $res = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($res as $r) {
            if (!empty($r['tel1'])) { $numbers[] = $r['tel1']; $cntCourseRaw++; }
            if (!empty($r['tel2'])) { $numbers[] = $r['tel2']; $cntCourseRaw++; }
            if (!empty($r['tel3'])) { $numbers[] = $r['tel3']; $cntCourseRaw++; }
        }
    }


    // 3) normalize + dedupe
    $numbers = array_values(array_unique(array_filter(array_map(function ($n) {
        $n = normalizePhone((string)$n);
        return $n !== '' ? $n : null;
    }, $numbers))));
    $cntBeforeBlacklist = count($numbers);

    // 4) load blacklist + normalize
    $blackRows = autodialerBlacklistList('');
    $blackSet = [];
    foreach ($blackRows as $b) {
        $bn = normalizePhone((string)($b['phone'] ?? ''));
        if ($bn !== '') $blackSet[$bn] = true;
    }

    // 5) remove blacklisted
    $finalNumbers = array_values(array_filter($numbers, function ($n) use ($blackSet) {
        return !isset($blackSet[$n]);
    }));
    $cntAfterBlacklist = count($finalNumbers);

    // 6) create group if we still have numbers
    $groupName = trim((string)($_POST['group_name'] ?? ''));
    $groupDesc = trim((string)($_POST['group_desc'] ?? ''));

    if ($groupName === '' || empty($finalNumbers)) {
        $_SESSION['flash_error'] = 'شماره‌ای برای ساخت گروه باقی نماند (بعد از فیلتر/بلک‌لیست).';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $createGroupResult = autodialerGroupsAddNumbers($groupName, $finalNumbers, $groupDesc);

    $ok = ($createGroupResult['httpcode'] >= 200 && $createGroupResult['httpcode'] < 300);
    if (isset($createGroupResult['json']['success'])) {
        $ok = (bool)$createGroupResult['json']['success'];
    }
    $actor = (string)($_SESSION['admin']['user'] ?? 'admin');

    // Build courses meta like: " codecourse:12324,12341,12231"
    $coursesPart = '';
    if (!empty($selectedCourses)) {
        $coursesPart = ' codecourse:' . implode(',', $selectedCourses);
    }

    // Keep action <= 100 chars
    $actionBase = ($ok ? 'group.add ' : 'group.add_failed ') . $groupName;
    $actionMeta = ' count=' . $cntAfterBlacklist . $coursesPart;

    $action = trim($actionBase . $actionMeta);

    // If too long, shorten group name first
    if (mb_strlen($action) > 100) {
        $shortName = mb_substr($groupName, 0, 20);
        $action = trim(($ok ? 'group.add ' : 'group.add_failed ') . $shortName . $actionMeta);
    }

    // If still too long, trim the courses list to fit (keep "codecourse:" prefix)
    if (mb_strlen($action) > 100 && $coursesPart !== '') {
        $baseNoCourses = trim($actionBase . ' count=' . $cntAfterBlacklist . ' codecourse:');
        $maxCoursesLen = 100 - mb_strlen($baseNoCourses);

        if ($maxCoursesLen > 0) {
            $courseCsv = implode(',', $selectedCourses);
            $courseCsvTrim = mb_substr($courseCsv, 0, $maxCoursesLen);
            $action = $baseNoCourses . $courseCsvTrim;
        } else {
            // worst case: fall back to cutting hard
            $action = mb_substr($action, 0, 100);
        }
    }

    // Final hard cap (safety)
    if (mb_strlen($action) > 100) {
        $action = mb_substr($action, 0, 100);
    }

    autodialerLog($actor, $action, 'group');

    $_SESSION['flash_success'] = $ok
        ? ('گروه با موفقیت ساخته شد. تعداد شماره‌ها: ' . count($finalNumbers))
        : ('خطا در ساخت گروه. تعداد شماره‌ها: ' . count($finalNumbers));

    if (!$ok) {
        // optional: attach raw error text (short)
        $_SESSION['flash_error'] = isset($createGroupResult['raw']) ? mb_substr((string)$createGroupResult['raw'], 0, 500) : '';
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_delete_submit'])) {
    $gid = trim((string)($_POST['group_id'] ?? ''));
    $gname = trim((string)($_POST['group_name'] ?? ''));
    if ($gid === '') {
        $_SESSION['flash_error'] = 'شناسه گروه معتبر نیست.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $res = autodialerGroupsRemove($gid);

    $ok = ($res['httpcode'] >= 200 && $res['httpcode'] < 300);
    if (isset($res['json']['success'])) {
        $ok = (bool)$res['json']['success'];
    }

    // log
    $actor = (string)($_SESSION['admin']['user'] ?? 'admin');
    $action = ($ok ? 'group.remove ' : 'group.remove_failed ') . $gname;
    autodialerLog($actor, mb_substr($action, 0, 100), 'group');

    $_SESSION['flash_success'] = $ok ? 'گروه با موفقیت حذف شد.' : 'خطا در حذف گروه.';
    if (!$ok) {
        $_SESSION['flash_error'] = isset($res['raw']) ? mb_substr((string)$res['raw'], 0, 500) : '';
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$groups = autodialerGroupsSearch(0, 500000);

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
    <title> groups |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">

<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <div class="main-content side-content pt-0">
        <?php if ($flash_success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="container-fluid">

                    <div class="col-12">
                        <div class="card col-12">
                            <div class="card-header d-flex col-12 flex-wrap flex-row">
                                <div class="card-title mb-0 fs-5 font-weight-bold pb-4 col-6">لیست گروه‌ها</div>
                                <div class="mb-3 d-flex gap-2 col-6 flex-row-reverse">
                                    <button type="button" class="btn btn-primary"
                                            data-bs-toggle="modal" data-bs-target="#modalBlacklist">
                                        ساخت گروه تماس اتودایلر
                                    </button>
                                </div>
                                <p class="col-12">قبل از ساخته شدن گروه جدید شماره های لیست سیاه حذف میشود.</p>
                                <p class="col-12">شماره های تکراری به صورت خودکار حذف میشود.</p>
                                <p class="col-12">شماره های تست یا contact را فقط از اینجا اضافه کنید.</p>
                            </div>


                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th style="width:60px;">#</th>
                                            <th>نام گروه</th>
                                            <th>توضیحات</th>
                                            <th>تعداد شماره های گروه</th>
                                            <th>عملیات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($groups)):  ?>
                                            <?php $i=1; foreach ($groups as $g):  ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td><?php echo htmlspecialchars((string)($g->name ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($g->description ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($g->count ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php
                                                        $gid = htmlspecialchars((string)($g->_id ?? ($g->id ?? '')), ENT_QUOTES, 'UTF-8');
                                                        $gname = htmlspecialchars((string)($g->name ?? ''), ENT_QUOTES, 'UTF-8');
                                                        ?>
                                                        <button type="button"
                                                                class="btn btn-danger btn-sm"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteGroupModal"
                                                                data-id="<?php echo $gid; ?>"
                                                                data-name="<?php echo $gname; ?>">
                                                            حذف
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">موردی یافت نشد</td>
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

    <?php include '../../footers.php'; ?>
</div>

<!-- Modal -->
 <div class="modal fade" id="modalBlacklist" tabindex="-1" aria-labelledby="modalBlacklistLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="">
            <input type="hidden" name="selectedCourses" id="selectedCourses" value="">
            <input type="hidden" name="selectedContactNumbers" id="selectedContactNumbers" value="">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalBlacklistLabel">انتخاب مخاطبین و دوره‌ها</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <div class="accordion" id="selectAccordion">

                        <!-- Contacts -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingContacts">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseContacts"
                                        aria-expanded="false" aria-controls="collapseContacts">
                                    انتخاب مخاطبین <span class="ms-2 text-muted small" id="contactsCount">(0)</span>
                                </button>
                            </h2>
                            <div id="collapseContacts" class="accordion-collapse collapse"
                                 aria-labelledby="headingContacts" data-bs-parent="#selectAccordion">
                                <div class="accordion-body">

                                    <div class="mb-3">
                                        <input type="text" id="contactSearch" class="form-control form-control-sm" placeholder="جستجوی مخاطب...">
                                    </div>

                                    <div id="contactList" class="d-flex flex-wrap gap-2">
                                        <?php foreach ($contacts as $c):
                                            $rawId = (string)($c->_id ?? ($c->id ?? ''));
                                            $cid   = htmlspecialchars($rawId, ENT_QUOTES, 'UTF-8');

                                            $cname = htmlspecialchars((string)($c->name ?? ''), ENT_QUOTES, 'UTF-8');
                                            $cnum  = htmlspecialchars((string)($c->number ?? ''), ENT_QUOTES, 'UTF-8');
                                            $label = trim($cname) !== '' ? ($cname . ' - ' . $cnum) : $cnum;
                                            ?>
                                            <div class="contact-pill btn btn-outline-success btn-sm"
                                                 data-number="<?php echo $cnum; ?>">
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endforeach; ?>

                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Courses -->
                        <div class="accordion-item mt-2">
                            <h2 class="accordion-header" id="headingCourses">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseCourses"
                                        aria-expanded="false" aria-controls="collapseCourses">
                                    انتخاب دوره‌ها <span class="ms-2 text-muted small" id="coursesCount">(0)</span>
                                </button>
                            </h2>
                            <div id="collapseCourses" class="accordion-collapse collapse"
                                 aria-labelledby="headingCourses" data-bs-parent="#selectAccordion">
                                <div class="accordion-body">
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary course-filter-btn" data-keys="">
                                            همه
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary course-filter-btn" data-keys="هوش">
                                            هوش
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary course-filter-btn" data-keys="قهرمان">
                                            قهرمان
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary course-filter-btn"
                                                data-keys="ابتدایی,اول,دوم,سوم,چهارم,پنجم,ششم,شش,پنج,چهار,سه,دو">
                                            ابتدایی
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary course-filter-btn"
                                                data-keys="متوسطه اول,هفتم,هشتم,نهم,نه,هشت,هفت">
                                            متوسطه اول
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary course-filter-btn"
                                                data-keys="متوسطه دوم,دهم,یازدهم,دوازدهم,ده,یازده,دوازده">
                                            متوسطه دوم
                                        </button>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" id="courseSearch" class="form-control form-control-sm" placeholder="جستجو...">
                                    </div>

                                    <div id="courseList" class="d-flex flex-wrap gap-2">
                                        <?php
                                        $stmt = $conn->query("SELECT codecourse, coursename FROM codtbl_course WHERE activecourse = 1 ORDER BY coursename ASC");
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $val = htmlspecialchars($row['codecourse'], ENT_QUOTES, 'UTF-8');
                                            $txt = htmlspecialchars($row['coursename'], ENT_QUOTES, 'UTF-8');
                                            echo "<div class='course-pill btn btn-outline-primary btn-sm' data-value='{$val}'>{$txt}</div>";
                                        }
                                        ?>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">نام گروه</label>
                                <input type="text" name="group_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">توضیحات</label>
                                <input type="text" name="group_desc" class="form-control">
                            </div>
                        </div>


                    </div><!-- /accordion -->

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    <button type="submit" name="group_submit" value="1" class="btn btn-primary">ساخت گروه</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="">
            <input type="hidden" name="group_name" id="deleteGroupName" value="">
            <input type="hidden" name="group_id" id="deleteGroupId" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">حذف گروه</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    آیا از حذف این گروه مطمئن هستید؟
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="group_delete_submit" value="1" class="btn btn-danger">حذف</button>
                </div>
            </div>
        </form>
    </div>
</div>

    <script>
    (function () {
        function updateCounts() {
            const c = document.querySelectorAll('.contact-pill.active').length;
            const k = document.querySelectorAll('.course-pill.active').length;
            const elC = document.getElementById('contactsCount');
            const elK = document.getElementById('coursesCount');
            if (elC) elC.textContent = '(' + c + ')';
            if (elK) elK.textContent = '(' + k + ')';
        }

        // Toggle contacts
        document.addEventListener('click', function (e) {
            const pill = e.target.closest('.contact-pill');
            if (!pill) return;
            pill.classList.toggle('active');
            pill.classList.toggle('btn-success');
            pill.classList.toggle('btn-outline-success');
            updateCounts();
        });

        // Toggle courses
        document.addEventListener('click', function (e) {
            const pill = e.target.closest('.course-pill');
            if (!pill) return;
            pill.classList.toggle('active');
            pill.classList.toggle('btn-primary');
            pill.classList.toggle('btn-outline-primary');
            updateCounts();
        });

        // Search contacts
        const contactSearch = document.getElementById('contactSearch');
        if (contactSearch) {
            contactSearch.addEventListener('input', () => {
                const term = contactSearch.value.trim().toLowerCase();
                document.querySelectorAll('.contact-pill').forEach(p => {
                    p.style.display = p.textContent.trim().toLowerCase().includes(term) ? '' : 'none';
                });
            });
        }

        // Search courses
        const courseSearch = document.getElementById('courseSearch');
        if (courseSearch) {
            courseSearch.addEventListener('input', () => {
                const term = courseSearch.value.trim().toLowerCase();
                document.querySelectorAll('.course-pill').forEach(p => {
                    p.style.display = p.textContent.trim().toLowerCase().includes(term) ? '' : 'none';
                });
            });
        }

        // On submit: write hidden values
        const form = document.querySelector('#modalBlacklist form');
        if (form) {
            form.addEventListener('submit', function () {
                const courseIds = Array.from(document.querySelectorAll('.course-pill.active')).map(p => p.dataset.value);
                const contactNumbers = Array.from(document.querySelectorAll('.contact-pill.active'))
                    .map(p => (p.dataset.number || '').trim())
                    .filter(n => n !== '');
                document.getElementById('selectedCourses').value = courseIds.join(',');
                document.getElementById('selectedContactNumbers').value = contactNumbers.join(',');
            });
        }

        updateCounts();
    })();
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-bs-target="#deleteGroupModal"]');
        if (!btn) return;

        const gid = btn.getAttribute('data-id') || '';
        const gname = btn.getAttribute('data-name') || '';

        const idInput = document.getElementById('deleteGroupId');
        const nameInput = document.getElementById('deleteGroupName');

        if (idInput) idInput.value = gid;
        if (nameInput) nameInput.value = gname;
    });


        (function () {
        const searchInput = document.getElementById('courseSearch');
        const pills = Array.from(document.querySelectorAll('#courseList .course-pill'));
        const btns = Array.from(document.querySelectorAll('.course-filter-btn'));
        const hint = document.getElementById('courseHint');

        if (!pills.length) return;

        let activeKeys = [];
        let userActivated = false; // only show after user action

        function norm(s) { return (s || '').toString().trim().toLowerCase(); }
        function parseKeys(csv) { return (csv || '').split(',').map(norm).filter(k => k.length > 0); }
        function matchesAnyKey(text, keys) {
        if (!keys || keys.length === 0) return true;
        return keys.some(k => text.includes(k));
    }

        function hideAll() {
        pills.forEach(p => p.style.display = 'none');
        if (hint) hint.style.display = '';
    }

        function applyFilters() {
        const q = searchInput ? norm(searchInput.value) : '';

        if (!userActivated) {
        hideAll();
        return;
    }

        pills.forEach(pill => {
        const txt = norm(pill.textContent);
        const okBtn = matchesAnyKey(txt, activeKeys);
        const okTxt = (q === '') ? true : txt.includes(q);
        pill.style.display = (okBtn && okTxt) ? '' : 'none';
    });

        if (hint) hint.style.display = 'none';
    }

        // typing activates
        if (searchInput) {
        searchInput.addEventListener('input', () => {
        userActivated = true;
        // when typing, you can optionally clear active button style:
        // btns.forEach(b => b.classList.remove('active'));
        applyFilters();
    });
    }

        // button click activates
        btns.forEach(btn => {
        btn.addEventListener('click', () => {
        userActivated = true;
        btns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeKeys = parseKeys(btn.getAttribute('data-keys') || '');
        applyFilters();
    });
    });

        // initial: hide everything
        hideAll();
    })();

</script>
</body>
</html>
