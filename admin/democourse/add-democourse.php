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
        $sql = 'SELECT codecourse FROM course_demo WHERE codecourse= ? ';
        $query = $conn->prepare($sql);
        $query->execute([$_POST['code']]);
        if (!$query->rowCount() > 0) {

            $code = intval($_POST['code']);
            $paye = implode('/', $_POST['paye']);
            $addcourse = 'INSERT INTO course_demo (codecourse,coursename,coursemuted,paye,link_class) 
                VALUES (?,?,?,?,?)';
            $qinsert = $conn->prepare($addcourse);
            $qinsert->execute([$code, $_POST['coursename'], $_POST['muted'], $paye, $_POST['class']]);
            //

            $text = 'پایه ' . $_POST['coursename'] . ' با موفقیت ایجاد شد';
        } else {
            $erordublicate = 'این کد دوره فوق برنامه، متعلق به دوره فوق برنامه دیگری است';
        }
    } else {
        $erortoken = 'خطای غیر منتظره رخ داد، لطفا مجدد امتحان کنید';
    }
}
if (isset($_POST['save-update'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (CheckcodecourseForupdatDemo($_POST['code'], $_GET['edit'])) {

            $code = intval($_POST['code']);
            $paye = implode('/', $_POST['paye']);
            $update = 'UPDATE course_demo SET  codecourse=?,coursename=?,coursemuted=?,paye=?,link_class=? WHERE id = ?';
            $qup = $conn->prepare($update);
            $qup->execute([$code, $_POST['coursename'], $_POST['muted'], $paye, $_POST['class'], $_GET['id']]);
            //
            $text = 'پایه ' . $_POST['coursename'] . ' با موفقیت ویرایش شد';
            if (isset($_GET['id'])) {
                $stmt = $conn->prepare("SELECT * FROM course_demo WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } else {
            $erordublicate = 'این کد پایه، متعلق به پایه دیگری است';
        }
    } else {
        $erortoken = 'خطای غیر منتظره رخ داد، لطفا مجدد امتحان کنید';
    }
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
    <title> ثبت پایه جدید | <?php echo __TITLE ?></title>
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
                                                        افزودن دوره فوق برنامه جدید
                                                    </h6>

                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش می‌توانید یک دوره فوق برنامه یا موقت جدید ایجاد کنید و اطلاعاتی مانند نام پایه، توضیحات، مدت استفاده، وضعیت فعالیت و سایر تنظیمات موردنیاز را ثبت نمایید. این پایه برای دانش آموزان موقت و بدون فرایند ثبت‌نام قطعی استفاده می‌شود."
                                                          aria-label="راهنمای افزودن دوره فوق برنامه جدید">

                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>

                                                </div>

                                                <p class="text-muted card-sub-title">
                                                    ایجاد یک دوره فوق برنامه جدید
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
                                                            کد دوره فوق برنامه:
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
                                                            نام دوره فوق برنامه:
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

                                            <div class="col-lg-4 mt-3">
                                                <p class="mg-b-10"> شناسه اتاق اسکای روم <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="class"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="4562153">
                                            </div>

                                            <div class="mt-3 text-center">
                                                <button type="submit"
                                                        name="save"
                                                        class="btn btn-primary  mr-1">
                                                    تایید و ایجاد دوره فوق برنامه
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
                                                <h6 class="main-content-label mb-1"> ویرایش دوره فوق برنامه </h6>
                                                <p class="text-muted card-sub-title">ویرایش دوره فوق برنامه که از قبل ایجاد شده
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
                                    $sql = 'SELECT * FROM course_demo WHERE codecourse = ?';
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute([$_GET['edit']]);
                                    $result = $stmt->fetch(PDO::FETCH_OBJ);
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

                                            <div class="col-lg-4 mt-3">
                                                <p class="mg-b-10"> شناسه اتاق اسکای روم <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="class"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $result->link_class; ?>"
                                                       placeholder="http://skyroom.online/yourclass/12">
                                            </div>
                                        </div>

                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save-update"
                                                    class="btn btn-warning  mr-1">
                                                تایید و ویرایش دوره فوق برنامه
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
</div>
<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->


</body>

</html>