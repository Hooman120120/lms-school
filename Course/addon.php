<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    die();
}
if (!isset($_GET['code']) and $_GET['code'] = '') {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
// if (isset($_POST['chapter'])) {
//     dd($_POST);
// }
if (!isset($_GET['demo'])) {
    $course1 = returnCourse($_GET['code']);
} else {
    $sql = "SELECT * FROM course_demo WHERE codecourse = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['code']]);
    $course1 = $stmt->fetch(PDO::FETCH_OBJ);
}
include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> جزوات بارگزاری شده <?php echo m('label.for_course_colon') ?> <?php echo $course1->coursename ?> | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
    <style>
        /* برای select2 نسخه‌های معمولی */
        .select2-container .select2-selection--single {
            min-width: 200px;
            /* حداقل عرض */
            width: auto !important;
            /* اجازه بده اگر محتوا بیشتر بود بزرگ‌تر شود */
            max-width: 100%;
        }

        /* اگر از theme خاصی استفاده می‌کنی (مثلاً default) */
        .select2-container--default .select2-selection--single {
            min-width: 200px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            text-align: center;
        }
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
                    <div class="row row-sm">
                        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                            <div class="card custom-card">
                                <div class="card-header  border-bottom-0 pb-0 d-flex flex-row justify-content-between">
                                    <div>
                                        <div class="d-flex">
                                            <label class="main-content-label my-auto pt-2">جزوات و تکالیف ارائه شده
                                            </label>
                                        </div>
                                        <span class="d-block tx-12 mt-2 mb-0 text-muted"> <?php echo t('grade') ?> <?php echo $course1->coursename ?> </span>
                                    </div>
                                    <?php if (!isset($_GET['demo'])) { ?>
                                        <div class="mt-2 d-flex justify-content-center ">
                                            <?php
                                            $getClass = "SELECT class_id FROM dbcourse WHERE username = ? AND codecourse = ?";
                                            $stmt = $conn->prepare($getClass);
                                            $stmt->execute([$_SESSION['auth']['user'], $_GET['code']]);
                                            $classId = $stmt->fetchColumn();
                                            if ($_GET['dars'] == 'all') {
                                                $sql = "SELECT * FROM dblesson INNER JOIN class_lesson ON dblesson.id = class_lesson.lesson_id WHERE dblesson.codecourse = ? AND class_lesson.class_id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code'], $classId]);
                                            } else {
                                                $sql = 'SELECT * FROM dblesson INNER JOIN class_lesson ON dblesson.id = class_lesson.lesson_id WHERE dblesson.codecourse = ? AND dblesson.dars_id = ? AND class_lesson.class_id = ?';
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code'], $_GET['dars'], $classId]);
                                            }
                                            $lessons = $stmt->fetchAll(PDO::FETCH_OBJ);
                                            ?>
                                            <form action="" method="POST"
                                                class="d-flex align-items-center mx-auto flex-column flex-md-row">

                                                <label for="lesson" class="mb-0 ml-3 font-weight-bold text-nowrap">
                                                    انتخاب سرفصل
                                                </label>
                                                <select id="lesson" name="lesson" class="form-control select2">
                                                    <option value="all" <?= (isset($_POST['lesson']) and $_POST['lesson'] == 'all') ? 'selected' : '' ?>>
                                                        --- تمام سرفصل ها ---
                                                    </option>
                                                    <?php foreach ($lessons as $lesson) { ?>
                                                        <option value="<?= $lesson->lesson_id ?>" <?= (isset($_POST['lesson']) and $_POST['lesson'] == $lesson->lesson_id) ? 'selected' : '' ?>>
                                                            <?= $lesson->name ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>

                                                <label for="chapter" class="mb-0 ml-3 font-weight-bold text-nowrap mr-3">
                                                    انتخاب زیرفصل
                                                </label>

                                                <select id="chapter" name="chapter" class="form-control select2"
                                                    onchange="this.form.submit()">
                                                    <option value="all">--- تمام زیرفصل ها ---</option>
                                                    <?php
                                                    $thisLesson = $_POST['lesson'] ?? null;

                                                    if ($thisLesson === null || $thisLesson === 'all') {
                                                        // ✅ 1. گرفتن تمام درس‌ها مربوط به این کلاس
                                                        foreach ($lessons as $lesson) {
                                                            // ✅ 2. گرفتن زیرفصل‌های مربوط به هر درس
                                                            $sql = 'SELECT * FROM subchapters WHERE lesson_id = ?';
                                                            $stmt = $conn->prepare($sql);
                                                            $stmt->execute([$lesson->lesson_id]);
                                                            $chapters = $stmt->fetchAll(PDO::FETCH_OBJ);

                                                            // ✅ 3. نمایش زیرفصل‌ها به‌صورت option
                                                            foreach ($chapters as $chapter) {
                                                                $selected = (isset($_POST['chapter']) && $_POST['chapter'] == $chapter->id) ? 'selected' : '';
                                                                echo "<option value='$chapter->id' $selected>$chapter->name</option>";
                                                            }
                                                        }
                                                    } else {
                                                        // ✅ فقط یک درس انتخاب شده → زیرفصل‌های همان درس را نمایش بده
                                                        $sql = 'SELECT * FROM subchapters WHERE lesson_id = ?';
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$thisLesson]);
                                                        $chapters = $stmt->fetchAll(PDO::FETCH_OBJ);

                                                        foreach ($chapters as $chapter) {
                                                            $selected = (isset($_POST['chapter']) && $_POST['chapter'] == $chapter->id) ? 'selected' : '';
                                                            echo "<option value='$chapter->id' $selected>$chapter->name</option>";
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                            </form>
                                        </div>
                                    <?php } ?>
                                    <div class="mt-n3 mt-md-0"> <?php $code = $_GET['code']; ?>
                                        <a href=<?= isset($_GET['demo']) ? "mycourse.php?code=$code&demo" : "select_lesson.php?code=$code&type=addon" ?>
                                            class="btn btn-warning float-left">بازگشت</a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php
                                    if (!isset($_GET['demo'])) {
                                        $getClass = "SELECT class_id FROM dbcourse WHERE username = ? AND codecourse = ?";
                                        $stmt = $conn->prepare($getClass);
                                        $stmt->execute([$_SESSION['auth']['user'], $_GET['code']]);
                                        $classId = $stmt->fetchColumn();
                                        if ($_GET['dars'] == 'all') {
                                            if (isset($_POST['lesson']) and $_POST['lesson'] != 'all') {
                                                if (isset($_POST['chapter']) and !empty($_POST['chapter']) and $_POST['chapter'] != 'all') {
                                                    $sql = "SELECT * FROM dbaddon INNER JOIN addon_class ON dbaddon.id = addon_class.addon_id INNER JOIN subchapter_addon ON dbaddon.id = subchapter_addon.addon_id WHERE dbaddon.codecourse = ? AND dbaddon.lesson_id = ? AND addon_class.class_id = ? AND subchapter_addon.chapter_id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_GET['code'], $_POST['lesson'], $classId, $_POST['chapter']]);
                                                    $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                } else {
                                                    $sql = "SELECT * FROM dbaddon INNER JOIN addon_class ON dbaddon.id = addon_class.addon_id WHERE dbaddon.codecourse = ? AND dbaddon.lesson_id = ? AND addon_class.class_id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_GET['code'], $_POST['lesson'], $classId]);
                                                    $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                }
                                            } else {
                                                if (isset($_POST['chapter']) and !empty($_POST['chapter']) and $_POST['chapter'] != 'all') {
                                                    $sql = 'SELECT * FROM dbaddon INNER JOIN addon_class ON dbaddon.id = addon_class.addon_id INNER JOIN subchapter_addon ON dbaddon.id = subchapter_addon.addon_id WHERE dbaddon.codecourse = ? AND addon_class.class_id = ? AND subchapter_addon.chapter_id = ?';
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_GET['code'], $classId, $_POST['chapter']]);
                                                    $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                } else {
                                                    $addon = retAddonForThisClass($_GET['code'], $classId);
                                                }
                                            }
                                        } else {
                                            if (isset($_POST['lesson']) and $_POST['lesson'] != 'all') {
                                                if (isset($_POST['chapter']) and !empty($_POST['chapter']) and $_POST['chapter'] != 'all') {
                                                    $sql = 'SELECT * FROM dbaddon INNER JOIN addon_class ON dbaddon.id = addon_class.addon_id INNER JOIN subchapter_addon ON dbaddon.id = subchapter_addon.addon_id WHERE dbaddon.codecourse = ? AND dbaddon.dars_id = ? AND dbaddon.lesson_id = ? AND addon_class.class_id = ? AND subchapter_addon.chapter_id = ?';
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_GET['code'], $_GET['dars'], $_POST['lesson'], $classId, $_POST['chapter']]);
                                                    $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                } else {
                                                    $sql = "SELECT * FROM dbaddon INNER JOIN addon_class ON dbaddon.id = addon_class.addon_id WHERE dbaddon.codecourse = ? AND dbaddon.dars_id = ? AND dbaddon.lesson_id = ? AND addon_class.class_id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_GET['code'], $_GET['dars'], $_POST['lesson'], $classId]);
                                                    $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                }
                                            } else {
                                                if (isset($_POST['chapter']) and !empty($_POST['chapter'] and $_POST['chapter'] != 'all')) {
                                                    $sql = "SELECT * FROM dbaddon INNER JOIN addon_class ON dbaddon.id = addon_class.addon_id INNER JOIN subchapter_addon ON dbaddon.id = subchapter_addon.addon_id WHERE dbaddon.codecourse = ? AND dbaddon.dars_id = ? AND addon_class.class_id = ? AND subchapter_addon.chapter_id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_GET['code'], $_GET['dars'], $classId, $_POST['chapter']]);
                                                    $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                } else {
                                                    $sql = "SELECT * FROM dbaddon INNER JOIN addon_class ON dbaddon.id = addon_class.addon_id WHERE dbaddon.codecourse = ? AND dbaddon.dars_id = ? AND addon_class.class_id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_GET['code'], $_GET['dars'], $classId]);
                                                    $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                }
                                            }
                                        }
                                    } else {
                                        $sql = "SELECT * FROM dbaddon_demo WHERE codecourse = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute([$_GET["code"]]);
                                        $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                    }
                                    ?>
                                    <div class="card-body">
                                        <div class="product-timeline card-body pt-3 mt-1">
                                            <ul class="timeline-1 mb-0">
                                                <?php
                                                if ($addon) {
                                                    foreach ($addon as $ert) {
                                                ?>
                                                        <li class="mt-0"><i class="fe fe-file product-icon"></i> <span
                                                                class="font-weight-semibold mb-4 tx-14 "><?php echo $ert->docname ?>
                                                            </span>
                                                            <a href="attachment.php?id=<?php echo isset($_GET['demo']) ? $ert->id . '&demo' : $ert->addon_id ?>"
                                                                target="_blank"
                                                                class="btn btn-sm btn-success  float-left  ">دانلود</a>
                                                            <p class="mb-0 text-muted tx-12"><?php echo $ert->host ?></p>

                                                        </li>

                                                <?php }
                                                } ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../footers.php'; ?>
    <!--End Footer-->
    <script>
        $('#lesson').on('change', function() {
            const lessonEl = document.getElementById('lesson');
            const chapterEl = document.getElementById('chapter');

            // اگر انتخاب "تمام سرفصلها" بود، فرم رو سابمیت کن و دیگه ادامه نده

            // قبل از پر کردن، گزینه‌های قبلی رو پاک کن
            chapterEl.innerHTML = '';

            // (اختیاری) یک placeholder اضافه کن تا کاربر بدونه در حال لود هست
            const loadingOpt = document.createElement('option');
            loadingOpt.textContent = 'در حال بارگذاری...';
            loadingOpt.value = '';
            chapterEl.appendChild(loadingOpt);

            const dars = '<?= $_GET['dars'] ?>';
            const code = '<?= $_GET['code'] ?>';

            fetch('get_chapters.php?lesson=' + encodeURIComponent(lessonEl.value) + '&dars=' + encodeURIComponent(dars) + '&code=' + encodeURIComponent(code))
                .then(response => {
                    if (!response.ok) {
                        // برای دیباگ: متن خطا رو لاگ کن
                        return response.text().then(t => {
                            throw new Error('Network response was not ok: ' + t);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    // پاک کردن loading
                    chapterEl.innerHTML = '';

                    if (!Array.isArray(data) || data.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = 'موردی یافت نشد';
                        chapterEl.appendChild(opt);
                        return;
                    }
                    const disOpt = document.createElement('option');
                    disOpt.value = '';
                    disOpt.textContent = 'یکی را انتخاب کنید';
                    chapterEl.appendChild(disOpt);
                    // اول گزینه "تمام زیرفصلها" (یک المان جدید)
                    const allOpt = document.createElement('option');
                    allOpt.value = 'all';
                    allOpt.textContent = '--- تمام زیرفصل ها ---';
                    chapterEl.appendChild(allOpt);

                    // سپس برای هر آیتم یک گزینه جدید بساز و اضافه کن
                    data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.subId;
                        opt.textContent = item.subName;
                        chapterEl.appendChild(opt);
                    });
                })
                .catch(err => {
                    console.error('Fetch error یا JSON خطا:', err);
                    chapterEl.innerHTML = '';
                    const errOpt = document.createElement('option');
                    errOpt.value = '';
                    errOpt.textContent = 'خطا در بارگذاری';
                    chapterEl.appendChild(errOpt);
                });

        });
    </script>

</body>

</html>