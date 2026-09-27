<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function-quiz.php';
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
//* End Check Session JUST ONE Session
if (!Permision('permision_viewcourse')) {
    header('location: ' . url('admin/index.php'));
    die();
}

$currentStaffUser = (string)($_SESSION['admin']['user'] ?? '');
if (StaffCourseScopeRequired($currentStaffUser)) {
    $editCourseCode = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
    if ($editCourseCode <= 0 || !StaffHasCourseAccess($editCourseCode, $currentStaffUser)) {
        http_response_code(403);
        exit('شما فقط مجاز به مشاهده و ویرایش پایه‌های تخصیص‌یافته هستید.');
    }
}

if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (Checkcodecourse($_POST['code'])) {
//            include_once '../../quizconfig.php';
//            include_once '../../inc/function-quiz.php';
//            if (checkEntityGroupQuiz($_POST['code'])) {

                $code = intval($_POST['code']);
                $paye = implode('/', $_POST['paye']);
                if (isset($_POST['meta'])) {
                    $meta = $_POST['meta'];
                } else {
                    $meta = NULL;
                }
                if (isset($_POST['activity-register'])) {
                    $register = 1;
                } else {
                    $register = 0;
                }
                if (isset($_POST['activity-course'])) {
                    $actcourse = 1;
                } else {
                    $actcourse = 0;
                }
                $value = intval($_POST['value']);
                $value_without = intval($_POST['value_without']);
//                $teachers = isset($_POST['teacher']) ? $_POST['teacher'] : NULL;
                $package_id = (!empty($_POST['package_id'])) ? intval($_POST['package_id']) : NULL;
                date_default_timezone_set("Iran");
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');
                $course_type = intval($_POST['course_type']);
                $addcourse = 'INSERT INTO codtbl_course (codecourse,coursename,coursemuted,coursemeta,paye,piclink,detail_link,coursefee,coursefee_without,courseactive,activecourse,link_class,link_general,link_eita,girls_group,guys_group,datacreated,timecreated,useradder,course_type,package_id) 
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

                $qinsert = $conn->prepare($addcourse);
                $qinsert->execute([
                    $code,
                    $_POST['coursename'],
                    $_POST['muted'],
                    $meta,
                    $paye,
                    $_POST['piclink'],
                    $_POST['detail-link'],
                    $value,
                    $value_without,
                    $register,
                    $actcourse,
                    $_POST['class'],
                    $_POST['general'],
                    $_POST['chanel'],
                    $_POST['girls_group'] ?? null,
                    $_POST['guys_group'] ?? null,
                    $datenow,
                    $timenow,
                    $_SESSION['admin']['user'],
                    $course_type,
                    $package_id
                ]);

                $currentCourseId = $conn->lastInsertId();
                $sql = "SELECT codecourse FROM codtbl_course WHERE idcourse = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$currentCourseId]);
                $currentCode = $stmt->fetchColumn();
                linkCourseToSelectedAcademicYear((int)$currentCode);
                //
                $additionalText = '';
                if (isset($_POST['activity-quiz'])) {
                    $apiResult = CreateGroupByApi(1, $code, $_POST['coursename']);
                    if (isset($apiResult['success']) && $apiResult['success'] === true) {
                        $ugid = $apiResult['ugid'];
                        $additionalText = ' و در سامانه آزمون ثبت گردید';
                    } else {
                        $ugid = null;
                        $errorMsg = ($apiResult['message'] . ' | ' . $apiResult['error']) ?? $apiResult['error'] ?? 'خطای ناشناخته در ارتباط با سامانه آزمون';
                        $additionalText = ' اما در ساخت گروه آزمون خطایی رخ داد: ' . $errorMsg;
                    }
                    $sql = 'UPDATE codtbl_course SET ugid = ? WHERE codecourse = ?';
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$ugid, $code]);
                }
                // $addquiz = 'INSERT INTO `_ugroups`(aid,ugtitle,ugdescription) VALUES (?,?,?)';
                // global $ponn;
                // $qq = $ponn->prepare($addquiz);
                // $qq->execute([1, $code, $_POST['coursename']]);

//                if (!is_null($teachers)) {
//                    $insertTeachersSql = 'INSERT INTO course_teacher (codecourse,teacher_username) VALUES (?,?)';
//                    $insertTeachersStmt = $conn->prepare($insertTeachersSql);
//                    foreach ($teachers as $teacher) {
//                        $insertTeachersStmt->execute([$currentCode, $teacher]);
//                    }
//                }

                $text = 'پایه ' . $_POST['coursename'] . ' با موفقیت ایجاد شد' . $additionalText;
//            } else {
//                $erordublicate = 'این کد پایه، در سامانه آزمون موجود است';
//            }
        } else {
            $erordublicate = 'این کد پایه، متعلق به پایه دیگری است';
        }
    } else {
        $erortoken = 'خطای غیر منتظره رخ داد، لطفا مجدد امتحان کنید';
    }
}
if (isset($_POST['save-update'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (CheckcodecourseForupdat($_POST['code'], $_GET['edit'])) {
//            include_once '../../quizconfig.php';
//            include_once '../../inc/function-quiz.php';

            $code = intval($_POST['code']);
            $paye = implode('/', $_POST['paye']);
            if (isset($_POST['meta'])) {
                $meta = $_POST['meta'];
            } else {
                $meta = NULL;
            }
            if (isset($_POST['activity-register'])) {
                $register = 1;
            } else {
                $register = 0;
            }
            if (isset($_POST['activity-course'])) {
                $actcourse = 1;
            } else {
                $actcourse = 0;
            }
//            $teachers = isset($_POST['teacher']) ? $_POST['teacher'] : NULL;
            $value = intval($_POST['value']);
            $value_without = intval($_POST['value_without']);
            $course_type = intval($_POST['course_type']);
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $package_id = (!empty($_POST['package_id']) && intval($_POST['package_id']) !== 0) ? intval($_POST['package_id']) : NULL;            $update = 'UPDATE codtbl_course SET
  codecourse=?,coursename=?,coursemuted=?,coursemeta=?,paye=?,piclink=?,detail_link=?,coursefee=?,coursefee_without=?,courseactive=?,activecourse=?,
  link_class=?,link_general=?,link_eita=?,girls_group=?,guys_group=?,
  datacreated=?,timecreated=?,useradder=?,course_type=?,package_id=?
  WHERE idcourse=?';

            $qup = $conn->prepare($update);
            $qup->execute([
                $code,
                $_POST['coursename'],
                $_POST['muted'],
                $meta,
                $paye,
                $_POST['piclink'],
                $_POST['detail-link'],
                $value,
                $value_without,
                $register,
                $actcourse,
                $_POST['class'],
                $_POST['general'],
                $_POST['chanel'],
                $_POST['girls_group'] ?? null,
                $_POST['guys_group'] ?? null,
                $datenow,
                $timenow,
                $_SESSION['admin']['user'],
                $course_type,
                $package_id,
                $_GET['id']
            ]);
            UpdateGroupByApi($code, $_POST['coursename'], $_GET['edit']);
            // $updatequiz = 'UPDATE `_ugroups` SET ugtitle=? ,ugdescription=? WHERE ugtitle=? ';
            // global $ponn;
            // $adt = $ponn->prepare($updatequiz);
            // $adt->execute([$code, $_POST['coursename'], $_GET['edit']]);
            $text = 'پایه ' . $_POST['coursename'] . ' با موفقیت ویرایش شد';
            if (isset($_GET['id'])) {
                $stmt = $conn->prepare("SELECT * FROM codtbl_course WHERE idcourse = ?");
                $stmt->execute([$_GET['id']]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
            }
//            if (!is_null($teachers)) {
//                $deletePreviousTeachersSql = 'DELETE FROM course_teacher WHERE codecourse = ?';
//                $deletePreviousTeachersQuery = $conn->prepare($deletePreviousTeachersSql);
//                $deletePreviousTeachersQuery->execute([$_GET['edit']]);
//
//                foreach ($teachers as $teacher) {
//                    $setNewTeachersSql = 'INSERT INTO course_teacher (codecourse,teacher_username) VALUES (?,?)';
//                    $setNewTeachersQuery = $conn->prepare($setNewTeachersSql);
//                    $setNewTeachersQuery->execute([$_GET['edit'], $teacher]);
//                }
//            }
        } else {
            $erordublicate = 'این کد پایه، متعلق به پایه دیگری است';
        }
    } else {
        $erortoken = 'خطای غیر منتظره رخ داد، لطفا مجدد امتحان کنید';
    }
}

$apiPackages = getPackageGroups();

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
    <title> ثبت پایه جدید | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!--    <div id="global-loader">-->
<!--        <img src="-->
<?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--    </div>-->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';

    ?>
    <!-- Main Content-->
    <?php if (!isset($_GET['edit'])) { ?>
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="row row-sm">
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                    <h6 class="main-content-label mb-0">
                                                        افزودن پایه جدید
                                                    </h6>

                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش می‌توانید یک پایه یا محصول آموزشی جدید ایجاد کنید و اطلاعاتی مانند نام پایه، توضیحات، مدت، مبلغ، وضعیت فعالیت و سایر تنظیمات موردنیاز را ثبت نمایید."
                                                          aria-label="راهنمای افزودن پایه جدید">

                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>

                                                </div>

                                                <p class="text-muted card-sub-title">
                                                    ایجاد یک پایه جدید
                                                </p>
                                            </div>
                                        </div>

                                    </div>
                                    <?php if (isset($text)) { ?>
                                        <div class="alert alert-success" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            <?php echo $text ?>
                                        </div>
                                    <?php } ?>
                                    <?php if (isset($erordublicate)) { ?>
                                        <div class="alert alert-danger" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            <?php echo $erordublicate ?>
                                        </div>
                                    <?php } ?>
                                    <?php if (isset($erortoken)) { ?>
                                        <div class="alert alert-warning" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            <?php echo $erortoken ?>
                                        </div>
                                    <?php } ?>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            کد پایه:
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="code"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="فقط عدد وارد کنید">
                                                </div>
                                            </div>

                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام پایه:
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="coursename"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="پایه هوش پاییزی 1402">
                                                </div>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                            </div>
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            توضیح کوتاه:
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="muted"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="ویژه دانش آموزان پایه ششم و نهم ">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row ">
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> انتخاب دسته بندی <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="paye[]" multiple="multiple"
                                                        required>
<?php echo inst_tag_options() ?>
</select>
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> آدرس تصویر <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="piclink"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="http://dl.yourdomain.ir/aasets/img/3.jpg">
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> آدرس توضیحات بیشتر <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="detail-link"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="http://yourdomain.ir/news/page=10">
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <div class="col-lg-4">
                                                <p class="mg-b-10">مبلغ پایه <?= inst_is('school') ? 'با مشاور' : 'با پشتیبان' ?><span
                                                            class="tx-danger">*</span></p>


                                                <div class="input-group mb-3">
                                                    <input aria-describedby="basic-addon2" aria-label="شارژ حساب"
                                                           name="value" class="form-control" value="0"
                                                           placeholder="مبلغ پایه به تومان" type="number" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" id="basic-addon2">تومان</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10">مبلغ پایه <?= inst_is('school') ? 'بدون مشاور' : 'بدون پشتیبان' ?></p>


                                                <div class="input-group mb-3">
                                                    <input aria-describedby="basic-addon2" aria-label="شارژ حساب"
                                                           name="value_without" class="form-control" value="0"
                                                           placeholder="مبلغ پایه به تومان" type="number" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" id="basic-addon2">تومان</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10"> شناسه اتاق چهاردیواری <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="class"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="4562153">
                                            </div>
                                            <div class="col-lg-12">
                                                <p class="mg-b-10 "> آدرس کانال پایه <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="chanel"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="http://eitaa.com/3jkfm54ffgh">
                                            </div>
                                        </div>
                                        <div class="row ">
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 "> شناسه اتاق عمومی <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="general"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="4181779">
                                            </div>
                                            <div class="col-4">
                                                <p class="mg-b-10 "> توضیحات بیشتر (قبول html)</p>
                                                <textarea class="form-control" name="meta"></textarea>
                                            </div>
                                            <div class="mb-3 col-12 col-sm-6 col-md-4">
                                                <label for="course_type" class="form-label">نوع پایه</label>
                                                <select name="course_type" id="course_type" class="form-select"
                                                        required>
                                                    <option value="" disabled selected>انتخاب نوع پایه</option>
                                                    <option value="0">آموزشی</option>
                                                    <option value="1">مهارت</option>
                                                    <option value="2">المپیاد</option>
                                                </select>
                                            </div>

                                        </div>
                                        <div class="row">

                                            <div class="mb-3 col-12 col-sm-6 col-md-4">
                                                <label for="package_id" class="form-label">اتصال به پکیج آزمون</label>
                                                <select name="package_id" id="package_id" class="form-control select2">
                                                    <option value="" selected>بدون اتصال به پکیج (هیچکدام)</option>
                                                    <?php foreach ($apiPackages as $package): ?>
                                                        <option value="<?= $package['gid'] ?>">
                                                            <?= htmlspecialchars($package['gtitle']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

<?php /*
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> <?php echo m('label.choose_teacher') ?></p>
                                                <select class="form-control select2" name="teacher[]"
                                                        multiple="multiple">
                                                    <?php
                                                    $sql = "SELECT * FROM dbadmin WHERE adminrole = 3";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute();
                                                    $teachers = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                    if ($stmt->rowCount() > 0) {
                                                        foreach ($teachers as $teacher) {
                                                            ?>
                                                            <option value="<?= $teacher->username ?>">
                                                                <?= $teacher->fname . ' ' . $teacher->lname ?>
                                                            </option>
                                                        <?php }
                                                    } else { ?>
                                                        <option value="">
                                                            داده ای وجود ندارد
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>

   */ ?>
                                            <div class="col-lg-4 p-3">
                                                <p class="mg-b-10">لینک گروه دختران (پیام رسان)</p>
                                                <input class="form-control" name="girls_group" type="text"
                                                       placeholder="https://eitaa.com/joinchat">
                                            </div>

                                            <div class="col-lg-4 p-3">
                                                <p class="mg-b-10">لینک گروه پسران (پیام رسان)</p>
                                                <input class="form-control" name="guys_group" type="text"
                                                       placeholder="https://eitaa.com/joinchat">
                                            </div>
                                        </div>

                                        <hr>
                                        <div class="row ">
                                            <div class="col-xl-4 mg-t-10">
                                                <label class="custom-switch">
                                                    <input type="checkbox"
                                                           name="activity-register"
                                                           class="custom-switch-input" checked>
                                                    <span class="custom-switch-indicator"></span>
                                                    <span class=""> فعال سازی ثبت نام </span>
                                                </label>
                                            </div>
                                            <div class="col-xl-4 mg-t-10">
                                                <label class="custom-switch">
                                                    <input type="checkbox"
                                                           name="activity-course"
                                                           class="custom-switch-input" checked>
                                                    <span class="custom-switch-indicator"></span>
                                                    <span class=""> فعال سازی پایه </span>
                                                </label>
                                            </div>
                                            <div class="col-xl-4 mg-t-10">
                                                <label class="custom-switch">
                                                    <input type="checkbox"
                                                           name="activity-quiz"
                                                           class="custom-switch-input" checked>
                                                    <span class="custom-switch-indicator"></span>
                                                    <span class=""> ساخت معادل این پایه در سامانه آزمون </span>
                                                </label>
                                            </div>

                                        </div>
                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save"
                                                    class="btn btn-primary  mr-1">
                                                تایید و ایجاد پایه
                                            </button>

                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
    <?php if (isset($_GET['edit'])) { ?>
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="row row-sm">
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <h6 class="main-content-label mb-1">ویرایش پایه </h6>
                                                <p class="text-muted card-sub-title">ویرایش پایه ای که از قبل ایجاد شده
                                                </p>
                                            </div>
                                        </div>

                                    </div>
                                    <?php if (isset($text)) { ?>
                                        <div class="alert alert-success" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            <?php echo $text ?>
                                        </div>
                                    <?php } ?>
                                    <?php if (isset($erordublicate)) { ?>
                                        <div class="alert alert-danger" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            <?php echo $erordublicate ?>
                                        </div>
                                    <?php } ?>
                                    <?php if (isset($erortoken)) { ?>
                                        <div class="alert alert-warning" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            <?php echo $erortoken ?>
                                        </div>
                                    <?php } ?>
                                    <?php
                                    $result = returnCourse($_GET['edit']);
                                    ?>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            کد پایه:
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="code"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           value="<?php echo $result->codecourse; ?>"
                                                           placeholder="فقط عدد وارد کنید">
                                                </div>
                                            </div>

                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام پایه:
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="coursename"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           value="<?php echo $result->coursename; ?>"
                                                           placeholder="پایه هوش پاییزی 1402">
                                                </div>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                            </div>
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            توضیح کوتاه:
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="muted"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           value="<?php echo $result->coursemuted; ?>"
                                                           placeholder="ویژه دانش آموزان پایه ششم و نهم ">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row ">
                                            <div class="col-lg-4">
                                                <?php $pay = $result->paye;
                                                $pay = explode('/', $pay);
                                                ?>
                                                <p class="mg-b-10 mg-t-10"> انتخاب دسته بندی <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="paye[]" multiple="multiple"
                                                        required>
<?php echo inst_tag_options($pay) ?>
</select>
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> آدرس تصویر <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="piclink"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $result->piclink; ?>"
                                                       placeholder="http://dl.yourdomain.ir/aasets/img/3.jpg">
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> آدرس توضیحات بیشتر <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="detail-link"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $result->detail_link; ?>"
                                                       placeholder="http://yourdomain.ir/news/page=10">
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <div class="col-lg-4">
                                                <p class="mg-b-10">مبلغ پایه <?= inst_is('school') ? 'با مشاور' : 'با پشتیبان' ?><span
                                                            class="tx-danger">*</span></p>


                                                <div class="input-group mb-3">
                                                    <input aria-describedby="basic-addon2" aria-label="شارژ حساب"
                                                           name="value" class="form-control"
                                                           value="<?php echo $result->coursefee; ?>"
                                                           placeholder="مبلغ پایه به تومان" type="number" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" id="basic-addon2">تومان</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10">مبلغ پایه <?= inst_is('school') ? 'بدون مشاور' : 'بدون پشتیبان' ?></p>


                                                <div class="input-group mb-3">
                                                    <input aria-describedby="basic-addon2" aria-label="شارژ حساب"
                                                           name="value_without" class="form-control"
                                                           value="<?php echo $result->coursefee_without; ?>"
                                                           placeholder="مبلغ پایه به تومان" type="number" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" id="basic-addon2">تومان</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10"> شناسه اتاق اسکای روم <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="class"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $result->link_class; ?>"
                                                       placeholder="http://skyroom.online/yourclass/12">
                                            </div>
                                            <div class="col-lg-12">
                                                <p class="mg-b-10 "> آدرس کانال پایه <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="chanel"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $result->link_eita; ?>"
                                                       placeholder="http://eitaa.com/3jkfm54ffgh">
                                            </div>
                                        </div>
                                        <div class="row ">

                                            <div class="col-lg-4">
                                                <p class="mg-b-10 "> شناسه اتاق عمومی <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="general"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $result->link_general; ?>"
                                                       placeholder="4181779">
                                            </div>
                                            <div class="col-4">
                                                <p class="mg-b-10 "> توضیحات بیشتر (قبول html)</p>
                                                <textarea class="form-control"
                                                          name="meta"><?php echo $result->coursemeta; ?></textarea>
                                            </div>
                                            <div class="mb-3 col-12 col-sm-6 col-md-4">
                                                <label for="course_type" class="form-label">نوع پایه</label>
                                                <select name="course_type" id="course_type" class="form-select">
                                                    <option value="0" <?= ($result->course_type == 0) ? 'selected' : '' ?>>
                                                        آموزشی
                                                    </option>
                                                    <option value="1" <?= ($result->course_type == 1) ? 'selected' : '' ?>>
                                                        مهارت
                                                    </option>
                                                    <option value="2" <?= ($result->course_type == 2) ? 'selected' : '' ?>>
                                                        المپیاد
                                                    </option>
                                                </select>
                                            </div>


                                        </div>
                                        <div class="row">

                                            <div class="mb-3 col-12 col-sm-6 col-md-4">
                                                <label for="package_id" class="form-label">اتصال به پکیج آزمون</label>
                                                <select name="package_id" id="package_id" class="form-control select2">
                                                    <option value="0" <?= (empty($result->package_id)) ? 'selected' : ''; ?>>-- بدون اتصال به پکیج (حذف اتصال) --</option>

                                                    <?php foreach ($apiPackages as $package): ?>
                                                        <?php
                                                        $selected = (isset($result->package_id) && $result->package_id == $package['gid']) ? 'selected' : '';
                                                        ?>
                                                        <option value="<?= $package['gid'] ?>" <?= $selected ?>>
                                                            <?= htmlspecialchars($package['gtitle']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                      <?php /*
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> <?php echo m('label.choose_teacher') ?></p>
                                                <select class="form-control select2" name="teacher[]"
                                                        multiple="multiple">
                                                    <?php
                                                    // گرفتن تمام معلم‌ها
                                                    $sql = "SELECT * FROM dbadmin WHERE adminrole = 3";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute();
                                                    $teachers = $stmt->fetchAll(PDO::FETCH_OBJ);

                                                    // گرفتن معلم‌های مرتبط با پایه
                                                    $sql2 = "SELECT teacher_username FROM course_teacher WHERE codecourse = ?";
                                                    $stmt2 = $conn->prepare($sql2);
                                                    $stmt2->execute([$_GET['edit']]);
                                                    $relatedTeachers = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                                                    if (count($teachers) > 0) {
                                                        foreach ($teachers as $teacher) {

                                                            $selected = in_array($teacher->username, $relatedTeachers) ? 'selected' : '';
                                                            ?>
                                                            <option value="<?= htmlspecialchars($teacher->username) ?>" <?= $selected ?>
                                                            ><?= htmlspecialchars($teacher->fname . ' ' . $teacher->lname) ?>
                                                            </option>
                                                            <?php
                                                        }
                                                    } else {
                                                        ?>
                                                        <option value="">داده‌ای وجود ندارد</option>
                                                    <?php }
                                                    ?>
                                                </select>
                                            </div>

                                          */  ?>

                                            <div class="col-lg-4 pd-3">
                                                <p class="mg-b-10">لینک گروه دختران (ایتا)</p>
                                                <input class="form-control" name="girls_group" type="text"
                                                       value="<?php echo htmlspecialchars($result->girls_group ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                       placeholder="https://eitaa.com/joinchat/....">
                                            </div>

                                            <div class="col-lg-4 pd-3">
                                                <p class="mg-b-10">لینک گروه پسران (ایتا)</p>
                                                <input class="form-control" name="guys_group" type="text"
                                                       value="<?php echo htmlspecialchars($result->guys_group ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                       placeholder="https://eitaa.com/joinchat/....">
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <div class="col-xl-4 mg-t-10">
                                                <label class="custom-switch">
                                                    <input type="checkbox"
                                                           name="activity-register"
                                                           class="custom-switch-input" <?php if ($result->courseactive == 1) {
                                                        echo 'checked';
                                                    } ?>>
                                                    <span class="custom-switch-indicator"></span>
                                                    <span class=""> فعال سازی ثبت نام </span>
                                                </label>
                                            </div>
                                            <div class="col-xl-4 mg-t-10">
                                                <label class="custom-switch">
                                                    <input type="checkbox"
                                                           name="activity-course"
                                                           class="custom-switch-input" <?php if ($result->activecourse == 1) {
                                                        echo 'checked';
                                                    } ?>>
                                                    <span class="custom-switch-indicator"></span>
                                                    <span class=""> فعال سازی پایه </span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save-update"
                                                    class="btn btn-warning  mr-1">
                                                تایید و ویرایش پایه
                                            </button>

                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>

</html>
