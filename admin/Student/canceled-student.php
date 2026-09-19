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
if (!Permision('permision_editstudent')) {
    header('location: ' . url('admin/index.php'));
    die();
}
// --- Filter Logic ---
$selectedCourses = [];
$filterClause = '';
$rows = []; // Default: empty results

// --- Handle POST (filter submitted) ---
if (isset($_POST['selectedCourses'])) {
    $selectedCourses = array_filter(explode(',', $_POST['selectedCourses']));
    $paramString = implode(',', $selectedCourses);

    // Redirect to GET (to prevent resubmission)
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?selectedCourses=" . urlencode($paramString));
    exit;
}

if (isset($_GET['selectedCourses']) && !empty($_GET['selectedCourses'])) {
    $selectedCourses = array_filter(explode(',', $_GET['selectedCourses']));
    if (!empty($selectedCourses)) {
        $placeholders = rtrim(str_repeat('?,', count($selectedCourses)), ',');
        $filterClause = " AND course_tbl.codecourse IN ($placeholders)";
    }

    $sql = "
        SELECT 
            p.codemeli,
            p.fname,
            p.lname,
            p.state,
            p.city,
            GROUP_CONCAT(DISTINCT course_tbl.coursename SEPARATOR ' -- ') AS courses
        FROM dbpersonal p
        INNER JOIN dbcourse c ON p.codemeli = c.username
        INNER JOIN codtbl_course AS course_tbl ON course_tbl.codecourse = c.codecourse
        WHERE 1=1
        $filterClause
        GROUP BY p.codemeli, p.fname, p.lname, p.state, p.city
        HAVING COUNT(*) > 0 
        AND SUM(CASE WHEN c.activityuser = 0 THEN 1 ELSE 0 END) = COUNT(*)
    ";

    $query = $conn->prepare($sql);
    $query->execute($selectedCourses);
    $rows = $query->fetchAll(PDO::FETCH_OBJ);
}

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
    <!-- Title -->
    <title> دانش آموزان انصرافی |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .course-pill.active { box-shadow: 0 0 0 .15rem rgba(13,110,253,.25); }
    </style>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';

    ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="col-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="col-10">
                                <div>
                                    <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                        <h6 class="main-content-label mb-0">
                                            لیست دانش آموزان انصرافی
                                        </h6>

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش می توانید لیست دانش آموزانی که از یک یا چند پایه انصراف داده و درخواست انصراف آن ها توسط کارشناسان پشتیبانی ثبت و در نهایت تائید شده است را مشاهده نمایید و همچنین اطلاعات پایه، وضعیت انصراف و مشخصات دانش آموزان در این بخش قابل بررسی می باشد."
                                              aria-label="راهنمای لیست دانش‌آموزان انصرافی">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                    </div>

                                    <p class="text-muted card-sub-title">
                                        مشاهده دانش آموزان انصراف‌داده از پایه‌ها
                                    </p>
                                </div>
                            </div>
                            <!-- ====== Course Filter Dropdown ====== -->
                            <!-- ========== COURSE FILTER DROPDOWN ========== -->
                            <div class="mb-3 position-relative" id="courseFilterWrapper" dir="rtl">
                                <label for="courseFilterDropdown" class="form-label fw-semibold">فیلتر بر اساس پایه‌ها</label>

                                <div class="dropdown w-100">
                                    <button class="btn btn-outline-info w-100 d-flex justify-content-between align-items-center"
                                            type="button" id="courseFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        انتخاب پایه‌ها
                                        <span class="ms-2 small text-muted">▼</span>
                                    </button>

                                    <div class="dropdown-menu p-3 shadow w-100" style="max-height:400px; overflow:auto;"
                                         aria-labelledby="courseFilterDropdown">

                                        <!-- Toolbar Row -->
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                            <!-- Search Bar -->
                                            <input type="text" id="courseSearch" class="form-control form-control-sm flex-grow-1" placeholder="جستجو...">

                                            <!-- Select All -->
                                            <button type="button" id="selectAllCourses" class="btn btn-outline-info btn-sm flex-shrink-0">
                                                انتخاب همه
                                            </button>

                                            <!-- Apply -->
                                            <button type="button" class="btn btn-primary btn-sm flex-shrink-0" id="applyFilter">
                                                جستجو
                                            </button>

                                            <!-- Close -->
                                            <button type="button"
                                                    class="btn btn-outline-info btn-sm flex-shrink-0"
                                                    id="closeDropdownBtn">
                                                بستن
                                            </button>
                                        </div>

                                        <!-- Course List -->
                                        <div id="courseList" class="d-flex flex-wrap gap-2">
                                            <?php
                                            $stmt = $conn->query("SELECT codecourse, coursename FROM codtbl_course ORDER BY coursename ASC");
                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $val = htmlspecialchars($row['codecourse'], ENT_QUOTES, 'UTF-8');
                                                $txt = htmlspecialchars($row['coursename'], ENT_QUOTES, 'UTF-8');
                                                echo "<div class='course-pill btn btn-outline-primary btn-sm' data-value='{$val}'>{$txt}</div>";
                                            }
                                            ?>
                                        </div>
                                    </div>                                    </div>
                            </div>

                            <input type="hidden" id="selectedCourses" name="selectedCourses">
                            <div class="table">
                                <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0" id="example1">
                                    <thead>
                                    <tr>
                                        <th>کدملی</th>
                                        <th>نام</th>
                                        <th>استان</th>
                                        <th>شهر</th>
                                        <th class="text-wrap text-break" style="max-width: 200px;">پایه</th>
                                        <th>اقدام</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    if (!empty($rows)) {
                                        foreach ($rows as $r) {
                                            echo "<tr>";
                                            echo "<td>{$r->codemeli}</td>";
                                            echo "<td>{$r->fname} {$r->lname}</td>";
                                            echo "<td>{$r->state}</td>";
                                            echo "<td>{$r->city}</td>";
                                            echo "<td class='text-wrap text-break' style='max-width: 200px;'>{$r->courses}</td>";
                                            echo "<td>
                      <div class='btn btn-sm ripple btn-info btn-icon'
                           onclick=\"window.open(
                               '".url('admin/panel/suporting.php')."?Id=".urlencode($r->codemeli)."',
                               'پشتیبانی',
                               'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                           )\">
                        <i class='fa fa-key'></i>
                      </div>
                    </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center text-danger'>هیچ دانش آموزی با وضعیت انصرافی یافت نشد.</td></tr>";
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('courseSearch');
            const selectAllBtn = document.getElementById('selectAllCourses');
            const courseList = document.getElementById('courseList');
            const applyBtn = document.getElementById('applyFilter');
            const dropdownBtn = document.getElementById('courseFilterDropdown');
            const dropdownMenu = document.querySelector('.dropdown-menu');
            const hiddenInput = document.getElementById('selectedCourses');
            const pills = Array.from(document.querySelectorAll('.course-pill'));
            const bsDropdown = new bootstrap.Dropdown(dropdownBtn, { autoClose: false });
            const urlParams = new URLSearchParams(window.location.search);
            const selectedCoursesFromURL = urlParams.get('selectedCourses');
            if (selectedCoursesFromURL) {
                const selectedIds = selectedCoursesFromURL.split(',');
                selectedIds.forEach(id => {
                    const pill = document.querySelector(`.course-pill[data-value="${id}"]`);
                    if (pill) {
                        pill.classList.add('active', 'btn-primary');
                        pill.classList.remove('btn-outline-primary');
                    }
                });
            }
            // Toggle pill selection
            courseList.addEventListener('click', (e) => {
                const pill = e.target.closest('.course-pill');
                if (!pill) return;
                pill.classList.toggle('active');
                pill.classList.toggle('btn-primary');
                pill.classList.toggle('btn-outline-primary');
            });

            // Select / Deselect all
            selectAllBtn.addEventListener('click', () => {
                const allSelected = pills.every(p => p.classList.contains('active'));
                pills.forEach(p => {
                    p.classList.toggle('active', !allSelected);
                    p.classList.toggle('btn-primary', !allSelected);
                    p.classList.toggle('btn-outline-primary', allSelected);
                });
                selectAllBtn.textContent = allSelected ? 'انتخاب همه' : 'لغو انتخاب همه';
            });

            // Search filter
            searchInput.addEventListener('input', () => {
                const term = searchInput.value.trim().toLowerCase();
                pills.forEach(p => {
                    const match = p.textContent.trim().toLowerCase().includes(term);
                    p.style.display = match ? '' : 'none';
                });
            });

            // Prevent closing when clicking inside
            dropdownMenu.addEventListener('click', (e) => e.stopPropagation());

            // Apply button → POST submit to PHP
            applyBtn.addEventListener('click', () => {
                const selectedValues = pills
                    .filter(p => p.classList.contains('active'))
                    .map(p => p.dataset.value);

                hiddenInput.value = selectedValues.join(',');

                // Build a POST form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selectedCourses';
                input.value = hiddenInput.value;
                form.appendChild(input);

                document.body.appendChild(form);
                form.submit();
            });

            // Close only when clicking outside
            document.addEventListener('click', (e) => {
                const inside = e.target.closest('#courseFilterWrapper');
                if (!inside && dropdownMenu.classList.contains('show')) {
                    bsDropdown.hide();
                }
            });
        });
        document.getElementById('closeDropdownBtn').addEventListener('click', () => {
            const dropdownInstance = bootstrap.Dropdown.getInstance(document.getElementById('courseFilterDropdown'));
            if (dropdownInstance) dropdownInstance.hide();
        });

    </script>



</body>
</html>
