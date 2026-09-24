<?php
include_once '../dbconfig.php';
include_once '../quizconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
include_once '../inc/function-quiz.php';
displayError(0);

if (isset($_GET['user']) && isset($_GET['token'])) {
    $expected_token = md5($_GET['user'] . $_GET['quiz'] . 'YOUR_SECRET_SALT');
    if ($_GET['token'] !== $expected_token) {
        die('دسترسی غیرمجاز');
    }
    $_SESSION['auth']['user'] = $_GET['user'];
    $_SESSION['auth']['role'] = 1;
}
if (empty($_SESSION['auth']['user'])) {
    die('PDF access denied');
}
// 3. Normal Web Access Authentication
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
$userTestId = GetUserTestIdByApi(GetUserIdByApi($_SESSION['auth']['user'])['uid'], GetTidFromDbQuiz($_GET['quiz']))['utid'];
$questions = GetTestQuestionsByApi($userTestId)['questions'];
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
// Protect against SESSION Hijacking

if ($_SESSION['auth']['ip'] != GetIp() || $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
// Start Check Single Session

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
if (!isset($_GET['quiz']) || $_GET['quiz'] == '') {
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
if ($qres->rowCount() == 0) {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
$sql = 'SELECT class_id FROM dbcourse WHERE codecourse = ? AND username = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$quiz->codecourse, $_SESSION['auth']['user']]);
$classId = $stmt->fetchColumn();
$isJame = ($quiz->dars == 21);
$classResults = getClassQuizResults($_GET['quiz'], $classId, $isJame);
$classStat = calcClassRankAndTaraz($classResults, $_SESSION['auth']['user']);
$chartData = [];
$chartLabels = [];
$studentTarazData = [];
$avgTarazData = [];
$showChart = false;
$resultTable = $isJame ? 'result_quizzes_jame' : 'result_quiz';
$quizIdCol = $isJame ? 'quiz_id' : 'quizid';
$currentId = $quiz->idquiz;
$lessonData = [];
if ($isJame && !empty($req->results_json)) {
    // Comprehensive: Use the JSON from DB
    $lessonData = json_decode($req->results_json, false);
} else {
    // Non-Comprehensive: Create a single row mimicking the lesson structure
    $lessonData = [(object)[
        'lesson_name' => $quiz->quizname,
        'total_questions' => $req->allquestion,
        'true_answers' => ($quiz->dars != 21 ? $req->trueresponse : $req->true_response),
        'false_answers' => ($quiz->dars != 21 ? $req->falseresponse : $req->false_response),
        'blank_answers' => $req->unresponse,
        'percent' => $req->nomre,
        'taraz' => $req->taraz ?? '---' // Displaying the main Taraz for the single lesson
    ]];
}
$sqlHistory = "
    (SELECT r.taraz, q.quizname, q.idquiz 
     FROM result_quiz r
     JOIN dbquiz q ON r.quizid = q.idquiz
     WHERE r.username = ? AND q.codecourse = ? AND q.idquiz <= ?)
    UNION ALL
    (SELECT r.taraz, q.quizname, q.idquiz 
     FROM result_quizzes_jame r
     JOIN dbquiz q ON r.quiz_id = q.idquiz
     WHERE r.username = ? AND q.codecourse = ? AND q.idquiz <= ?)
    ORDER BY idquiz ASC";

$stmtHist = $conn->prepare($sqlHistory);

// Bind the 6 parameters (Username, Course, CurrentID) x 2
$stmtHist->execute([
    $_SESSION['auth']['user'], $quiz->codecourse, $currentId,
    $_SESSION['auth']['user'], $quiz->codecourse, $currentId
]);

$historyResults = $stmtHist->fetchAll(PDO::FETCH_OBJ);

if (count($historyResults) > 1) {
    $showChart = true;
    foreach ($historyResults as $hRes) {
        $chartLabels[] = $hRes->quizname;

        // 1. Determine Quiz Type for this specific history point
        $checkQuiz = $conn->prepare("SELECT dars FROM dbquiz WHERE idquiz = ?");
        $checkQuiz->execute([$hRes->idquiz]);
        $quizType = $checkQuiz->fetchColumn();
        $isThisJame = ($quizType == 21);

        // 2. CALCULATE STUDENT TARAZ
        if ($isThisJame) {
            // Fetch the results_json for this specific past comprehensive quiz
            $stmtJson = $conn->prepare("SELECT results_json FROM result_quizzes_jame WHERE quiz_id = ? AND username = ?");
            $stmtJson->execute([$hRes->idquiz, $_SESSION['auth']['user']]);
            $jsonStr = $stmtJson->fetchColumn();

            if (!empty($jsonStr)) {
                $pastLessonData = json_decode($jsonStr);
                $pastBalances = [];
                if (is_array($pastLessonData)) {
                    foreach ($pastLessonData as $pLesson) {
                        $pastBalances[] = $pLesson->taraz ?? 0;
                    }
                    // Calculate the Average of lessons for the chart
                    $calcAvgTaraz = count($pastBalances) > 0 ? array_sum($pastBalances) / count($pastBalances) : 0;
                    $studentTarazData[] = round($calcAvgTaraz, 0);
                } else {
                    $studentTarazData[] = $hRes->taraz; // Fallback to column if JSON fails
                }
            } else {
                $studentTarazData[] = $hRes->taraz;
            }
        } else {
            // Non-comprehensive: Just use the standard Taraz column
            $studentTarazData[] = $hRes->taraz;
        }

        // 3. CALCULATE GLOBAL AVERAGE (Existing Logic)
        $avgTable = $isThisJame ? 'result_quizzes_jame' : 'result_quiz';
        $avgCol = $isThisJame ? 'quiz_id' : 'quizid';

        $sqlAvg = "SELECT AVG(taraz) as avg_t FROM $avgTable WHERE $avgCol = ?";
        $stmtAvg = $conn->prepare($sqlAvg);
        $stmtAvg->execute([$hRes->idquiz]);
        $avgResult = $stmtAvg->fetch(PDO::FETCH_ASSOC);

        $avgTarazData[] = round($avgResult['avg_t'] ?? 0);
    }
}
include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1200, initial-scale=0.1">
    <title>کارنامه آزمون <?= $quiz->quizname ?> | <?= __TITLE ?></title>

    <?php include_once '../inc/link.php'; ?>

    <style>
        @font-face {
            font-family: 'IRANSans';
            src: url('../assets/fonts/IRANSansWeb(FaNum).ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        /* A4 Landscape Simulation */
        body {
            background-color: #f0f2f5;
            font-family: 'IRANSans', 'Tahoma', sans-serif !important;
            margin: 0;
            padding: 0;
            direction: rtl;
            overflow: auto;
            -webkit-text-size-adjust: 100%;
        }

        .row {
            display: flex !important;
            flex-wrap: nowrap !important; /* Forces side-by-side always */
        }

        .col-md-7 {
            width: 58% !important;
            flex: 0 0 58% !important;
            max-width: 58% !important;
        }

        .col-md-5 {
            width: 42% !important;
            flex: 0 0 42% !important;
            max-width: 42% !important;
        }

        .landscape-page {
            width: 210mm;
            min-width: 1100px;
            min-height: 210mm;
            padding: 15px;
            height: auto !important;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            font-family: 'IRANSans', sans-serif; /* Ensure container uses it */
            overflow: visible;
            display: block !important;
        }

        /* Professional Typography & Tables */
        .report-header-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
            margin-bottom: 15px;
            display: inline-block;
        }

        .table-custom {
            width: 100%;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            font-size: 0.85rem;
        }

        .table-custom th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: bold;
            padding: 8px;
            border: 1px solid #dee2e6;
        }

        .table-custom td {
            padding: 8px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .table-custom td, .table-custom th, .h2, .h4, .h5, .report-header-title {
            font-family: 'IRANSans', sans-serif !important;
        }

        /* Summary Stats Styling */
        .stats-sidebar {
            background: #ffffff;
        }

        /* Answersheet Grid */
        .answersheet-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* 4 columns for landscape */
            gap: 5px;
            font-size: 0.75rem;
        }

        .bubble-item {
            display: flex;
            align-items: center;
            border: 1px solid #eee;
            padding: 4px;
            background: #fafafa;
        }

        .q-num {
            width: 25px;
            font-weight: bold;
            color: #777;
        }

        .bubble {
            width: 20px;
            height: 20px;
            border: 1.5px solid #555;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            margin: 0 2px;
        }

        .bubble.selected {
            background-color: #333 !important;
            color: #fff !important;
        }

        .bubble.is-correct-key {
            border: 2px solid #28a745;
            background-color: #d4edda;
        }

        .result-icon {
            margin-right: auto;
            font-weight: bold;
        }

        /* Chart constraints */
        #chart-wrapper {
            background: #fff;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            height: 300px;
        }

        .row {
            display: flex !important;
            flex-wrap: nowrap !important; /* Forces side-by-side always */
        }

        .col-md-7 {
            width: 58% !important;
            flex: 0 0 58% !important;
            max-width: 58% !important;
        }

        .col-md-5 {
            width: 42% !important;
            flex: 0 0 42% !important;
            max-width: 42% !important;
        }

        @media print {
            body {
                background: none;
                overflow: auto;
                -webkit-text-size-adjust: 100%;
            }

            .landscape-page {
                margin: 0;
                box-shadow: none;
                width: 100%;
            }

            .no-print {
                display: none;
            }
        }

        @media screen {
            .landscape-page {
                overflow: visible !important; /* Critical for dom-to-image */
                position: relative;
            }
        }
    </style>


<!--    <script src="https://cdnjs.cloudflare.com/ajax/libs/dom-to-image/2.6.0/dom-to-image.min.js"></script>-->

</head>
<body>

<div class="text-center p-3 no-print">
    <button onclick="downloadPerfectPDF()" class="btn btn-danger">📄 دریافت PDF</button>
</div>

<div class="landscape-page" id="main-capture-area">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <img src="<?= url('assets/img/pngs/work4.png') ?>" style="height: 80px;" alt="Logo">
        <h2 class="m-0" style="font-weight: 900; color: #1a2a6c;">کارنامه جامع آزمون تحلیلی</h2>
        <div class="text-left small">
            <?php
            date_default_timezone_set("Asia/Tehran");
            $dateno = date("Y-m-d");
            $dateno = explode('-', $dateno);
            $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
            $dateno = implode('/', $dateno);
            $user = retPerson($_SESSION['auth']['user']);
            $datestart = explode('-', $quiz->datestart);
            $datestart = gregorian_to_jalali($datestart[0], $datestart[1], $datestart[2]);
            $datestart = implode('/', $datestart);
            ?>
            تاریخ: <?= $dateno ?><br>
            کد آزمون: <?= $quiz->idquiz ?>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <table class="table-custom">
                <thead>
                <tr>
                    <th colspan="4" class="text-right">اطلاعات شرکت کننده</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td class="bg-light">نام و نام خانوادگی</td>
                    <td><strong><?= $user->fname . ' ' . $user->lname ?></strong></td>
                    <td class="bg-light">کد ملی</td>
                    <td><strong><?= $_SESSION['auth']['user'] ?></strong></td>
                </tr>
                <tr>
                    <td class="bg-light">نام آزمون</td>
                    <td><?= $quiz->quizname ?></td>
                    <td class="bg-light">تاریخ برگزاری</td>
                    <td><?= $datestart ?></td>
                </tr>
                <tr>
                    <td class="bg-light">تعداد کل شرکت کنندگان</td>
                    <td><?= $quiz->dars != 21 ? countresultquiz($_GET['quiz']) : countresultquizJame($_GET['quiz']) ?></td>
                    <td class="bg-light">تعداد شرکت کنندگان در کلاس</td>
                    <td><?= $quiz->dars != 21 ? countresultquizForThisClass($_GET['quiz'], $classId) : countResultQuizForThisClassJame($_GET['quiz'], $classId) ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">

        <div class="col-md-7">
            <div class="report-header-title">روند پیشرفت و تراز</div>
            <div id="chart-wrapper">
                <canvas id="progressChart"></canvas>
            </div>

            <?php if (GetTidFromDbQuiz($_GET['quiz'])) { ?>
                <div class="report-header-title mt-3">پاسخ‌نامه تستی (نمای کلی)</div>
                <div class="answersheet-container">
                    <?php
                    $q_num = 1;
                    foreach ($questions as $question):
                        // $details = GetQuestionDetails($userTestId, $question->qid);
                        $details = GetQuestionDetailsByApi($userTestId, $question['qid']);
                        $choice_count = $details['choice_count'] ?? 4;
                        // $choice_count = $details->choice_count ?? 4;
                        ?>
                        <div class="bubble-item">
                            <span class="q-num"><?= $q_num ?></span>
                            <?php for ($i = 1; $i <= $choice_count; $i++):
                                $b_class = '';
                                if ($details['student_choice'] == $i) $b_class .= ' selected';
                                if ($details['correct_choice'] == $i) $b_class .= ' is-correct-key';
                                ?>
                                <div class="bubble <?= $b_class ?>"><?= $i ?></div>
                            <?php endfor; ?>

                            <div class="result-icon">
                                <?php if (empty($details['student_choice'])): ?>
                                    <span class="text-warning">!</span>
                                <?php elseif ($details['student_choice'] == $details['correct_choice']): ?>
                                    <span class="text-success">✓</span>
                                <?php else: ?>
                                    <span class="text-danger">×</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php $q_num++; endforeach; ?>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-5">
            <div class="report-header-title">
                <?= $isJame ? 'وضعیت آزمون جامع' : 'وضعیت آزمون تک درس' ?>
            </div>

            <table class="table-custom text-center" style="font-size: 0.75rem;">
                <thead class="thead-light">
                <tr>
                    <th>درس</th>
                    <th>سوال</th>
                    <th class="text-success">صحیح</th>
                    <th class="text-danger">غلط</th>
                    <th>نزده</th>
                    <th>درصد</th>
                    <th>تراز</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $balances = [];
                foreach ($lessonData as $lesson):
                    $balances[] = $lesson->taraz;

                    ?>
                    <tr>
                        <td class="text-right"><strong><?= $lesson->lesson_name ?></strong></td>
                        <td><?= $lesson->total_questions ?></td>
                        <td class="text-success"><?= $lesson->true_answers ?></td>
                        <td class="text-danger"><?= $lesson->false_answers ?></td>
                        <td><?= $lesson->blank_answers ?? ($lesson->total_questions - ($lesson->true_answers + $lesson->false_answers)) ?></td>
                        <td class="font-weight-bold"><?= $lesson->percent ?>%</td>
                        <td class="text-primary"><?= isset($lesson->taraz) ? number_format($lesson->taraz) : '---' ?></td>  
                    </tr>
                <?php endforeach;
                $sum = array_sum($balances);
                $count = count($balances);
                $totalBalance = $sum / $count;
                ?>
                </tbody>
            </table>

            <div class="p-3 border rounded shadow-sm text-white"
                 style="background: linear-gradient(45deg, #1a2a6c, #b21f1f, #fdbb2d);">
                <div class="row text-center">
                    <div class="col-6 border-left">
                        <small>درصد نهایی</small>
                        <div class="h4 m-0">%<?= $req->nomre ?></div>
                    </div>
                    <div class="col-6">
                        <small>تراز کل</small>
                        <div class="h4 m-0"><?= number_format(round($totalBalance, 0)); ?></div>
                    </div>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2)">
                <div class="row text-center">
                    <div class="col-6 border-left">
                        <small>رتبه کل</small>
                        <div class="h5 m-0"><?= number_format($req->rotbe) ?></div>
                    </div>
                    <div class="col-6">
                        <small>رتبه در کلاس</small>
                        <div class="h5 m-0"><?= $classStat['rank'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../footers.php'; ?>
</div>

<script>
    // Keep Chart Mechanism Exactly as it was
    const ctx = document.getElementById('progressChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'تراز شما',
                    data: <?= json_encode($studentTarazData) ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    tension: 0,
                    fill: true
                },
                {
                    label: 'میانگین کل',
                    data: <?= json_encode($avgTarazData) ?>,
                    borderColor: '#e74c3c',
                    tension: 0,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {y: {min: 2000}}
        }
    });

    // High Quality Landscape PDF capture
    async function downloadPerfectPDF() {
        const {jsPDF} = window.jspdf;
        const element = document.getElementById('main-capture-area');

        // 1. Prepare for full-page capture
        // We remove any height constraints and ensure all content is visible
        const originalStyle = element.style.cssText;
        element.style.height = 'auto';
        element.style.overflow = 'visible';
        element.style.width = '1122px'; // Standard A4 Landscape width
        element.style.minWidth = '1122px';

        // 2. Use html2canvas to render the entire element
        html2canvas(element, {
            scale: 2, // High resolution
            useCORS: true,
            logging: false,
            allowTaint: true,
            scrollY: -window.scrollY, // Fix for scrolled pages cutting off top
            windowWidth: 1122,
            height: element.scrollHeight // Explicitly capture the full height
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');

            // 3. Initialize PDF in Landscape
            const pdf = new jsPDF('l', 'mm', 'a4');
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = pdf.internal.pageSize.getHeight();

            // 4. Calculate scaling to fit the whole capture into ONE page
            // We calculate the ratio to ensure the image doesn't overflow the A4 height
            const canvasRatio = canvas.height / canvas.width;
            let finalWidth = pdfWidth;
            let finalHeight = pdfWidth * canvasRatio;

            // If the content is too long for one A4 page, we shrink it to fit
            if (finalHeight > pdfHeight) {
                finalHeight = pdfHeight;
                finalWidth = finalHeight / canvasRatio;
            }

            // Center the content on the page
            const xOffset = (pdfWidth - finalWidth) / 2;

            pdf.addImage(imgData, 'PNG', xOffset, 0, finalWidth, finalHeight);
            pdf.save('Full-Comprehensive-Report.pdf');

            // 5. Restore original layout
            element.style.cssText = originalStyle;
        }).catch(err => {
            console.error("PDF Export Error:", err);
            element.style.cssText = originalStyle;
        });
    }
</script>
</body>
</html>