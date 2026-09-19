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
//* End Check Session JUST ONE Session

if (isset($_POST['submit'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['doc-name'];
        if (isset($_POST['meta'])) {
            $meta = $_POST['meta'];
        } else {
            $meta = NULL;
        }
        if (isset($_FILES['fileToUpload']) and $_FILES['fileToUpload']['name'] != '') {
            if (!file_exists("../assets/attachments/" . jdate("Y", "", "", "", "en"))) {
                mkdir("../assets/attachments/" . jdate("Y", "", "", "", "en"));
            }
            if (!file_exists("../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en"))) {
                mkdir("../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en"));
            }
            if (!file_exists("../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en") . "/" . jdate("d", "", "", "", "en"))) {
                mkdir("../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en") . "/" . jdate("d", "", "", "", "en"));
            }
            $target_dir = "../assets/attachments/" . jdate("Y", "", "", "", "en") . "/" . jdate("m", "", "", "", "en") . "/" . jdate("d", "", "", "", "en") . "/";
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
                echo "<script>alert('به علت ایراد در فایل، مدرک شما ارسال نشد');</script>";
            } else {
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
                    $url_targetfile = url('assets/attachments/' . jdate("Y", "", "", "", "en") . '/' . jdate("m", "", "", "", "en") . '/' . jdate("d", "", "", "", "en") . '/' . basename($hashFile . $_FILES["fileToUpload"]["name"]));
                    date_default_timezone_set("Iran");
                    $datenow = date("Y-m-d");
                    $timenow = date('H:i:s');
                    $ip=GetIp();
                    $os=GetOs();
                    $insert='INSERT INTO documention (username,namedoc,meta,url,datecreate,timecreate,ip,os) VALUES (?,?,?,?,?,?,?,?)';
                    $query=$conn->prepare($insert);
                    $query->execute([$_SESSION['auth']['user'],$name,$meta,$url_targetfile,$datenow,$timenow,$ip,$os]);
                    echo "<script>alert('مدرک شما با موفقیت به کارشناس ارجاع داده شد');</script>";
                }
            }
        }
    }
}
include_once '../temp.html';

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
    <title> ارسال مدارک و مستندات | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
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
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">ارسال مدارک و مستندات</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">اگر مدرک ارسالی مرتبط با دوره خاصی می باشد در قسمت توضیحات درج گردد</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-xl-6">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام مدرک:
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="doc-name"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-6 mg-t-15">
                                        <div class="row ">

                                            <p class="mg-b-10 "> توضیحات درخصوص مدرک</p>
                                            <textarea class="form-control" name="meta"></textarea>

                                        </div>
                                    </div>
                                    <div class="row ">
                                        <div class="col-xl-6 mg-t-15">

                                            <div class="input-group file-browser">
                                                <input type="text"
                                                       class="form-control border-left-0 browse-file"
                                                       placeholder=" فایل خود را بارگزاری نمایید"
                                                       readonly="">
                                                <label class="input-group-btn">
														<span class="btn btn-primary">
															انتخاب فایل <input type="file"
                                                                               name="fileToUpload" required
                                                                               style="display: none;">
														</span>
                                                </label>
                                            </div>
                                            <div class="text-muted">
                                                فایل های مورد پذیرش rar-pdf-jpg-png-gif-xlsx-pptx
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $hash = bin2hex(random_bytes(32));
                                    $_SESSION['token'] = $hash;
                                    ?>
                                    <input class="form-control" name="token" type="hidden"
                                           value="<?php echo $hash ?>">
                                    <div class="mt-3 text-center">
                                        <button type="submit" name="submit"
                                                class="btn btn-primary  mr-1">
                                            تایید و ارسال مدرک به کارشناس
                                        </button>

                                    </div>


                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>


    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
