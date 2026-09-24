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

$sql = "SELECT * FROM dbschedules WHERE codecourse = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$_GET['code']]);
if ($stmt->rowCount() > 0) {
    $_SESSION['message'] = "برنامه هفتگی برای این پایه تعریف شده است";
    header('Location: Active-course.php');
    die();
}


$course = returnCourse($_GET['code']);

if (isset($_POST['add-schedule'])) {
    $agent = $_SERVER["HTTP_USER_AGENT"];
    $mor = NULL;
    if (preg_match('/MSIE (\d+\.\d+);/', $agent)) {
        $mor = 'image/jpeg';
    } else if (preg_match('/Chrome[\/\s](\d+\.\d+)/', $agent)) {
        $mor = 'image/jpeg';
    } else if (preg_match('/Edge\/\d+/', $agent)) {
        $mor = 'image/jpeg';
    } else if (preg_match('/OPR[\/\s](\d+\.\d+)/', $agent)) {
        $mor = 'image/jpeg';
    } else if (preg_match('/Safari[\/\s](\d+\.\d+)/', $agent)) {
        $mor = 'image/jpeg';
    }
    if ($_FILES['fileToUpload']['error']) {
        $_SESSION['message'] = " خطا در بارگزاری فایل";
        $code = $_GET['code'];
        header("Location: add-schedule.php?code=$code");
        die();
    }
    if (isset($_FILES['fileToUpload']) and $_FILES['fileToUpload']['type'] != $mor) {
        $_SESSION['message'] = " عملیات با شکست مواجه شد . فرمت فایل باید JPG باشد";
        $code = $_GET['code'];
        header("Location: add-schedule.php?code=$code");
        die();
    } elseif (isset($_FILES['fileToUpload']) and $_FILES['fileToUpload']['type'] == $mor) {
        $target_dir = realpath('../../assets/schedule/');
        $hashFile = bin2hex(random_bytes(8));
        $target_file = $target_dir . '\\' . basename($hashFile . $_FILES["fileToUpload"]["name"]);

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $target_file)) {
            $sql = "INSERT INTO dbschedules (codecourse, file_path) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_GET['code'], $target_file]);
            $_SESSION['message'] = "فایل موردنظر بارگزاری شد";
            $code = $_GET['code'];
            header("Location: Active-course.php");
            die();
        } else {
            $_SESSION['message'] = "خطا در انجام عملیات";
            $code = $_GET['code'];
            header("Location: add-schedule.php?code=$code");
            die();
        }

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
    <title> افزودن برنامه هفتگی |<?php echo __TITLE ?></title>
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
                        <?php if (isset($_SESSION['message'])) { ?>
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                <?= $_SESSION['message'] ?>
                            </div>
                            <?php unset($_SESSION['message']);
                        } ?>
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <h6 class="main-content-label mb-1">افزودن برنامه هفتگی </h6>
                                                <p class="text-muted card-sub-title">
                                                    پایه <?= $course->coursename ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <section class="section">
                                        <div class="row">
                                            <div class="card">
                                                <form method="post" data-parsley-validate=""
                                                    enctype="multipart/form-data">
                                                    <div class="row row-sm">
                                                        <div class="col-xl-6 mb-2">
                                                            <div class="input-group file-browser">


                                                                <input type="text"
                                                                    class="form-control border-left-0 browse-file"
                                                                    placeholder=" فایل برنامه خود را بارگذاری کنید*"
                                                                    readonly="">
                                                                <label class="input-group-btn">
                                                                    <span class="btn btn-primary">
                                                                        انتخاب فایل<input type="file"
                                                                            name="fileToUpload" style="display: none;"
                                                                            required>
                                                                    </span>
                                                                </label>
                                                            </div>

                                                            <div class="mt-3 text-center">
                                                                <button type="submit" name="add-schedule"
                                                                    class="btn btn-warning  mr-1">
                                                                    افزودن فایل برنامه هفتگی
                                                                </button>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</body>

</html>