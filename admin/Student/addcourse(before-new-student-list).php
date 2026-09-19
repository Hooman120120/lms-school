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
if (isset($_SESSION['factorId'])) {
    $idf = $_SESSION['factorId'];
    unset($_SESSION['factorId']);
}
if (isset($_GET['change'])) {
    unset($_SESSION['add-course']);
    header('location: ' . url('admin/Student/addcourse.php'));
    die();
}
if (isset($_POST['search-melli'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['codemeli'] != '') {
            $codemeli = trim($_POST['codemeli']);
            // if (check_national_code($codemeli)) {
            if (checkCodemeli($codemeli)) {
                if (typeUser($codemeli) == 1) {

                    $_SESSION['add-course'] = array(
                        "codemeli" => $_POST['codemeli']
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
        if (isset($_POST['course']) and $_POST['course'] > 0) {
            $total = 0;
            foreach ($_POST['course'] as $code) {
                $fee = getfee($code);
                if ($fee != 0) {
                    $total = $total + $fee;
                }
            }
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $ip = GetIp();
            $os = GetOs();
            $add = 'INSERT INTO dbfactor (username,sumitem,subtotal,factorfee,wallet,datecreate,timecreate,factoradder,ip ,os) VALUES (?,?,?,?,?,?,?,?,?,?)';
            $inadd = $conn->prepare($add);
            $inadd->execute([$_SESSION['add-course']['codemeli'], $total, $total, $total, 0, $datenow, $timenow, $_SESSION['admin']['user'], $ip, $os]);
            $factorId = $conn->lastInsertId();
            $_SESSION['factorId'] = $factorId;
            $poshtiban = $_POST['suporter'];
            foreach ($_POST['course'] as $code) {
                $inc = 'INSERT INTO dbcourse (username,codecourse,numfactor,suporter) VALUES (?,?,?,?)';
                $inp = $conn->prepare($inc);
                $inp->execute([$_SESSION['add-course']['codemeli'], $code, $factorId, $poshtiban]);
            }
            unset($_SESSION['add-course']);

            header('location: addcourse.php');
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
    <title> ایجاد دوره برای دانش آموز | <?php echo __TITLE ?></title>
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
                                                <h6 class="main-content-label mb-1">ایجاد دوره برای دانش آموز </h6>
                                                <p class="text-muted card-sub-title">صدور فاکتور جدید برای دانش آموز
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (isset($erormeli) and $erormeli == 1) { ?>
                                        <div class="alert alert-danger" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            شناسه کاربری معتبر نیست

                                        </div>
                                    <?php } ?>
                                    <?php if (isset($idf)) { ?>
                                        <div class="alert alert-success" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            فاکتور شماره : <?php echo $idf ?> با موفقیت صادر شد. دانش آموز
                                            و یا حسابدار
                                            می بایست فاکتور را پرداخت نمایند

                                        </div>
                                    <?php } ?>
                                    <?php if (isset($erorstudent) and $erorstudent == 1) { ?>
                                        <div class="alert alert-danger" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            این شناسه کاربری ، متعلق به همکارآموزشی است و یا امکان ثبت دوره برایش فراهم نیست

                                        </div>
                                    <?php } ?>
                                    <?php if (isset($notmelli) and $notmelli == 1) { ?>
                                        <div class="alert alert-danger" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            این شناسه کاربری در سیستم وجود ندارد

                                        </div>
                                    <?php } ?>
                                    <?php if (isset($erortoken) and $erortoken == 1) { ?>
                                        <div class="alert alert-warning" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            خطای غیر منتظره رخ داده است
                                        </div>
                                    <?php } ?>
                                    <section class="section">
                                        <div class="row">
                                            <div class="card">
                                                <?php if (!isset($_SESSION['add-course'])) { ?>
                                                    <form method="post" data-parsley-validate="">
                                                        <div class="row">
                                                            <div class="col-xl-6 mb-2">
                                                                <label for="sens" class="form-label mg-t-15">شناسه کاربری 
                                                                    دانش آموز: <span
                                                                        style="color: red">*</span></label>
                                                                <input class="form-control" name="codemeli"
                                                                    type="text"
                                                                    placeholder="با اعداد انگلیسی وارد شود"
                                                                    required>
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
                                                                name="search-melli"><i
                                                                    class="fa fa-search"></i>
                                                                جستجوی دانش آموز
                                                            </button>
                                                        </div>
                                                    </form>
                                                    <?php if (Permision('excel_permission')) { ?>
                                                    <div class="card-body">
                                                        <form method="post" enctype="multipart/form-data" action="">
                                                            <div class="d-flex flex-column mb-3">
                                                                <label for="excel_file" class="form-label mg-t-15">ثبت دوره گروهی برای دانش آموزان</label>
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
                                                                <button type="button" name="course_signup"
                                                                    class="btn btn-success w-25 align-self-center"
                                                                    id="openModalBtn">
                                                                    ثبت‌ دوره گروهی دانش آموزان
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
                                                                    <li>پیغام موفقیت ظاهر می شود و می توانید از کد دوره های فعال استفاده کنید</li>
                                                                </ol>
                                                                <p>
                                                                    <a href="<?php echo url('assets/doc/addcourse.xlsm') ?>" class="btn btn-info ripple"> دانلود فایل نمونه</a>
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
                                                if (isset($_SESSION['add-course'])) {
                                                ?>
                                                    <div class="row row-sm">
                                                        <div class="col-xl-8">
                                                            <div class="card-header border-bottom-0 pb-1">
                                                                <label class="main-content-label mb-2 pt-1">دوره های در حال ثبت نام</label>
                                                                <p class="tx-12 mb-0 text-muted">دوره های مورد نظر برای دانش آموز را انتخاب کنید</p>
                                                            </div>

                                                            <?php
                                                            $rest = retPerson($_SESSION['add-course']['codemeli']);
                                                            ?>
                                                            <label class="main-content-label mb-2 pt-1">
                                                                نام دانش آموز:
                                                                <span class="text-warning">
                                                                    <?php echo htmlspecialchars($rest->fname . ' ' . $rest->lname, ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                            </label>
                                                            <hr>

                                                            <!-- 🔎 فیلتر دوره‌ها بر اساس پایه -->
                                                            <div class="row mb-3 align-items-center">

                                                                <div class="col-12 mb-2">
                                                                    <h6 class="main-content-label mb-1">فیلتر دوره های فعال</h6>
                                                                </div>

                                                                <div class="col-12 col-lg-3 mb-2">
                                                                    <label for="courseTypeFilter" class="form-label mb-1"></label>
                                                                    <select id="courseTypeFilter"
                                                                            class="form-control bg-primary text-white border-0">
                                                                        <option value="all">همه دوره‌ها</option>
                                                                        <option value="0">آموزشی</option>
                                                                        <option value="2">المپیاد</option>
                                                                        <option value="1">مهارتی</option>
                                                                    </select>
                                                                </div>

                                                            </div>
                                                            <!-- /فیلتر -->

                                                            <div class="card-body pt-0">
                                                                <form method="post" data-parsley-validate="">
                                                                    <ul class="top-selling-products pb-0 mb-0 pr-0">
                                                                        <?php
                                                                        // ⚠️ فرض: جدول codtbl_course ستون های paye و reshte دارد
                                                                        // اگر نام ستون‌ها چیز دیگری است، اینجا را تغییر بده.
                                                                        $sql = 'SELECT * FROM codtbl_course WHERE courseactive = ?';
                                                                        $query = $conn->prepare($sql);
                                                                        $query->execute([1]);
                                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);

                                                                        if ($query->rowCount() > 0) {
                                                                            foreach ($result as $results) {
                                                                                if (checkactCourse($_SESSION['add-course']['codemeli'], $results->codecourse)) {
                                                                                    $registercourse = 1;
                                                                                } else {
                                                                                    $registercourse = 0;
                                                                                }

                                                                                // امن‌سازی مقادیر
                                                                                $courseName  = htmlentities($results->coursename, ENT_QUOTES, 'UTF-8');
                                                                                $courseMuted = htmlentities($results->coursemuted, ENT_QUOTES, 'UTF-8');
                                                                                $courseFee   = (int)$results->coursefee;

                                                                                // ⚠️ این دو خط را با نام واقعی ستون‌های پایه/رشته در دیتابیست هماهنگ کن
                                                                                $courseGrade = isset($results->paye)   ? htmlentities($results->paye,   ENT_QUOTES, 'UTF-8') : '';
                                                                                $courseField = isset($results->reshte) ? htmlentities($results->reshte, ENT_QUOTES, 'UTF-8') : '';
                                                                                ?>
                                                                                <?php
                                                                                $courseGrade = isset($results->paye)        ? htmlentities($results->paye,   ENT_QUOTES, 'UTF-8') : '';
                                                                                $courseField = isset($results->reshte)      ? htmlentities($results->reshte, ENT_QUOTES, 'UTF-8') : '';
                                                                                $courseType  = isset($results->course_type) ? (int)$results->course_type : null;
                                                                                ?>
                                                                                <li class="product-item"
                                                                                    data-grade="<?php echo $courseGrade; ?>"
                                                                                    data-field="<?php echo $courseField; ?>"
                                                                                    data-type="<?php echo $courseType; ?>">

                                                                                <div class="product-img">
                                                                                        <input type="checkbox"
                                                                                               name="course[]"
                                                                                               value="<?php echo $results->codecourse; ?>"
                                                                                            <?php echo ($registercourse == 1) ? 'disabled' : ''; ?>>
                                                                                    </div>
                                                                                    <div class="product-info">
                                                                                        <div class="product-name">
                                                                                            <?php
                                                                                            if ($registercourse == 1) {
                                                                                                echo '<del>' . $courseName . '</del>';
                                                                                            } else {
                                                                                                echo $courseName;
                                                                                            }
                                                                                            ?>
                                                                                        </div>
                                                                                        <div class="price"><?php echo $courseMuted; ?></div>
                                                                                    </div>
                                                                                    <div class="product-amount">
                                                                                        <div class="product-price">
                                                                                            <?php echo number_format($courseFee); ?> تومان
                                                                                        </div>
                                                                                        <div class="items-sold">
                                                                                            <?php echo ($registercourse == 1) ? 'غیرفعال' : 'فعال'; ?>
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
                                                                    <input class="form-control" name="token" type="hidden" value="<?php echo $hash; ?>">
                                                                    <input type="hidden" name="user"
                                                                           value="<?php echo htmlspecialchars($_SESSION['add-course']['codemeli'], ENT_QUOTES, 'UTF-8'); ?>">

                                                                    <div class="col-lg-8">
                                                                        <p class="mg-b-10 mg-t-10">پشتیبان <span class="tx-danger">*</span></p>
                                                                        <select class="form-control select2" name="suporter" required>
                                                                            <option label="دسته بندی ها"></option>
                                                                            <?php
                                                                            $sql = 'SELECT * FROM dbadmin WHERE simple_operator = ?';
                                                                            $query = $conn->prepare($sql);
                                                                            $query->execute([1]);
                                                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                                            if ($query->rowCount() > 0) {
                                                                                foreach ($result as $admin) { ?>
                                                                                    <option><?php echo htmlspecialchars($admin->username, ENT_QUOTES, 'UTF-8'); ?></option>
                                                                                <?php }
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>

                                                                    <div class="text-center mt-3">
                                                                        <button class="btn btn-success ripple" name="sales-course">
                                                                            تایید و صدور فاکتور
                                                                        </button>
                                                                    </div>
                                                                </form>

                                                                <div class="mg-t-10">
                                                                    <a href="?change">
                                                                        <button class="btn btn-warning" type="button" name="change">
                                                                            تغییر شناسه کاربری
                                                                        </button>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-4">
                                                            <div class="table">
                                                                <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>ردیف</th>
                                                                            <th>پشتیبان</th>
                                                                            <th>تعداد ( نفر دوره )</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        $sql = 'SELECT COUNT(*) as coreg , dbadmin.username FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse RIGHT JOIN dbadmin ON dbadmin.username=dbcourse.suporter  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? GROUP BY dbcourse.suporter ORDER BY coreg DESC ';
                                                                        $query = $conn->prepare($sql);
                                                                        $query->execute(['موفق']);
                                                                        $refrence = $query->fetchAll(PDO::FETCH_OBJ);
                                                                        if ($query->rowCount() > 0) {
                                                                            $count = 1;
                                                                            foreach ($refrence as $ted) {

                                                                        ?>
                                                                                <tr>
                                                                                    <td class="wd-15"><?php echo $count ?></td>
                                                                                    <td><?php echo $ted->username ?></td>
                                                                                    <td class="wd-100"><?php echo $ted->coreg ?></td>
                                                                                </tr>
                                                                        <?php
                                                                                $count++;
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </tbody>
                                                                </table>


                                                            </div>
                                                        </div>
                                                    </div>

                                                <?php } ?>
                                                <!-- Confirmation Modal -->
                                                <div class="modal fade" id="confirmModal" tabindex="-1"
                                                    aria-labelledby="confirmModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="confirmModalLabel">تأیید
                                                                    عملیات</h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal" aria-label="بستن"></button>
                                                            </div>
                                                            <div class="modal-body text-center">
                                                                آیا فاکتورهای صادر شده تأیید واریزی شوند؟
                                                            </div>
                                                            <div class="modal-footer justify-content-center">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">لغو عملیات
                                                                </button>
                                                                <button type="button" class="btn btn-danger" id="noBtn">
                                                                    خیر
                                                                </button>
                                                                <button type="button" class="btn btn-success" id="yesBtn">
                                                                    بله
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

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
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const openModalBtn = document.getElementById("openModalBtn");
                const yesBtn = document.getElementById("yesBtn");
                const noBtn = document.getElementById("noBtn");
                const excelForm = document.querySelector('form[enctype="multipart/form-data"]');
                const fileInput = excelForm.querySelector('input[type="file"]');
                const confirmModal = new bootstrap.Modal(document.getElementById("confirmModal"));

                // 🚦 وقتی روی دکمه "ثبت دوره گروهی" کلیک شد
                openModalBtn.addEventListener("click", function() {
                    if (!fileInput.value) {
                        // اگر فایل انتخاب نشده بود
                        alert("لطفاً ابتدا فایل اکسل را انتخاب کنید.");
                        fileInput.focus();
                        return;
                    }
                    // اگر فایل انتخاب شده بود، مودال باز شود
                    confirmModal.show();
                });

                // 🚀 تابع ارسال فرم با مقدار انتخابی
                function submitForm(confirmValue) {
                    let oldInput = document.querySelector('input[name="confirm_invoice"]');
                    if (oldInput) oldInput.remove();

                    let hiddenInput = document.createElement("input");
                    hiddenInput.type = "hidden";
                    hiddenInput.name = "confirm_invoice";
                    hiddenInput.value = confirmValue;
                    excelForm.appendChild(hiddenInput);

                    excelForm.submit();
                }

                yesBtn.addEventListener("click", function() {
                    submitForm("yes");
                });

                noBtn.addEventListener("click", function() {
                    submitForm("no");
                });
            });
//here
                document.addEventListener("DOMContentLoaded", function () {
                // ----- فیلتر دوره ها بر اساس پایه / رشته + نوع دوره -----
                const filterButtons     = document.querySelectorAll(".btn-course-filter");
                const courseItems       = document.querySelectorAll(".top-selling-products .product-item");
                const typeFilterSelect  = document.getElementById("courseTypeFilter");

                // اگر هنوز فیلتر یا لیست دوره‌ها روی صفحه نیست، کاری نکن
                if (!filterButtons.length || !courseItems.length) {
                return;
            }

                let currentGradeFilter = "all";

                function matchesFilter(item, gradeFilter, typeFilter) {
                if (!gradeFilter) gradeFilter = "all";
                if (!typeFilter)  typeFilter  = "all";

                const grade = (item.dataset.grade || "").trim();
                const field = (item.dataset.field || "").trim();
                const type  = (item.dataset.type  || "").trim();

                // ---- فیلتر نوع دوره (course_type) ----
                // typeFilter: "all" | "0" | "1" | "2"
                if (typeFilter !== "all") {
                if (type !== typeFilter) {
                return false;
            }
            }

                // ---- فیلتر پایه / رشته (قبلی) ----
                if (gradeFilter === "all") {
                return true;
            }

                const parts       = gradeFilter.split(",");
                const baseGrade   = parts[0];        // e.g. "دهم" یا "متوسطه_اول" یا "ابتدایی"
                const fieldFilter = parts[1] || "";  // e.g. "ریاضی"

                // گروه‌های کلی
                if (baseGrade === "ابتدایی") {
                return ["اول", "دوم", "سوم", "چهارم", "پنجم", "ششم"].includes(grade);
            }
                if (baseGrade === "متوسطه_اول") {
                return ["هفتم", "هشتم", "نهم"].includes(grade);
            }
                if (baseGrade === "متوسطه_دوم") {
                return ["دهم", "یازدهم", "دوازدهم"].includes(grade);
            }

                // فیلتر پایه مشخص
                if (fieldFilter) {
                // پایه + رشته
                return grade === baseGrade && field === fieldFilter;
            } else {
                // فقط پایه
                return grade === baseGrade;
            }
            }

                function applyFilter() {
                const typeFilter = typeFilterSelect ? typeFilterSelect.value : "all";

                courseItems.forEach(function (item) {
                if (matchesFilter(item, currentGradeFilter, typeFilter)) {
                item.style.display = "";
                const hr = item.nextElementSibling;
                if (hr && hr.tagName === "HR") {
                hr.style.display = "";
            }
            } else {
                item.style.display = "none";
                const hr = item.nextElementSibling;
                if (hr && hr.tagName === "HR") {
                hr.style.display = "none";
            }
            }
            });
            }

                // کلیک روی دکمه‌های پایه / رشته
                filterButtons.forEach(function (btn) {
                btn.addEventListener("click", function (e) {
                e.preventDefault();

                const filter = this.dataset.filter || "all";
                currentGradeFilter = filter;

                // فعال کردن ظاهر دکمه انتخاب‌شده
                filterButtons.forEach(function (b) {
                b.classList.remove("active");
            });
                this.classList.add("active");

                applyFilter();
            });
            });

                // تغییر نوع دوره (course_type)
                if (typeFilterSelect) {
                typeFilterSelect.addEventListener("change", function () {
                applyFilter();
            });
            }

                // فیلتر اولیه (نمایش همه)
                applyFilter();
            });
        </script>


</body>

</html>