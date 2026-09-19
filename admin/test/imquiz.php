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
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (isset($_POST['save'])) {

    if (isset($_SESSION['token1']) and $_SESSION['token1'] == $_POST['token']) {
//        $group = $_POST['group'];
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
            echo "<script>alert('  CSV فایل  مجاز برای  بارگذاری  ');</script>";
        } else {
            $dele = 'DELETE FROM import_quiz1';
            $act = $conn->prepare($dele);
            $act->execute();

            $target_dir = '../../assets/doc/';
            $hashFile = bin2hex(random_bytes(8));
            $target_file = $target_dir . basename($hashFile . $_FILES["fileToUpload"]["name"]);
            if (
                move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $target_file)) {
                $CSVfp = fopen($target_file, "r");
                if ($CSVfp !== FALSE) {
                    $id = 0;
                    while (!feof($CSVfp)) {
                        $data = fgetcsv($CSVfp, 1000000, ",");
                        if ($data) {
                            if ($id != 0) {
                                $sql = "INSERT INTO import_quiz1 (`username`,`duration`,`allquestion`,`trueresponse`,`falseresponse`,`unresponse`,`nomre`,`quizid`)
VALUES ('" . $data[0] . "', '" . $data[23] . "','" . $data[26] . "','" . $data[29] . "','" . $data[30] . "','" . $data[31] . "','" . $data[33] . "','" . $data[36] . "');\r\n\n";
                                $ex = $conn->exec($sql);
                            }
                            $id++;
                        }
                    }
                }
                fclose($CSVfp);
                unlink($target_file);

                $ok = 1;
            }else{
                echo 'notcopy';
            }
        }
    }else{
        echo "<script>alert('  token eror ');</script>";
    }
}
if (isset($ok) and $ok == 1) {
    $sql1 = 'SELECT * FROM import_quiz1 GROUP BY quizid';
    $qq = $conn->prepare($sql1);
    $qq->execute();
    $rey = $qq->fetchAll(PDO::FETCH_OBJ);
    foreach ($rey as $dell) {
        $de = 'DELETE FROM result_quiz WHERE quizid=?';
        $we = $conn->prepare($de);
        $we->execute([$dell->quizid]);
    }
    $de1 = 'DELETE FROM import_quiz2 ';
    $we1 = $conn->prepare($de1);
    $we1->execute();
    //
    $sql = 'SELECT * FROM import_quiz1';
    $query = $conn->prepare($sql);
    $query->execute();
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $ret) {
            $all = intval($ret->allquestion);
            $tr = explode(' (', $ret->trueresponse);
            $tr = intval($tr[0]);
            $fr = explode(' (', $ret->falseresponse);
            $fr = intval($fr[0]);
            $un = explode(' (', $ret->unresponse);
            $un = intval($un[0]);
            $nomre = explode('%', $ret->nomre);
            $nomre = intval($nomre[0]);
            $quizid = intval($ret->quizid);


            $ins = 'INSERT INTO import_quiz2(`username`,`duration`,`allquestion`,`trueresponse`,`falseresponse`,`unresponse`,`nomre`,`quizid`) VALUES (?,?,?,?,?,?,?,?)';
            $erp = $conn->prepare($ins);
            $erp->execute([$ret->username, $ret->duration, $all, $tr, $fr, $un, $nomre, $quizid]);
            //
            $ok = 2;
        }
        $dele = 'DELETE FROM import_quiz1';
        $act = $conn->prepare($dele);
        $act->execute();
    }

}
if (isset($ok) and $ok == 2) {
    $fdd = 'SELECT AVG(nomre) as avgnomre FROM import_quiz2  ';
    $afd = $conn->prepare($fdd);
    $afd->execute();
    $ravg = $afd->fetch(PDO::FETCH_OBJ);
    //
    $tqui = 'SELECT * FROM import_quiz2';
    $qq = $conn->prepare($tqui);
    $qq->execute();
    $datavalue = $qq->fetchAll(PDO::FETCH_OBJ);
    if ($qq->rowCount() > 0) {
        foreach ($datavalue as $res) {
            $student = ($res->nomre - $ravg->avgnomre) / 15;
            $taraz = (2000 * $student) + 10000;
            $update = 'UPDATE import_quiz2 SET taraz=? WHERE idimport=?';
            $qst = $conn->prepare($update);
            $qst->execute([$taraz, $res->idimport]);
            $ok = 3;
        }
    }
}
if (isset($ok) and $ok == 3) {
    $sql = 'SELECT MAX(taraz) as maxtaraz FROM import_quiz2 ';
    $query = $conn->prepare($sql);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_OBJ);

    $max = $result->maxtaraz;

    $uping = 'UPDATE import_quiz2 SET rotbe=? WHERE taraz=?';
    $quing = $conn->prepare($uping);
    $quing->execute([1, $max]);
    //
    $sdf = 'SELECT SUM(rotbe) as surotbe FROM import_quiz2 WHERE rotbe =?';
    $ert = $conn->prepare($sdf);
    $ert->execute([1]);
    $sumr = $ert->fetch(PDO::FETCH_OBJ);
    $sumr = $sumr->surotbe + 1;
    //
    $stm = $conn->prepare('SELECT * FROM import_quiz2 WHERE rotbe IS NULL');
    $stm->execute();
    while ($row = $stm->fetch(PDO::FETCH_OBJ)) {
        $sql = 'SELECT MAX(taraz) as maxtaraz FROM import_quiz2 WHERE taraz< ?';
        $query = $conn->prepare($sql);
        $query->execute([$max]);
        $result = $query->fetch(PDO::FETCH_OBJ);
        $max = $result->maxtaraz;
        $uping = 'UPDATE import_quiz2 SET rotbe=? WHERE taraz=?';
        $quing = $conn->prepare($uping);
        $quing->execute([$sumr, $max]);
        $sdf = 'SELECT COUNT(*) as surotbe FROM import_quiz2 WHERE rotbe =?';
        $ert = $conn->prepare($sdf);
        $ert->execute([$sumr]);
        $sumn = $ert->fetch(PDO::FETCH_OBJ);
        $sumr = $sumn->surotbe + $sumr;
        $ok = 4;
    }

}
if (isset($ok) and $ok == 4) {
    $sql = 'SELECT * FROM import_quiz2';
    $query = $conn->prepare($sql);
    $query->execute();
    $result = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($result as $rrt) {
            if (checkusercourse($rrt->quizid, $rrt->username)) {
                $ins = 'INSERT INTO result_quiz (quizid,username,allquestion,trueresponse,falseresponse,unresponse,duration,nomre,taraz,rotbe) VALUES (?,?,?,?,?,?,?,?,?,?)';
                $export = $conn->prepare($ins);
                $export->execute([$rrt->quizid, $rrt->username, $rrt->allquestion, $rrt->trueresponse, $rrt->falseresponse, $rrt->unresponse, $rrt->duration, $rrt->nomre, $rrt->taraz, $rrt->rotbe]);
                //
                $delr = 'DELETE FROM import_quiz2 WHERE idimport=? ';
                $deltr = $conn->prepare($delr);
                $deltr->execute([$rrt->idimport]);
                $ok = 5;
            }
        }

    }
}
if (isset($ok) and $ok == 5) {
    $sql = 'SELECT * FROM import_quiz2';
    $query = $conn->prepare($sql);
    $query->execute();
    $sgh = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        $erd = 1;
    }
}
if (isset($_POST['quiz'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
    $ids=intval($_POST['idquiz']);
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
                    $ip=GetIp();
                    $os=GetOs();
                    $insert='UPDATE dbquiz SET resultpdf=? WHERE idquiz=?';
                    $query=$conn->prepare($insert);
                    $query->execute([$url_targetfile,$ids]);
                    echo "<script>alert('پاسخنامه با موفقیت ثبت شد');</script>";
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
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> بارگذاری نتایج |<?php echo __TITLE ?></title>
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
                    <div class="col-lg-6">
                        <?php if (isset($ok) and $ok = 5) { ?>
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                فایل نتایج بارگذاری شد
                            </div>
                        <?php } ?>
                        <?php if (isset($erd)) { ?>
                            <div class="alert alert-danger" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                این کد ملی (ها) به دلیل عدم ثبت نام در پایه کارنامه برایشان صادر نشد
                                <br>
                                <?php
                                $is = 1;
                                foreach ($sgh as $ccd) {
                                    echo $ccd->username;
                                    echo '<br>';
                                    $is++;
                                }
                                ?>
                            </div>
                        <?php } ?>
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div>
                                            <h6 class="main-content-label mb-1">بارگذاری نتایج آزمون ها</h6>
                                            <p class="text-muted card-sub-title">از طریق افزودن فایل با فرمت CSV
                                            </p>
                                        </div>
                                    </div>


                                </div>
                                <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                    <?php
                                    $hash = bin2hex(random_bytes(8));
                                    $_SESSION['token1'] = $hash;
                                    ?>
                                    <div class="col-lg-12">
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">
                                        <div class="input-group file-browser">
                                            <input type="text"
                                                   class="form-control border-left-0 browse-file"
                                                   placeholder=" فایل نتایج خود را بارگذاری کنید*"
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
                                            فایل مطابق آموزش با فرمت csv را بارگذاری کنید
                                        </div>
                                    </div>
                                    <div class="text-center mg-10">
                                        <button type="submit" class="btn btn-success "
                                                name="save" onclick="return confirm('آیا این اقدام صورت پذیرد؟')"><i
                                                    class="fa fa-save"></i>
                                            ثبت و بارگذاری نتایج
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div>
                                            <h6 class="main-content-label mb-1">بارگذاری پاسخنامه آزمون</h6>
                                            <p class="text-muted card-sub-title">از طریق افزودن فایل با فرمت PDF
                                            </p>
                                        </div>
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
                                        <div class="col-lg-12">
                                            <p class="mg-b-10">شناسه آزمون</p>
                                            <div class="input-group mb-3">
                                                <input aria-describedby="basic-addon2"
                                                       aria-label=" شناسه آزمون"
                                                       name="idquiz" class="form-control"
                                                       placeholder="شناسه آزمون" type="number" required="">

                                            </div>
                                        </div>
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
        </div>
    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

</body>
</html>