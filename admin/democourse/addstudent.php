<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

include_once '../../inc/bulk_upload.php';
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
//* End Check Session JUST ONE Session
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_SESSION['courses'])) {
    $idf = $_SESSION['courses'];
    unset($_SESSION['courses']);
}
if (isset($_GET['change'])) {
    unset($_SESSION['add-course2']);
    header('location: ' . url('admin/democourse/addstudent.php'));
    die();
}
if (isset($_POST['search-tel'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['tel'] != '') {
            $tel = trim($_POST['tel']);
            // if (check_national_code($codemeli)) {
            if (checkCodemeli($tel)) {
                if (typeUser($tel) == 1) {

                    $_SESSION['add-course2'] = array(
                        "tel" => $_POST['tel']
                    );
                } else {
                    $erorstudent = 1;
                }
            } else {
                $notmelli = 1;
            }

            // } else {
            //     $erormeli = 1;
            // }

        }
    } else {
        $erortoken = 1;
    }
}
if (isset($_POST['sales-course'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        if (isset($_POST['course']) and count($_POST['course']) > 0) {
            foreach ($_POST['course'] as $code) {
                $inc = 'INSERT INTO dbcourse_demo (username,codecourse,useradder) VALUES (?,?,?)';
                $inp = $conn->prepare($inc);
                $inp->execute([$_SESSION['add-course2']['tel'], $code, $_SESSION['admin']['user']]);
            }
            $_SESSION['courses'] = implode(',', $_POST['course']);
            unset($_SESSION['add-course2']);

            header('location: addstudent.php');
        }
    }
}

$bulk_records = 0;
$bulk_inserted = 0;
$bulk_skipped  = 0;
$bulk_errors   = [];

if (!empty($_SESSION['bulk_course_report'])) {
    $bulk_records = $_SESSION['bulk_course_report']['records'] ?? 0;
    $bulk_inserted = $_SESSION['bulk_course_report']['inserted'] ?? 0;
    $bulk_skipped  = $_SESSION['bulk_course_report']['skipped'] ?? 0;
    $bulk_errors   = $_SESSION['bulk_course_report']['errors'] ?? [];

    unset($_SESSION['bulk_course_report']); // clear after reading
}
include_once '../../temp.html';
$demoCourseLabel = inst_is('institute') ? 'دوره موقت' : 'دوره فوق برنامه';
$demoCoursesLabel = inst_is('institute') ? 'دوره‌های موقت' : 'دوره‌های فوق برنامه';
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
    <title> ایجاد <?php echo $demoCourseLabel ?> برای دانش آموز | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
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
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-9">
                                        <div>
                                            <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                <h6 class="main-content-label mb-0">
                                                    ایجاد <?php echo $demoCourseLabel ?> برای دانش آموز
                                                </h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید برای دانش آموز موقت، <?php echo $demoCourseLabel ?> ثبت کنید. این پایه بدون صدور فاکتور و بدون ثبت پرداخت ایجاد می‌شود و برای ارائه دسترسی موقت به دانش آموز مورد استفاده قرار می‌گیرد."
                                                      aria-label="راهنمای ایجاد <?php echo $demoCourseLabel ?> برای دانش آموز">

                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>

                                            </div>

                                            <p class="text-muted card-sub-title">
                                                ثبت <?php echo $demoCourseLabel ?> بدون صدور فاکتور
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php if (isset($erormeli) and $erormeli == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        کد ملی معتبر نیست

                                    </div>
                                <?php } ?>
                                <?php if (isset($idf)) { ?>
                                    <div class="alert alert-success" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $demoCoursesLabel ?> : <?php echo $idf ?> با موفقیت ایجاد شدند.
                                    </div>
                                <?php } ?>
                                <?php if (isset($erorstudent) and $erorstudent == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        این کدملی ، متعلق به همکارآموزشی است و یا امکان ثبت پایه برایش فراهم نیست

                                    </div>
                                <?php } ?>
                                <?php if (isset($notmelli) and $notmelli == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        این شماره تماس در سیستم وجود ندارد
                                    </div>
                                <?php } ?>
                                <?php if (isset($erortoken) and $erortoken == 1) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        خطای غیر منتظره رخ داده است
                                    </div>
                                <?php } ?>
                                <section class="section">
                                    <div class="row">
                                        <div class="card">
                                            <?php if (!isset($_SESSION['add-course2'])) { ?>
                                                <form method="post" data-parsley-validate="">
                                                    <div class="row">
                                                        <div class="col-xl-6 mb-2">
                                                            <label for="sens" class="form-label mg-t-15">کد ملی یا شماره تماس
                                                                دانش آموز: <span style="color: red">*</span></label>
                                                            <input class="form-control" name="tel" type="text"
                                                                   placeholder="با اعداد انگلیسی وارد شود" required>
                                                        </div>

                                                    </div>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">


                                                    <div class="text-center mg-10">
                                                        <button type="submit" class="btn btn-success "
                                                                name="search-tel"><i class="fa fa-search"></i>
                                                            جستجوی دانش آموز
                                                        </button>
                                                    </div>
                                                </form>
                                                <?php if (Permision('excel_permission')) { ?>
                                                    <div class="card-body">
                                                        <form method="post" enctype="multipart/form-data" action="">
                                                            <div class="d-flex flex-column mb-3">
                                                                <div class="d-flex align-items-center mg-t-15" style="gap: 12px;">

                                                                    <label for="excel_file" class="form-label mb-0">
                                                                        ایجاد <?php echo $demoCourseLabel ?> برای گروهی از دانش آموزان
                                                                    </label>

                                                                    <span class="menu-info-trigger"
                                                                          data-tooltip="در این بخش می‌توانید با بارگذاری فایل اکسل، یک <?php echo $demoCourseLabel ?> را برای گروهی از دانش آموزان ثبت کنید. این پایه‌ها بدون صدور فاکتور و ثبت پرداخت ایجاد می‌شوند و اطلاعات فایل اکسل باید مطابق قالب تعیین‌شده باشد. جهت استفاده از این بخش فایل نمونه را دانلود نمایید."
                                                                          aria-label="راهنمای ایجاد گروهی <?php echo $demoCourseLabel ?> برای دانش آموزان">

                                                                            <i class="fe fe-info fs-4"></i>
                                                                        </span>

                                                                </div>
                                                                <div class="input-group file-browser mt-3">
                                                                    <input type="text" class="form-control border-left-0 browse-file"
                                                                           placeholder=" فایل اکسل را بارگذاری کنید*" readonly="">
                                                                    <label class="input-group-btn">
                                                                            <span class="btn btn-primary">
                                                                                انتخاب<input type="file" name="excel_file" id="excel_file"
                                                                                             style="display: none;" required>
                                                                            </span>
                                                                    </label>
                                                                </div>
                                                                <br>
                                                                <button type="submit" name="course_signup"
                                                                        class="btn btn-success w-25 align-self-center"
                                                                        id="openModalBtn">
                                                                    ایجاد <?php echo $demoCourseLabel ?> برای گروهی از دانش آموزان
                                                                </button>
                                                                <br>
                                                                <p class="header-message">
                                                                    طبق فرمت ذیل، فایل خود را آپلود نمایید
                                                                </p>
                                                                <ol style="text-align: right; direction: rtl;">
                                                                    <li>بعد از دانلود فایل، راست کلیک کنید و بر روی گزینه <strong>Properties</strong> کلیک نمایید.</li>
                                                                    <li>در تب <strong>General</strong> پایین پنجره، بخش <strong>Unblock</strong> را تیک بزنید.</li>
                                                                    <li>دکمه <strong>OK</strong> را کلیک کنید و پنجره را ببندید.</li>
                                                                    <li>فایل اکسل را باز کنید و روی <strong>Enable Content</strong> کلیک نمایید</li>
                                                                    <li><strong>ALT+F8</strong> را بزنید و در پنجره ای که باز میشود روی اولین فایل دوبار کلیک نمایید</li>
                                                                    <li>پیغام موفقیت ظاهر می شود و می توانید از کد پایه های فعال استفاده کنید</li>
                                                                </ol>
                                                                <p>
                                                                    <a href="<?php echo url('assets/doc/addcourse-demo.xlsm') ?>" class="btn btn-info ripple"> دانلود فایل نمونه</a>
                                                                </p>
                                                            </div>
                                                        </form>
                                                        <?php if ($bulk_records || $bulk_inserted || $bulk_skipped || !empty($bulk_errors)): ?>
                                                            <div class="card mt-3">
                                                                <div class="card-body">
                                                                    <h5 class="card-title">گزارش ثبت‌نام گروهی</h5>
                                                                    <p>کل ردیف ها: <?= $bulk_records ?></p>
                                                                    <p>ثبت موفق: <?= $bulk_inserted ?></p>
                                                                    <p>ردیف‌های تکراری/رد شده: <?= $bulk_skipped ?></p>

                                                                    <?php if (!empty($bulk_errors)): ?>
                                                                        <div class="alert alert-danger">
                                                                            <ul>
                                                                                <?php foreach ($bulk_errors as $error): ?>
                                                                                    <li><?= htmlspecialchars($error) ?></li>
                                                                                <?php endforeach; ?>
                                                                            </ul>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php } ?>
                                                <!--                                        end step 1-->
                                            <?php }
                                            if (isset($_SESSION['add-course2'])) {
                                                ?>
                                                <div class="row row-sm">
                                                    <div class="col-xl-8">
                                                        <div class="card-header border-bottom-0 pb-1">
                                                            <label class="main-content-label mb-2 pt-1">لیست <?php echo $demoCoursesLabel ?>
                                                            </label>
                                                            <p class="tx-12 mb-0 text-muted"><?php echo $demoCoursesLabel ?> مورد نظر
                                                                برای دانش آموز را
                                                                انتخاب کنید</p>
                                                        </div>
                                                        <?php
                                                        $rest = retPerson($_SESSION['add-course2']['tel']);
                                                        ?>
                                                        <label class="main-content-label mb-2 pt-1 mr-3">نام دانش آموز:
                                                            <span class="text-warning">
                                                                    <?php echo $rest->fname . ' ' . $rest->lname ?>
                                                                </span></label>
                                                        <hr>
                                                        <div class="card-body pt-0">
                                                            <form method="post" data-parsley-validate="">
                                                                <ul class="top-selling-products pb-0 mb-0 pr-0">
                                                                    <?php
                                                                    $sql = 'SELECT * FROM course_demo';
                                                                    $query = $conn->prepare($sql);
                                                                    $query->execute();
                                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                                    if ($query->rowCount() > 0) {
                                                                        foreach ($result as $results) {
                                                                            $sql = 'SELECT * FROM dbcourse_demo WHERE username = ? AND codecourse = ?';
                                                                            $query2 = $conn->prepare($sql);
                                                                            $query2->execute([$_SESSION['add-course2']['tel'], $results->codecourse]);
                                                                            if ($query2->rowCount() > 0) {
                                                                                $registercourse = 1;
                                                                            } else {
                                                                                $registercourse = 0;
                                                                            }
                                                                            ?>
                                                                            <li class="product-item ">
                                                                                <div class="product-img"><input type="checkbox"
                                                                                                                name="course[]"
                                                                                                                value="<?php echo $results->codecourse ?>"
                                                                                        <?php if ($registercourse == 1) {
                                                                                            echo 'disabled';
                                                                                        } ?>></div>
                                                                                <div class="product-info">
                                                                                    <div class="product-name">
                                                                                        <?php if ($registercourse == 1) {
                                                                                            echo '<del>' . htmlentities($results->coursename) . '</del>';
                                                                                        } else {
                                                                                            echo htmlentities($results->coursename);
                                                                                        } ?> </div>
                                                                                    <div class="price">
                                                                                        <?php echo htmlentities($results->coursemuted) ?>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="product-amount">
                                                                                    <div class="items-sold"><?php if ($registercourse == 1) {
                                                                                            echo 'غیرفعال';
                                                                                        } else {
                                                                                            echo 'فعال';
                                                                                        } ?>
                                                                                    </div>
                                                                                </div>
                                                                            </li>
                                                                            <hr>
                                                                            <?php

                                                                        }
                                                                    }
                                                                    ?>
                                                                </ul>
                                                                <?php
                                                                $hash = bin2hex(random_bytes(32));
                                                                $_SESSION['token'] = $hash;
                                                                ?>
                                                                <input class="form-control" name="token" type="hidden"
                                                                       value="<?php echo $hash ?>">
                                                                <input type="hidden" name="user"
                                                                       value="<?php echo $_SESSION['add-course2']['tel'] ?>">
                                                                <div class="text-center mt-3">
                                                                    <button class="btn btn-success ripple"
                                                                            name="sales-course">
                                                                        تایید
                                                                    </button>
                                                                </div>
                                                            </form>
                                                            <div class=" mg-t-10">
                                                                <a href="?change">
                                                                    <button class="btn btn-warning " name="change">
                                                                        تغییر شماره تماس
                                                                    </button>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            <?php } ?>

                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Row -->
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>

</html>