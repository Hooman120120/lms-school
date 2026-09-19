<?php
include_once '../dbconfig.php';
include_once '../quizconfig.php';
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
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    die();
}
if (!isset($_GET['quiz']) and $_GET['quiz'] = '') {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
$quiz = retDbquiz($_GET['quiz']);
$sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND `codtbl_course`.`codecourse`=?';
$query = $conn->prepare($sql);
$query->execute([1, 1, $_SESSION['auth']['user'], $quiz->codecourse]);
if (!$query->rowCount() > 0) {
    header('location: ' . url('Course/allcourse.php'));
    die();
} else {
    $course = $query->fetch(PDO::FETCH_OBJ);
}
if ($quiz->dars != 21) {
    $requiz = 'SELECT * FROM result_quiz WHERE quizid=? AND username=?';
    $qres = $conn->prepare($requiz);
    $qres->execute([$_GET['quiz'], $_SESSION['auth']['user']]);
    $req = $qres->fetch(PDO::FETCH_OBJ);
} else {
    $requiz = 'SELECT * FROM result_quizzes_jame WHERE quiz_id=? AND username=?';
    $qres = $conn->prepare($requiz);
    $qres->execute([$_GET['quiz'], $_SESSION['auth']['user']]);
    $req = $qres->fetch(PDO::FETCH_OBJ);
}

//
if ($qres->rowCount() == 0) {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
$sql = 'SELECT class_id FROM dbcourse WHERE codecourse = ? AND username = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$quiz->codecourse, $_SESSION['auth']['user']]);
$classId = $stmt->fetchColumn();

$isJame = ($quiz->dars == 21);

$classResults = getClassQuizResults(
    $_GET['quiz'],
    $classId,
    $isJame
);

$classStat = calcClassRankAndTaraz(
    $classResults,
    $_SESSION['auth']['user']
);


include_once '../temp.html';
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
    <title> کارنامه آزمون <?php echo $quiz->quizname ?> | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" type="text/css" href="../assets/print.css">
    <meta name="robots" content="noindex" />
</head>

<body>
    <div class="printview">

        <div class="content">
            <div class="clearfix">
                <img src="<?php echo url('assets/img/pngs/work4.png') ?>" class="float-sm-right wd-40p wd-sm-150"
                    alt="موسسه هنر آموزش">
            </div>
            <div class="title"><?php echo m('label.detailed_report_card') ?></div>
            <div class="meta">

                <div>شماره: <strong><?= $quiz->dars != 21 ? $req->idresult : $req->id ?> </strong></div>
                <?php
                date_default_timezone_set("Iran");
                $dateno = date("Y-m-d");
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
                $user = retPerson($_SESSION['auth']['user']);
                $datestart = $quiz->datestart;
                $datestart = explode('-', $datestart);
                $datestart = gregorian_to_jalali($datestart[0], $datestart[1], $datestart[2]);
                $datestart = implode('/', $datestart);
                ?>
                <div>تاریخ: <strong><?php echo $dateno; ?></strong></div>
            </div>
            <div class="text-left mb-3">
                <a href="print-quiz-pdf.php?quiz=<?= $_GET['quiz'] ?>"
                    class="btn btn-danger">
                    📄 دریافت PDF کارنامه
                </a>
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>

                        <th colspan="2">اطلاعات شرکت کننده</th>

                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="wd-150">نام و نام خانوادگی</td>
                        <td><?php echo $user->fname . ' ' . $user->lname ?></td>
                    </tr>
                    <tr>
                        <td class="wd-150">کد ملی</td>
                        <td><?php echo $_SESSION['auth']['user'] ?></td>
                    </tr>
                    <tr>
                        <td class="wd-150">تاریخ آزمون</td>
                        <td><?php echo $datestart; ?></td>
                    </tr>
                    <tr>
                        <td class="wd-150">نام آزمون</td>
                        <td><?php echo $quiz->quizname; ?></td>
                    </tr>
                </tbody>
            </table>
            <table>
                <thead>
                    <tr>
                        <th width="2%">ردیف</th>
                        <th>عنوان </th>
                        <th> توضیح</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td width="2%">1</td>
                        <td>تعداد کل شرکت کنندگان </td>
                        <td>
                            <?= $quiz->dars != 21 ? countresultquiz($_GET['quiz']) : countresultquizJame($_GET['quiz']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td width="2%">2</td>
                        <td>تعداد شرکت کنندگان در کلاس </td>
                        <td>
                            <?= countresultquizForThisClass($_GET['quiz'], $classId) ?>
                        </td>
                    </tr>
                    <tr>
                        <td width="2%">3</td>
                        <td>تعداد سوالات آزمون </td>
                        <td>
                            <?php echo $req->allquestion ?>
                        </td>
                    </tr>
                    <tr>
                        <td width="2%">4</td>
                        <td>تعداد پاسخ صحیح </td>
                        <td>
                            <?= $quiz->dars != 21 ? $req->trueresponse : $req->true_response ?>
                        </td>

                    </tr>
                    <tr>
                        <td width="2%">5</td>
                        <td>تعداد پاسخ غلط</td>
                        <td>
                            <?= $quiz->dars != 21 ? $req->falseresponse : $req->false_response ?>
                        </td>

                    </tr>
                    <tr>
                        <td width="2%">6</td>
                        <td>تعداد بدون پاسخ</td>
                        <td>
                            <?php echo $req->unresponse ?>
                        </td>

                    </tr>
                    <tr>
                        <td width="2%">7</td>
                        <td>مدت زمان حضور در آزمون</td>
                        <td>
                            <?php echo $req->duration ?>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th></th>
                        <th class="text-left">درصد پاسخ:</th>
                        <th class=" tx-15">%<?php echo $req->nomre ?></th>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="text-left">تراز کل:</th>
                        <th class="text-danger tx-20"><?php echo number_format($req->taraz) ?></th>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="text-left">رتبه کل:</th>
                        <th class="text-danger tx-20"><?php echo number_format($req->rotbe) ?></th>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="text-left">تراز در کلاس:</th>
                        <th class="text-primary tx-20">
                            <?= number_format($classStat['taraz']) ?>
                        </th>
                    </tr>

                    <tr>
                        <th></th>
                        <th class="text-left">رتبه در کلاس:</th>
                        <th class="text-success tx-20">
                            <?= $classStat['rank'] ?> از <?= count($classResults) ?>
                        </th>
                    </tr>
                </tfoot>
            </table>

            <?php if (!empty($req->results_json)) {
                $lessons = json_decode($req->results_json, false);
            ?>
                <br><br>
                <div class="title text-center mt-4 mb-2">کارنامه آزمون جامع (تفکیک دروس)</div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped table-bordered text-center align-middle">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col">ردیف</th>
                                <th scope="col">نام درس</th>
                                <th scope="col">ضریب درس در آزمون</th>
                                <th scope="col">تعداد سوالات</th>
                                <th scope="col">تعداد پاسخ های صحیح</th>
                                <th scope="col">تعداد پاسخ های غلط</th>
                                <th scope="col">تعداد بدون پاسخ</th>
                                <th scope="col">درصد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $row = 1;
                            foreach ($lessons as $lesson) { ?>
                                <tr class="table-light">
                                    <td class="fw-bold"><?= $row ?></td>
                                    <td class="fw-bold"><?= $lesson->lesson_name ?></td>
                                    <td class="fw-bold"><?= $lesson->lesson_coe ?></td>
                                    <td class="fw-bold"><?= $lesson->total_questions ?></td>
                                    <td class="fw-bold"><?= $lesson->true_answers ?></td>
                                    <td class="fw-bold"><?= $lesson->false_answers ?></td>
                                    <td class="fw-bold"><?= $lesson->blank_answers ?></td>
                                    <td class="fw-bold">%<?= $lesson->percent ?></td>
                                </tr>
                            <?php $row++;
                            } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>

        </div>
        <?php
        // ================================
        // پاسخبرگ صحیح آزمون (فقط تستی)
        // ================================
        $sql2 = "
SELECT 
    q.qid,
    COUNT(qc.qcid) AS choices_count,
    MAX(CASE WHEN qc.qcis_answer = 1 THEN qc.qcid END) AS correct_choice_id
FROM _questions q
JOIN _questions_choices qc ON qc.qid = q.qid
WHERE q.tid = ?
GROUP BY q.qid
ORDER BY q.qid
";
        global $ponn;
        $stmt = $ponn->prepare($sql2);
        $stmt->execute([$_GET['quiz']]);
        $answerSheet = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <?php if (!empty($answerSheet)): ?>
            <br><br>
            <div class="title text-center mt-4 mb-3">
                پاسخبرگ صحیح آزمون (سؤالات تستی)
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped text-center align-middle">
                    <thead class="thead-dark">
                        <tr>
                            <th>شماره سؤال</th>
                            <th>تعداد گزینه‌ها</th>
                            <th>گزینه صحیح</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        foreach ($answerSheet as $row):
                            // شماره گزینه صحیح (مثلاً گزینه 3)
                            $correctOptionNumber = $row['correct_choice_id']
                                ? 'گزینه ' . ($row['correct_choice_id'])
                                : 'نامشخص';
                        ?>
                            <tr>
                                <td><?= $i ?></td>
                                <td><?= $row['choices_count'] ?></td>
                                <td class="text-success fw-bold">
                                    <?= $correctOptionNumber ?>
                                </td>
                            </tr>
                        <?php
                            $i++;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>


        <?php include '../footers.php'; ?>

        <!--End Footer-->

</body>

</html>