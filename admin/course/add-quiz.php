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
// if (!Permision('permision_viewtest')) {
//     header('location: ' . url('admin/index.php'));
//     die();
// }
if (!isset($_GET['id'])) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!checkvalidcourse(intval($_GET['id']))) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['addquiz'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (checkDubDars($_POST['dars'], $_POST['tasalsol'], $_GET['id'])) {

            $code = intval($_GET['id']);
            $namequiz = trim($_POST['name-quiz']);
            $datestart = $_POST['datestart'];
            $dars = $_POST['dars'];
            $tasalsol = $_POST['tasalsol'];
            $datestart = explode('/', $datestart);
            $datestart = jalali_to_gregorian($datestart[0], $datestart[1], $datestart[2]);
            $datestart = implode('-', $datestart);
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
                if ($_POST['dars'] != 21) {
                    $dele = 'TRUNCATE import_quiz1';
                    $act = $conn->prepare($dele);
                    $act->execute();


                    $insql = 'INSERT INTO `dbquiz`(codecourse,dars,tasalsol,quizname,datestart) VALUES (?,?,?,?,?)';
                    $qss = $conn->prepare($insql);
                    $qss->execute([$code, $dars, $tasalsol, $namequiz, $datestart]);
                    $lastId = $conn->lastInsertId();
                    //            $text = 'پایه با شناسه <strong>' . $lastId . '</strong> ایجاد شد';


                    $target_dir = '../../assets/doc/';
                    $hashFile = bin2hex(random_bytes(8));
                    $target_file = $target_dir . basename($hashFile . $_FILES["fileToUpload"]["name"]);
                    if (
                        move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $target_file)
                    ) {
                        $CSVfp = fopen($target_file, "r");
                        if ($CSVfp !== FALSE) {
                            $id = 0;
                            while (!feof($CSVfp)) {
                                $data = fgetcsv($CSVfp, 1000000, ",");
                                if ($data) {
                                    if ($id != 0 and $id != 1) {

                                        $sql = "INSERT INTO import_quiz1 (`username`,`duration`,`allquestion`,`trueresponse`,`falseresponse`,`unresponse`,`nomre`,`quizid`)
VALUES ('" . $data[0] . "', '" . $data[23] . "','" . $data[26] . "','" . $data[29] . "','" . $data[30] . "','" . $data[31] . "','" . $data[33] . "','" . $lastId . "');\r\n\n";
                                        $ex = $conn->exec($sql);
                                    } elseif ($id == 0) {
                                        $sql = "UPDATE dbquiz set tid = ? WHERE idquiz = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute([$data[7], $lastId]);
                                    }
                                    $id++;
                                }
                            }
                        }
                        fclose($CSVfp);
                        unlink($target_file);

                        $ok = 1;
                    } else {
                        echo 'notcopy';
                    }
                } else {
                    $dele = 'TRUNCATE import_quiz1_jame';
                    $act = $conn->prepare($dele);
                    $act->execute();

                    $insql = 'INSERT INTO `dbquiz`(codecourse,dars,tasalsol,quizname,datestart) VALUES (?,?,?,?,?)';
                    $qss = $conn->prepare($insql);
                    $qss->execute([$code, 21, $tasalsol, $namequiz, $datestart]);
                    $lastId = $conn->lastInsertId();

                    $target_dir = '../../assets/doc/';
                    $hashFile = bin2hex(random_bytes(8));
                    $target_file = $target_dir . basename($hashFile . $_FILES["fileToUpload"]["name"]);

                    if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $target_file)) {
                        $fp = fopen($target_file, "r");
                        if ($fp !== false) {
                            $first = fgetcsv($fp, 1000000);
                            $header = fgetcsv($fp, 1000000);
                        }
                        $CSVfp = fopen($target_file, "r");
                        if ($CSVfp !== FALSE) {
                            $id = 0;
                            while (!feof($CSVfp)) {
                                $data = fgetcsv($CSVfp, 1000000, ",");
                                if ($data) {
                                    if ($id != 0 and $id != 1) {
                                        $lessons = [];
                                        $start = 32;
                                        $colsPerLesson = 8;
                                        while (isset($data[$start]) && $header[$start] == 'نام درس') {
                                            $lessons[] = [
                                                'lesson_name' => $data[$start],
                                                'lesson_coe' => $data[$start + 1],
                                                'total_questions' => $data[$start + 2],
                                                'true_answers' => $data[$start + 3],
                                                'false_answers' => $data[$start + 4],
                                                'blank_answers' => $data[$start + 5],
                                                'percent' => $data[$start + 6],
                                                'taraz' => $data[$start + 7]
                                            ];
                                            $start += $colsPerLesson;
                                        }
                                        $nomre = $data[$start];
                                        $sql = "INSERT INTO import_quiz1_jame (`username`,`duration`,`allquestion`,`true_response`,`false_response`,`unresponse`,`nomre`,`quiz_id`,`results_json`)
VALUES ('" . $data[0] . "', '" . $data[23] . "','" . $data[26] . "','" . $data[29] . "','" . $data[30] . "','" . $data[31] . "','" . $nomre . "','" . $lastId . "','" . json_encode($lessons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "');\r\n\n";
                                        $ex = $conn->exec($sql);
                                    } elseif ($id == 0) {
                                        $sql = "UPDATE dbquiz set tid = ? WHERE idquiz = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute([$data[7], $lastId]);
                                    }
                                    $id++;
                                }
                            }
                        }
                        fclose($CSVfp);
                        unlink($target_file);

                        $ok = 1;
                    } else {
                        echo 'notcopy';
                    }
                }
            }
        } else {
            $erortoken = 'این آزمون از قبل ایجاد شده است';
        }
    } else {
        $erortoken = 'خطای غیر منتظره رخ داد ! ';
    }
}
if (isset($ok) and $ok == 1) {
    if ($_POST['dars'] != 21) {
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
    } else {
        $sql1 = 'SELECT * FROM import_quiz1_jame GROUP BY quiz_id';
        $qq = $conn->prepare($sql1);
        $qq->execute();
        $rey = $qq->fetchAll(PDO::FETCH_OBJ);
        foreach ($rey as $dell) {
            $de = 'DELETE FROM result_quizzes_jame WHERE quiz_id=?';
            $we = $conn->prepare($de);
            $we->execute([$dell->quiz_id]);
        }
        $de1 = 'DELETE FROM import_quiz2_jame ';
        $we1 = $conn->prepare($de1);
        $we1->execute();
        //
        $sql = 'SELECT * FROM import_quiz1_jame';
        $query = $conn->prepare($sql);
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            foreach ($result as $ret) {
                $all = intval($ret->allquestion);
                $tr = explode(' (', $ret->true_response);
                $tr = intval($tr[0]);
                $fr = explode(' (', $ret->false_response);
                $fr = intval($fr[0]);
                $un = explode(' (', $ret->unresponse);
                $un = intval($un[0]);
                $nomre = explode('%', $ret->nomre);
                $nomre = intval($nomre[0]);
                $quizid = intval($ret->quiz_id);


                $ins = 'INSERT INTO import_quiz2_jame(`username`,`duration`,`allquestion`,`true_response`,`false_response`,`unresponse`,`nomre`,`quiz_id`,`results_json`) VALUES (?,?,?,?,?,?,?,?,?)';
                $erp = $conn->prepare($ins);
                $erp->execute([$ret->username, $ret->duration, $all, $tr, $fr, $un, $nomre, $quizid, $ret->results_json]);
                //
                $ok = 2;
            }
            $dele = 'DELETE FROM import_quiz1_jame';
            $act = $conn->prepare($dele);
            $act->execute();
        }
    }
}
if (isset($ok) and $ok == 2) {
    if ($_POST['dars'] != 21) {
        $fdd = 'SELECT AVG(nomre) as avgnomre FROM import_quiz2';
        $afd = $conn->prepare($fdd);
        $afd->execute();
        $ravg = $afd->fetch(PDO::FETCH_OBJ);
        //
        $n_sql = 'SELECT COUNT(*) FROM import_quiz2';
        $n_stmt = $conn->prepare($n_sql);
        $n_stmt->execute();
        $n = $n_stmt->fetchColumn();
        //
        $sigmaX2_sql = 'SELECT SUM(nomre * nomre) AS sigma FROM import_quiz2';
        $sigmaX2_stmt = $conn->prepare($sigmaX2_sql);
        $sigmaX2_stmt->execute();
        $sigmaX2 = $sigmaX2_stmt->fetchColumn();
        //
        $xBar2 = $ravg->avgnomre * $ravg->avgnomre;
        //
        $s = sqrt(($sigmaX2 / $n) - $xBar2);
        //
        $tqui = 'SELECT * FROM import_quiz2';
        $qq = $conn->prepare($tqui);
        $qq->execute();
        $datavalue = $qq->fetchAll(PDO::FETCH_OBJ);
        if ($qq->rowCount() > 0) {
            foreach ($datavalue as $res) {
                if ($_POST['dars'] == 7) {
                    $taraz = $res->nomre * 100;
                } else {
                    $z = ($res->nomre - $ravg->avgnomre) / $s;
                    $taraz = (1000 * $z) + 5000;
                }
                $update = 'UPDATE import_quiz2 SET taraz=? WHERE idimport=?';
                $qst = $conn->prepare($update);
                $qst->execute([$taraz, $res->idimport]);
                $ok = 3;
            }
        }
    } else {
        $sql = 'SELECT id_import, results_json FROM import_quiz2_jame';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        foreach ($results as $result) {

            $lesson = json_decode($result->results_json, true);
            $totalTaraz = 0;
            $totalCoef = 0;
            foreach ($lessons as $lesson) {

                if (!isset($lesson['taraz'])) {
                    continue; // اگر تراز نداشت رد شود
                }

                $taraz = floatval($lesson['taraz']);
                $coef = floatval($lesson['lesson_coe']);

                $totalTaraz += $taraz * $coef;
                $totalCoef += $coef;
            }

            $finalTaraz = 0;

            if ($totalCoef > 0) {
                $finalTaraz = $totalTaraz / $totalCoef;
            }

            $sqll = 'UPDATE import_quiz2_jame SET taraz = ? WHERE id_import = ?';
            $stmtt = $conn->prepare($sqll);
            $stmtt->execute([$finalTaraz, $result->id_import]);
        }
        $ok = 3;
        // $fdd = 'SELECT AVG(nomre) as avgnomre FROM import_quiz2_jame';
        // $afd = $conn->prepare($fdd);
        // $afd->execute();
        // $ravg = $afd->fetch(PDO::FETCH_OBJ);
        // //
        // $tqui = 'SELECT * FROM import_quiz2_jame';
        // $qq = $conn->prepare($tqui);
        // $qq->execute();
        // $datavalue = $qq->fetchAll(PDO::FETCH_OBJ);
        // if ($qq->rowCount() > 0) {
        //     foreach ($datavalue as $res) {
        //         $student = ($res->nomre - $ravg->avgnomre) / 15;
        //         $taraz = (2000 * $student) + 10000;
        //         $update = 'UPDATE import_quiz2_jame SET taraz=? WHERE id_import=?';
        //         $qst = $conn->prepare($update);
        //         $qst->execute([$taraz, $res->id_import]);
        //         $ok = 3;
        //     }
        // }
    }
}
if (isset($ok) and $ok == 3) {
    if ($_POST['dars'] != 21) {
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
    } else {
        $sql = 'SELECT MAX(taraz) as maxtaraz FROM import_quiz2_jame ';
        $query = $conn->prepare($sql);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_OBJ);

        $max = $result->maxtaraz;

        $uping = 'UPDATE import_quiz2_jame SET rotbe=? WHERE taraz=?';
        $quing = $conn->prepare($uping);
        $quing->execute([1, $max]);
        //
        $sdf = 'SELECT SUM(rotbe) as surotbe FROM import_quiz2_jame WHERE rotbe =?';
        $ert = $conn->prepare($sdf);
        $ert->execute([1]);
        $sumr = $ert->fetch(PDO::FETCH_OBJ);
        $sumr = $sumr->surotbe + 1;
        //
        $stm = $conn->prepare('SELECT * FROM import_quiz2_jame WHERE rotbe IS NULL');
        $stm->execute();
        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {
            $sql = 'SELECT MAX(taraz) as maxtaraz FROM import_quiz2_jame WHERE taraz< ?';
            $query = $conn->prepare($sql);
            $query->execute([$max]);
            $result = $query->fetch(PDO::FETCH_OBJ);
            $max = $result->maxtaraz;
            $uping = 'UPDATE import_quiz2_jame SET rotbe=? WHERE taraz=?';
            $quing = $conn->prepare($uping);
            $quing->execute([$sumr, $max]);
            $sdf = 'SELECT COUNT(*) as surotbe FROM import_quiz2_jame WHERE rotbe =?';
            $ert = $conn->prepare($sdf);
            $ert->execute([$sumr]);
            $sumn = $ert->fetch(PDO::FETCH_OBJ);
            $sumr = $sumn->surotbe + $sumr;
            $ok = 4;
        }
    }
}
if (isset($ok) and $ok == 4) {
    if ($_POST['dars'] != 21) {
        $sql = 'SELECT * FROM import_quiz2';
        $query = $conn->prepare($sql);
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            foreach ($result as $rrt) {
                // if (checkusercourse($rrt->quizid, $rrt->username)) {
                $ins = 'INSERT INTO result_quiz (quizid,username,allquestion,trueresponse,falseresponse,unresponse,duration,nomre,taraz,rotbe) VALUES (?,?,?,?,?,?,?,?,?,?)';
                $export = $conn->prepare($ins);
                if (strlen($rrt->username) == 9) {
                    $rrt->username = '0' . $rrt->username;
                } elseif (strlen($rrt->username) == 8) {
                    $rrt->username = '00' . $rrt->username;
                }
                $export->execute([$rrt->quizid, $rrt->username, $rrt->allquestion, $rrt->trueresponse, $rrt->falseresponse, $rrt->unresponse, $rrt->duration, $rrt->nomre, $rrt->taraz, $rrt->rotbe]);
                //
                $delr = 'DELETE FROM import_quiz2 WHERE idimport=? ';
                $deltr = $conn->prepare($delr);
                $deltr->execute([$rrt->idimport]);
                $ok = 5;
                // }
            }
        }
    } else {
        $sql = 'SELECT * FROM import_quiz2_jame';
        $query = $conn->prepare($sql);
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            foreach ($result as $rrt) {
                // if (checkusercourse($rrt->quiz_id, $rrt->username)) {
                $ins = 'INSERT INTO result_quizzes_jame (quiz_id,username,allquestion,true_response,false_response,unresponse,duration,nomre,taraz,rotbe,results_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
                $export = $conn->prepare($ins);
                if (strlen($rrt->username) == 9) {
                    $rrt->username = '0' . $rrt->username;
                } elseif (strlen($rrt->username) == 8) {
                    $rrt->username = '00' . $rrt->username;
                }
                $export->execute([$rrt->quiz_id, $rrt->username, $rrt->allquestion, $rrt->true_response, $rrt->false_response, $rrt->unresponse, $rrt->duration, $rrt->nomre, $rrt->taraz, $rrt->rotbe, $rrt->results_json]);
                //
                $delr = 'DELETE FROM import_quiz2_jame WHERE id_import=? ';
                $deltr = $conn->prepare($delr);
                $deltr->execute([$rrt->id_import]);
                $ok = 5;
                // }
            }
        }
    }
    $ok = 5;
}
if (isset($ok) and $ok == 5) {
    if ($_POST['dars'] != 21) {
        $sql = 'SELECT * FROM import_quiz2';
        $query = $conn->prepare($sql);
        $query->execute();
        $sgh = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            $erd = 1;
        }
    } else {
        $sql = 'SELECT * FROM import_quiz2_jame';
        $query = $conn->prepare($sql);
        $query->execute();
        $sgh = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            $erd = 1;
        }
    }
}


if (isset($_GET['delete'])) {
    $del = intval($_GET['delete']);
    $sql = 'DELETE FROM dbquiz WHERE idquiz=?';
    $query = $conn->prepare($sql);
    $query->execute([$del]);
    header('location:add-quiz.php?id=' . $_GET['id'] . ' ');
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> افزودن آزمون | <?php echo __TITLE ?></title>
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

        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="row row-sm">
                        <?php if (isset($ok) and $ok = 5) { ?>
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                فایل نتایج بارگذاری شد
                            </div>
                        <?php } ?>
                        <?php if (isset($erd)) { ?>
                            <div class="alert alert-danger" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                این کد ملی (ها) به دلیل عدم ثبت نام در پایه کارنامه برایشان صادر نشد
                                <br>
                                <?php
                                $is = 1;
                                foreach ($sgh as $ccd) {
                                    echo $is . '-' . $ccd->username;
                                    echo '<br>';
                                    $is++;
                                }
                                ?>
                            </div>
                        <?php } ?> <?php $course = returnCourse($_GET['id']); ?>
                        <div class="col-lg-3">
                            <?php if (Permision('permision_viewtest')) { ?>
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row">

                                            <div class="col-xl-12">
                                                <div>
                                                    <h6 class="main-content-label mb-1">افزودن آزمون </h6>
                                                    <p class="text-muted card-sub-title"><?php echo $course->coursename ?>
                                                    </p>
                                                </div>
                                            </div>

                                        </div>
                                        <?php if (isset($text)) { ?>
                                            <div class="alert alert-success" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                <?php echo $text ?>
                                            </div>
                                        <?php } ?>
                                        <?php if (isset($erordublicate)) { ?>
                                            <div class="alert alert-danger" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                <?php echo $erordublicate ?>
                                            </div>
                                        <?php } ?>
                                        <?php if (isset($erortoken)) { ?>
                                            <div class="alert alert-warning" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                <?php echo $erortoken ?>
                                            </div>
                                        <?php } ?>
                                        <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                            <div class="row">
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">نام آزمون:<span class="tx-danger">*</span></p>
                                                    <input class="form-control " placeholder="نام آزمون را وارد کنید"
                                                        type="text" name="name-quiz" required>
                                                </div>

                                                <div class="col-lg-12 mg-t-20">
                                                    <input class="form-control" name="code_course" type="hidden"
                                                        value="<?= $_GET['id'] ?>">
                                                </div>

                                                <div class="col-lg-12">
                                                    <p class=" mg-t-20"> انتخاب درس: <span class="tx-danger">*</span></p>
                                                    <select class="form-control select2" name="dars" required>
                                                        <option label="درس">
                                                        </option>
                                                        <?php
                                                        $wtr = 'SELECT * FROM dbdars';
                                                        $qer = $conn->prepare($wtr);
                                                        $qer->execute();
                                                        $retu = $qer->fetchAll(PDO::FETCH_OBJ);
                                                        foreach ($retu as $rep) {


                                                        ?>
                                                            <option value="<?php echo $rep->id ?>"><?php echo $rep->dars ?>
                                                            </option>
                                                        <?php
                                                        }
                                                        ?>


                                                    </select>
                                                </div>
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">توالی<span class="tx-danger">*</span></p>
                                                    <input class="form-control " placeholder="شماره توالی را وارد کنید"
                                                        type="number" name="tasalsol" required>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                    value="<?php echo $hash ?>">
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">تاریخ برگزاری آزمون<span class="tx-danger">*</span>
                                                    </p>
                                                    <div class="input-group ">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                <i class="fe fe-calendar lh--9 op-6 "></i>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        $datenow = date("Y-m-d");
                                                        $datenow = explode('-', $datenow);
                                                        $datenow = gregorian_to_jalali($datenow[0], $datenow[1], $datenow[2]);
                                                        $datenow = implode('/', $datenow);
                                                        ?>
                                                        <input class="form-control " placeholder="1404/02/20"
                                                            value="<?php echo $datenow ?>" type="text" name="datestart"
                                                            required>
                                                    </div>
                                                </div>
                                                <div class="input-group file-browser mt-3">
                                                    <input type="text" class="form-control border-left-0 browse-file"
                                                        placeholder=" فایل آزمون را بارگذاری کنید*" readonly="">
                                                    <label class="input-group-btn">
                                                        <span class="btn btn-primary">
                                                            انتخاب<input type="file" name="fileToUpload"
                                                                style="display: none;" required>
                                                        </span>
                                                    </label>
                                                </div>


                                            </div>
                                            <div class="row">
                                                <div class="text-center mg-t-30">
                                                    <button class="btn ripple  btn-success pd-x-20" type="submit"
                                                        name="addquiz">ثبت آزمون و صدورکارنامه
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-lg-9">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-xl-12">
                                        <div>
                                            <h6 class="main-content-label mb-1">لیست آزمون های برگزار شده</h6>
                                            <p class="text-primary card-sub-title"><?php echo $course->coursename ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="table table-responsive">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                                <tr>
                                                    <th>ردیف</th>
                                                    <th>پاسخنامه</th>
                                                    <th>نام آزمون</th>
                                                    <th>درس</th>
                                                    <th>توالی</th>
                                                    <th>حاضرین</th>
                                                    <th>غائبین</th>
                                                    <th>معدل</th>
                                                    <th>بالاترین تراز</th>
                                                    <th>پایین تراز</th>
                                                    <th>تاریخ برگزاری</th>
                                                    <?php if (Permision('permision_viewtest')) { ?><th>حذف</th> <?php } ?>

                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sql = 'SELECT * FROM dbquiz WHERE codecourse=? ORDER BY datestart DESC ';
                                                $query = $conn->prepare($sql);
                                                $query->execute([$_GET['id']]);
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    $rt = 1;
                                                    foreach ($result as $plr) {
                                                ?>

                                                        <tr>
                                                            <td class="wd-15"><?php echo $rt ?></td>
                                                            <td class="wd-15">
                                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                                    onclick="window.open('add-pasokhname.php?Id='+'<?php echo $plr->idquiz ?>','افزودن پاسخنامه'
                                                                     ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                    <i class="fe fe-upload"></i>
                                                                </div>
                                                            </td>

                                                            <td><?php echo $plr->quizname ?></td>
                                                            <?php
                                                            $dars = getdars($plr->dars);
                                                            ?>

                                                            <td class="wd-15 <?= $plr->dars == 21 ? 'bg-light' : '' ?>">
                                                                <?php echo $dars ?>
                                                            </td>
                                                            <td class="wd-15"> <?php echo $plr->tasalsol ?></td>
                                                            <td class="wd-15 bg-success"
                                                                onclick="window.open('list-result.php?Id='+'<?php echo $plr->idquiz ?>','مشاهده حاضرین'
                                                            ,'width=1000,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                <?php echo countresultquiz($plr->idquiz) ?>
                                                            </td>
                                                            <td class="wd-15 bg-danger"
                                                                onclick="window.open('absent.php?Id='+'<?php echo $plr->idquiz ?>','مشاهده غائبین'
                                                                ,'width=1000,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                <?php echo absentuser($plr->idquiz) ?>
                                                            </td>
                                                            <td class="wd-15"><?php echo round(avequiz($plr->idquiz), 2) ?></td>
                                                            <td class="wd-15">
                                                                <?= $plr->dars == 21 ? number_format(maxtarazJame($plr->idquiz)) : number_format(maxtaraz($plr->idquiz)) ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?= $plr->dars == 21 ? number_format(mintarazJame($plr->idquiz)) : number_format(mintaraz($plr->idquiz)) ?>
                                                            </td>
                                                            <td class="wd-10"><?php
                                                                                $dateno = $plr->datestart;
                                                                                $dateno = explode('-', $dateno);
                                                                                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                                                $dateno = implode('/', $dateno);
                                                                                echo $dateno;
                                                                                ?></td>
                                                            <?php if (Permision('permision_viewtest')) { ?>
                                                                <td class="wd-15">
                                                                    <a href="?id=<?php echo $_GET['id'] ?>&delete=<?php echo $plr->idquiz ?>"
                                                                        onclick="return confirm('آیا با حذف این آزمون  موافقید؟ این اقدام غیر قابل بازگشت است!')"
                                                                        class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                            class="fe fe-x"></i></a>

                                                                </td>
                                                            <?php } ?>
                                                        </tr>
                                                <?php
                                                        $rt++;
                                                    }
                                                } ?>
                                            </tbody>
                                        </table>
                                    </div>

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