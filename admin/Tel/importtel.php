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
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_addcall')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['add-number'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $group = $_POST['group'];
        $agent = $_SERVER["HTTP_USER_AGENT"];
        $mor = NULL;
        if (preg_match('/MSIE (\d+\.\d+);/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Chrome[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Edge\/\d+/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Firefox[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'application/vnd.ms-excel';
        } else if (preg_match('/OPR[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Safari[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        }
        if ($_FILES['fileToUpload']['type'] != $mor) {
            echo "<script>alert('   فایل  مجاز برای  بارگذاری CSV ');</script>";

        } else {

            if (isset($_POST['delete-old'])) {
                $dele = 'DELETE FROM tel_db WHERE `group`=?';
                $act = $conn->prepare($dele);
                $act->execute([$group]);

            }
            $target_dir='../../assets/doc/';
            $hashFile = bin2hex(random_bytes(8));
            $target_file = $target_dir . basename($hashFile . $_FILES["fileToUpload"]["name"]);

            if (

                move_uploaded_file($_FILES['fileToUpload']['tmp_name'],$target_file ) ) {
                $CSVfp = fopen($target_file, "r");
                if ($CSVfp !== FALSE) {
                    while (!feof($CSVfp)) {
                        $data = fgetcsv($CSVfp, 1000000, ",");

                        if ($data) {


                            $sql = "INSERT INTO tel_db (`group`,`fname`,`lname`,`guidance`,`state`,`city`,`mob1`,`mob2`,`mob3`,`tel1`,`tel2`,`tel3`,`dakheli`)
VALUES ('" . $group . " ','" . $data[0] . "', '" . $data[1] . "','" . $data[2] . "','" . $data[3] . "','" . $data[4] . "','" . $data[5] . "','" . $data[6] . "','" . $data[7] . "','" . $data[8] . "','" . $data[9] . "','" . $data[10] . "','" . $data[11] . "');\r\n\n";
                            $ex = $conn->exec($sql);

                        }
                    }
                }
                fclose($CSVfp);
                unlink($target_file);
                $ok=1;

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
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> افزودن فایل به تلفن|<?php echo __TITLE ?></title>
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
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-9">
                                        <div>
                                            <h6 class="main-content-label mb-1">افزودن فایل شماره تماس</h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php if (isset($ok) and $ok == 1) { ?>
                                    <div class="alert alert-success" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        فایل جدید با موفقیت اضافه شد

                                    </div>
                                <?php } ?>

                                <hr>
                                <p class="header-message">
                                    لطفا فایل شماره تماس های خود را با فرمت csv مطابق فایل نمونه بارگذاری نمایید
                                </p>
                                <p>
                                    <a href="<?php echo url('assets/doc/test.xlsx') ?>" class="btn btn-success ripple"> دانلود فایل نمونه</a>
                                    <a href="<?php echo url('assets/doc/s2.pdf') ?>" class="btn btn-info ripple"> دانلود فایل راهنما</a>
                                </p>
                                <hr>

                                <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                    <div class="card-body border ">
                                        <div class="row">
                                            <div class="col-xl-6">

                                                <div class="input-group file-browser">


                                                    <input type="text"
                                                           class="form-control border-left-0 browse-file"
                                                           placeholder=" فایل شماره های خود را بارگذاری کنید*"
                                                           readonly="">
                                                    <label class="input-group-btn">
														<span class="btn btn-primary">
															انتخاب فایل CSV<input type="file"
                                                                                  name="fileToUpload"
                                                                                  style="display: none;" required>
														</span>
                                                    </label>
                                                </div>
                                                <div class="text-muted">
                                                    حداکثر سایز مجاز 2 مگابایت می باشد
                                                </div>


                                            </div>
                                            <div class="col-xl-6">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        انتخاب گروه مخاطبان:<span
                                                                class="tx-danger">*</span>
                                                    </div>
                                                    <select class="form-control select2 "
                                                            name="group"
                                                            aria-describedby="basic-addon2" required>
                                                        <option label="گروه ">
                                                        </option>
                                                        <?php
                                                        $sql = 'SELECT * FROM tel_group ';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute();
                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                        if ($query->rowCount() > 0) {
                                                            foreach ($result as $res) {


                                                                ?>
                                                                <option value="<?php echo $res->id ?>"><?php echo $res->namegroup ?></option>
                                                            <?php }
                                                        }
                                                        ?>

                                                    </select>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <p class="pd-t-20">
                                            <label class="custom-switch mg-t-20">
                                                <input type="checkbox" name="delete-old" class="custom-switch-input">
                                                <span class="custom-switch-indicator"></span>
                                                <span class="custom-switch-description">شماره های قبلی گروه انتخابی را پاک و این لیست را جایگزین نمایید</span>
                                            </label>
                                        </p>
                                        <div class="mt-3 text-center">
                                            <button type="submit" name="add-number"
                                                    class="btn btn-warning  mr-1">
                                                افزودن فایل شماره تماس
                                            </button>

                                        </div>


                                    </div>


                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

