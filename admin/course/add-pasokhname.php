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
if (!Permision('permision_viewtest')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['Id'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (isset($_POST['quiz'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $ids = intval($_GET['Id']);
        if (isset($_FILES['fileToUpload']) and $_FILES['fileToUpload']['name'] != '') {
            if (!file_exists("../../assets/attachments/" . jdate("Y", "", "", "", "en"))) {
                mkdir("../../assets/attachments/" . jdate("Y", "", "", "", "en"));
            }
            if (!file_exists("../../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en"))) {
                mkdir("../../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en"));
            }
            if (!file_exists("../../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en") . "/" . jdate("d", "", "", "", "en"))) {
                mkdir("../../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en") . "/" . jdate("d", "", "", "", "en"));
            }
            $target_dir = "../../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en") . "/" . jdate("d", "", "", "", "en") . "/";
            $hashFile = bin2hex(random_bytes(8));
            $target_file = $target_dir . basename($hashFile . $_FILES["fileToUpload"]["name"]);
            $uploadOk = 1;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if ($_FILES["fileToUpload"]["size"] > 10000000) {
                echo "<script>alert(' حداکثر سایز انتخابی 10 مگابایت است ');</script>";
                $uploadOk = 0;
            }
// Allow certain file formats
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
                && $imageFileType != "gif" && $imageFileType != "pdf" && $imageFileType != "docx"
                && $imageFileType != "xlsx" && $imageFileType != "rar" && $imageFileType != "doc"
                && $imageFileType != "txt" && $imageFileType != "pptx" && $imageFileType != "mp4") {
                echo "<script>alert('  فایل های مجاز برای بارگذاری xlsx,docx,PDF,GIF,JPG,PNG,rar,txt,doc,pptx,mp4 ');</script>";
                $uploadOk = 0;
            }
            if ($uploadOk == 0) {
                echo "<script>alert('به علت ایراد در فایل، پاسخنامه شما ثبت نشد ');</script>";
            } else {
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
                    $url_targetfile = url('assets/attachments/' . jdate("Y", "", "", "", "en") . '/' . jdate("m", "", "", "", "en") . '/' . jdate("d", "", "", "", "en") . '/' . basename($hashFile . $_FILES["fileToUpload"]["name"]));
                    date_default_timezone_set("Iran");
                    $datenow = date("Y-m-d");
                    $timenow = date('H:i:s');
                    $ip = GetIp();
                    $os = GetOs();
                    $insert = 'UPDATE dbquiz SET resultpdf=? WHERE idquiz=?';
                    $query = $conn->prepare($insert);
                    $query->execute([$url_targetfile, $ids]);


                    echo "<script>alert('پاسخنامه با موفقیت ثبت شد');</script>";
                    echo '<script>window.close() </script>';
                }
            }
        }

    }

}
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <?php $db = retDbquiz($_GET['Id']); ?>
    <title> افزودن پاسخنامه آزمون <?php echo $db->quizname ?> | <?php echo __TITLE ?></title>
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
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div>
                                        <h6 class="main-content-label mb-1">افزودن پاسخنامه : <span
                                                    class="text-primary"><?php echo $db->quizname ?></span>
                                        </h6>
                                        <p class="text-muted card-sub-title">کد پایه : <?php echo $db->codecourse ?>
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                    <?php
                                    $hash = bin2hex(random_bytes(8));
                                    $_SESSION['token'] = $hash;
                                    ?>
                                    <div class="col-lg-12">
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">

                                        <div class="input-group file-browser">
                                            <input type="text"
                                                   class="form-control border-left-0 browse-file"
                                                   placeholder=" فایل پاسخنامه خود را بارگذاری کنید*"
                                                   readonly="">
                                            <label class="input-group-btn">
														<span class="btn btn-primary">
															انتخاب فایل PDF<input type="file"
                                                                                  name="fileToUpload"
                                                                                  style="display: none;" required>
														</span>
                                            </label>
                                        </div>
                                        <div class="text-muted">
                                            فایل pdf را بارگذاری کنید
                                        </div>
                                    </div>
                                    <div class="text-center mg-10">
                                        <button type="submit" class="btn btn-success "
                                                name="quiz" onclick="return confirm('آیا این اقدام صورت پذیرد؟')"><i
                                                    class="fa fa-save"></i>
                                            ثبت و بارگذاری پاسخنامه
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <?php if($db->resultpdf==NULL){ ?>
                            <p class="text-center"> این آزمون فاقد پاسخنامه می باشد</p>
                            <?php } else { ?>
                                <p class="text-center">  برای این آزمون یک پاسخنامه ثبت شده است</p>
                            <a href="<?php echo $db->resultpdf ?> " target="_blank" class="btn btn-block  btn-info">دانلود پاسخنامه</a>

                            <?php } ?>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

</body>
</html>
