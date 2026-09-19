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
//* End Check Session JUST ONE Session
if (!Permision('permision_viewcourse')) {
    header('location: ' . url('admin/index.php'));
    die();
}

if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (Checkcodecourse($_POST['code'])) {
            include_once '../../quizconfig.php';
            include_once '../../inc/function-quiz.php';
            if (checkEntityGroupQuiz($_POST['code'])) {

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
                if (isset($_POST['gender'])) {
                    $gender = 1;
                } else {
                    $gender = 0;
                }
                $value = intval($_POST['value']);
                $teachers = isset($_POST['teacher']) ? $_POST['teacher'] : NULL;
                date_default_timezone_set("Iran");
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');
                $course_type = intval($_POST['course_type']);
                $addcourse = 'INSERT INTO codtbl_course (codecourse,coursename,coursemuted,coursemeta,paye,piclink,detail_link,coursefee,courseactive,activecourse,link_class,link_general,girls_group,guys_group,operator,datacreated,timecreated,useradder,course_type,gender) 
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

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
                    $register,
                    $actcourse,
                    $_POST['class'],
                    $_POST['general'],
                    $_POST['girls_group'] ?? null,
                    $_POST['guys_group'] ?? null,
                    $_POST['operator'] = 1,
                    $datenow,
                    $timenow,
                    $_SESSION['admin']['user'],
                    $course_type,
                    $gender
                ]);

                $currentCourseId = $conn->lastInsertId();
                $sql = "SELECT codecourse FROM codtbl_course WHERE idcourse = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$currentCourseId]);
                $currentCode = $stmt->fetchColumn();
                //
                $addquiz = 'INSERT INTO `_ugroups`(aid,ugtitle,ugdescription) VALUES (?,?,?)';
                global $ponn;
                $qq = $ponn->prepare($addquiz);
                $qq->execute([1, $code, $_POST['coursename']]);

                if (!is_null($teachers)) {
                    $insertTeachersSql = 'INSERT INTO course_teacher (codecourse,teacher_username) VALUES (?,?)';
                    $insertTeachersStmt = $conn->prepare($insertTeachersSql);
                    foreach ($teachers as $teacher) {
                        $insertTeachersStmt->execute([$currentCode, $teacher]);
                    }
                }

                $text = 'دوره ' . $_POST['coursename'] . ' با موفقیت ایجاد شد';
            } else {
                $erordublicate = 'این کد دوره، در سامانه آزمون موجود است';
            }
        } else {
            $erordublicate = 'این کد دوره، متعلق به دوره دیگری است';
        }
    } else {
        $erortoken = 'خطای غیر منتظره رخ داد، لطفا مجدد امتحان کنید';
    }
}
if (isset($_POST['save-update'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (CheckcodecourseForupdat($_POST['code'], $_GET['edit'])) {
            include_once '../../quizconfig.php';
            include_once '../../inc/function-quiz.php';

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
            $gender = isset($_POST['gender']) ? intval($_POST['gender']) : 0;
            $teachers = isset($_POST['teacher']) ? $_POST['teacher'] : NULL;
            $value = intval($_POST['value']);
            $course_type = intval($_POST['course_type']);
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $update = 'UPDATE codtbl_course SET
  codecourse=?,coursename=?,coursemuted=?,coursemeta=?,paye=?,piclink=?,detail_link=?,coursefee=?,courseactive=?,activecourse=?,
  link_class=?,link_general=?,girls_group=?,guys_group=?,
  datacreated=?,timecreated=?,useradder=?,course_type=?,gender=?
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
                $register,
                $actcourse,
                $_POST['class'],
                $_POST['general'],
                $_POST['girls_group'] ?? null,
                $_POST['guys_group'] ?? null,
                $datenow,
                $timenow,
                $_SESSION['admin']['user'],
                $course_type,
                $gender,
                $_GET['id']

            ]);

            $updatequiz = 'UPDATE `_ugroups` SET ugtitle=? ,ugdescription=? WHERE ugtitle=? ';
            global $ponn;
            $adt = $ponn->prepare($updatequiz);
            $adt->execute([$code, $_POST['coursename'], $_GET['edit']]);
            $text = 'دوره ' . $_POST['coursename'] . ' با موفقیت ویرایش شد';
            if (isset($_GET['id'])) {
                $stmt = $conn->prepare("SELECT * FROM codtbl_course WHERE idcourse = ?");
                $stmt->execute([$_GET['id']]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!is_null($teachers)) {
                $deletePreviousTeachersSql = 'DELETE FROM course_teacher WHERE codecourse = ?';
                $deletePreviousTeachersQuery = $conn->prepare($deletePreviousTeachersSql);
                $deletePreviousTeachersQuery->execute([$_GET['edit']]);

                foreach ($teachers as $teacher) {
                    $setNewTeachersSql = 'INSERT INTO course_teacher (codecourse,teacher_username) VALUES (?,?)';
                    $setNewTeachersQuery = $conn->prepare($setNewTeachersSql);
                    $setNewTeachersQuery->execute([$_GET['edit'], $teacher]);
                }
            }
        } else {
            $erordublicate = 'این کد دوره، متعلق به دوره دیگری است';
        }
    } else {
        $erortoken = 'خطای غیر منتظره رخ داد، لطفا مجدد امتحان کنید';
    }
    header("Location: handhug-management.php");
    exit;
}
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
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
    <title> ثبت دوره جدید | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!--    <div id="global-loader">-->
<!--        <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
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
                                                <h6 class="main-content-label mb-1">افزودن گروه بغل دستی جدید </h6>
                                                <p class="text-muted card-sub-title">ایجاد یک گروه بغل دستی جدید
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
                                                            کد گروه بغل دستی:
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
                                                            نام بغل دستی:
                                                            <span
                                                                class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="coursename"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="دوره هوش پاییزی 1402">
                                                </div>
                                                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']) ?>">
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
                                                    <option value="اول">
                                                        پایه اول
                                                    </option>
                                                    <option value="دوم">
                                                        پایه دوم
                                                    </option>
                                                    <option value="سوم">
                                                        پایه سوم
                                                    </option>
                                                    <option value="چهارم">
                                                        پایه چهارم
                                                    </option>
                                                    <option value="پنجم">
                                                        پایه پنجم
                                                    </option>
                                                    <option value="ششم">
                                                        پایه ششم
                                                    </option>
                                                    <option value="هفتم">
                                                        پایه هفتم
                                                    </option>
                                                    <option value="هشتم">
                                                        پایه هشتم
                                                    </option>
                                                    <option value="نهم">
                                                        پایه نهم
                                                    </option>
                                                    <option value="دهم">
                                                        پایه دهم
                                                    </option>
                                                    <option value="یازدهم">
                                                        پایه یازدهم
                                                    </option>
                                                    <option value="دوازدهم">
                                                        پایه دوازدهم
                                                    </option>
                                                    <option value="ابتدایی">
                                                        گروه ابتدایی
                                                    </option>
                                                    <option value="متوسطه_اول">
                                                        متوسطه اول
                                                    </option>
                                                    <option value="متوسطه_دوم">
                                                        متوسطه دوم
                                                    </option>
                                                    <option value="ریاضی">
                                                        رشته ریاضی
                                                    </option>
                                                    <option value="تجربی">
                                                        رشته تجربی
                                                    </option>
                                                    <option value="انسانی">
                                                        رشته انسانی
                                                    </option>
                                                    <option value="فنی">
                                                        فنی و حرفه ای
                                                    </option>
                                                    <option value="کارودانش">
                                                        کار و دانش
                                                    </option>
                                                    <option>
                                                        خانواده
                                                    </option>
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
                                                <p class="mg-b-10">مبلغ <span
                                                        class="tx-danger">*</span></p>


                                                <div class="input-group mb-3">
                                                    <input aria-describedby="basic-addon2" aria-label="شارژ حساب"
                                                           name="value" class="form-control" value="1350000"
                                                           placeholder="مبلغ دوره به تومان" type="number" required>
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
                                                       type="text" required
                                                       placeholder="4562153">
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 "> شناسه اتاق عمومی <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="general"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="4181779">
                                            </div>

                                        </div>
                                    <div class="d-flex flex-row justify-content-between">
                                        <div class="row col-4">
                                            <div >
                                                <p class="mg-b-10 "> توضیحات بیشتر (قبول html)</p>
                                                <textarea class="form-control" name="meta"></textarea>
                                            </div>
                                        </div>
                                        <div class="mb-3 col-4 col-sm-6 col-md-4">
                                            <label for="gender" class="form-label">جنسیت</label>
                                            <select name="gender" class="form-select" required>
                                                <option value="" disabled selected></option>
                                                <option value="0">پسر</option>
                                                <option value="1">دختر</option>
=                                            </select>
                                        </div>

                                        <div class="mb-3 col-4 col-sm-6 col-md-4">
                                            <label for="course_type" class="form-label">نوع بغل دستی</label>
                                            <select name="course_type" id="course_type" class="form-select" required>
                                                <option value="" disabled selected>انتخاب دسته بغل دستی :</option>
                                                <option value="30"> آموزش تحصیلی</option>
                                                <option value="31">مهارت</option>
                                                <option value="32">المپیاد</option>
                                            </select>
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
                                                    <span class=""> فعال سازی دوره </span>
                                                </label>
                                            </div>


                                        </div>
                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save"
                                                    class="btn btn-primary  mr-1">
                                                تایید و ایجاد دوره
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
                                                <h6 class="main-content-label mb-1">ویرایش دوره </h6>
                                                <p class="text-muted card-sub-title">ویرایش دوره ای که از قبل ایجاد شده
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
                                                            کد دوره:
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
                                                            نام دوره:
                                                            <span
                                                                class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="coursename"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           value="<?php echo $result->coursename; ?>"
                                                           placeholder="دوره هوش پاییزی 1402">
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
                                        <div class="row">
                                            <div class="col-lg-4">
                                                <?php $pay = $result->paye;
                                                $pay = explode('/', $pay);

                                                ?>
                                                <p class="mg-b-10 mg-t-10"> انتخاب دسته بندی <span
                                                        class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="paye[]" multiple="multiple" required>
                                                    <!-- پایه‌های ابتدایی -->
                                                    <option value="اول" <?php if (in_array('اول', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه اول</option>
                                                    <option value="دوم" <?php if (in_array('دوم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه دوم</option>
                                                    <option value="سوم" <?php if (in_array('سوم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه سوم</option>
                                                    <option value="چهارم" <?php if (in_array('چهارم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه چهارم</option>

                                                    <!-- Existing options -->
                                                    <option value="پنجم" <?php if (in_array('پنجم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه پنجم</option>
                                                    <option value="ششم" <?php if (in_array('ششم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه ششم</option>
                                                    <option value="هفتم" <?php if (in_array('هفتم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه هفتم</option>
                                                    <option value="هشتم" <?php if (in_array('هشتم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه هشتم</option>
                                                    <option value="نهم" <?php if (in_array('نهم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه نهم</option>
                                                    <option value="دهم" <?php if (in_array('دهم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه دهم</option>
                                                    <option value="یازدهم" <?php if (in_array('یازدهم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه یازدهم</option>
                                                    <option value="دوازدهم" <?php if (in_array('دوازدهم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>پایه دوازدهم</option>

                                                    <!-- گروه‌ها -->
                                                    <option value="ابتدایی" <?php if (in_array('ابتدایی', $pay)) {
                                                        echo 'selected';
                                                    } ?>>گروه ابتدایی</option>
                                                    <option value="متوسطه_اول" <?php if (in_array('متوسطه_اول', $pay)) {
                                                        echo 'selected';
                                                    } ?>>متوسطه اول</option>
                                                    <option value="متوسطه_دوم" <?php if (in_array('متوسطه_دوم', $pay)) {
                                                        echo 'selected';
                                                    } ?>>متوسطه دوم</option>

                                                    <!-- رشته‌ها -->
                                                    <option value="ریاضی" <?php if (in_array('ریاضی', $pay)) {
                                                        echo 'selected';
                                                    } ?>>رشته ریاضی</option>
                                                    <option value="تجربی" <?php if (in_array('تجربی', $pay)) {
                                                        echo 'selected';
                                                    } ?>>رشته تجربی</option>
                                                    <option value="انسانی" <?php if (in_array('انسانی', $pay)) {
                                                        echo 'selected';
                                                    } ?>>رشته انسانی</option>
                                                    <option value="فنی" <?php if (in_array('فنی', $pay)) {
                                                        echo 'selected';
                                                    } ?>>فنی و حرفه ای</option>
                                                    <option value="کارودانش" <?php if (in_array('کارودانش', $pay)) {
                                                        echo 'selected';
                                                    } ?>>کار و دانش</option>

                                                    <!-- خانواده -->
                                                    <option value="خانواده" <?php if (in_array('خانواده', $pay)) {
                                                        echo 'selected';
                                                    } ?>>خانواده</option>
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
                                                <p class="mg-b-10">مبلغ دوره<span
                                                        class="tx-danger">*</span></p>


                                                <div class="input-group mb-3">
                                                    <input aria-describedby="basic-addon2" aria-label="شارژ حساب"
                                                           name="value" class="form-control"
                                                           value="<?php echo $result->coursefee; ?>"
                                                           placeholder="مبلغ دوره به تومان" type="number" required>
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
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 "> شناسه اتاق عمومی <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="general"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $result->link_general; ?>"
                                                       placeholder="4181779">
                                            </div>

                                        </div>
                                        <div class="row ">

                                            <div class="col-4">
                                                <p class="mg-b-10 "> توضیحات بیشتر (قبول html)</p>
                                                <textarea class="form-control"
                                                          name="meta"><?php echo $result->coursemeta; ?></textarea>
                                            </div>
                                            <div class="mb-3 col-12 col-sm-6 col-md-4">
                                                <label for="course_type" class="form-label">نوع دوره</label>
                                                <select name="course_type" id="course_type" class="form-select">
                                                    <option value="30" <?= ($result->course_type == 30) ? 'selected' : '' ?>>آموزشی</option>
                                                    <option value="31" <?= ($result->course_type == 31) ? 'selected' : '' ?>>مهارت</option>
                                                    <option value="32" <?= ($result->course_type == 32) ? 'selected' : '' ?>>المپیاد</option>
                                                </select>
                                            </div>
                                            <div class="mb-3 col-4 col-sm-6 col-md-4">
                                                <label for="gender" class="form-label">جنسیت</label>
                                                <select name="gender" class="form-select" required>
                                                    <option value="" disabled selected></option>
                                                    <option value="0" <?= ($result->gender == 0) ? 'selected' : '' ?>>پسر</option>
                                                    <option value="1" <?= ($result->gender == 1) ? 'selected' : '' ?>>دختر</option>
                                                    =                                            </select>
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
                                                    <span class=""> فعال سازی دوره </span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save-update"
                                                    class="btn btn-warning  mr-1">
                                                تایید و ویرایش دوره
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