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
    header('location: login.php');
    die();
}

if ($_SESSION['admin']['ip'] !== GetIp() && $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: login.php');
    die();
}

$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($re['session_id'] ?? '')) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

if (Permision('permision_suporter') && Permision('permision_viewcourse') == false) {
    header('location: ../login.php');
    exit;
}
$allowedRoles = [0, 51, 52];
if (!in_array($_SESSION['admin']['adminrole'], $allowedRoles)) {
    header('location: ' . url('admin/login.php'));
    die();
}

if (isset($_POST['homework_id'])) {
    $homeworkId = (int)$_POST['homework_id'];

    try {
        // Get file path before deletion to remove physical file
        $fileQuery = $conn->prepare("SELECT file_path FROM homework_admin WHERE id = ?");
        $fileQuery->execute([$homeworkId]);
        $filePath = $fileQuery->fetchColumn();

        // Delete the homework – child rows in private_homework_students will be removed automatically (CASCADE)
        $del = $conn->prepare("DELETE FROM homework_admin WHERE id = ?");
        $del->execute([$homeworkId]);

        // Delete the physical file if exists
        if (!empty($filePath) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $filePath)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $filePath);
        }

        $_SESSION['flash_msg'] = 'تکلیف با موفقیت حذف شد.';
    } catch (PDOException $e) {
        $_SESSION['flash_msg'] = 'خطا در حذف: ' . $e->getMessage();
    }

    header('location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

function deadline_jalali_date_only($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') return '—';

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        $y  = (int)$m[1];
        $mo = (int)$m[2];
        $d  = (int)$m[3];

        if ($y >= 1200 && $y <= 1600) {
            return sprintf('%04d/%02d/%02d', $y, $mo, $d);
        }

        if (function_exists('gregorian_to_jalali')) {
            [$jy, $jm, $jd] = gregorian_to_jalali($y, $mo, $d);
            return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    $ts = strtotime($raw);
    if ($ts === false) return '—';

    $gy = (int)date('Y', $ts);
    $gm = (int)date('m', $ts);
    $gd = (int)date('d', $ts);

    if (function_exists('gregorian_to_jalali')) {
        [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    return date('Y-m-d', $ts);
}

$sql = "SELECT * FROM homework_admin WHERE status = 'approved' || status = 'rejected'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>سوابق تکالیف | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        /* Accordion Custom Styling to make it clean inside Table Cell */
        .hw-student-accordion .accordion-button {
            padding: 0.4rem 0.75rem;
            font-size: 0.75rem;
            background-color: #f8f9fa;
            color: #595c73;
            box-shadow: none;
        }
        .hw-student-accordion .accordion-button:not(.collapsed) {
            background-color: #f1f0f9;
            color: #7043c5;
        }
        .hw-student-accordion .accordion-body {
            padding: 0.5rem;
            background-color: #fff;
        }
        .student-item-badge {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            background-color: #f3f4f6;
            color: #374151;
            padding: 4px 8px;
            margin-bottom: 3px;
            border-radius: 4px;
            font-size: 11px;
        }
        .text-break {
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">


                <div class="card custom-card">
                    <div class="card-header border-bottom-0 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="main-content-label mb-1">سوابق تایید تکالیف</h6>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example1" class="table table-bordered mg-b-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>عنوان</th>
                                    <th>نوع تکلیف</th>
                                    <th>توضیحات</th>
                                    <th>کد درس</th>
                                    <th>مهلت</th>
                                    <th>فایل</th>
                                    <th>وضعیت</th>
                                    <th>ایجاد کننده</th>
                                    <th>انجام شده توسط</th>
                                    <th>عملیات</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if ($rows): $i = 1; foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars((string)$r->title, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($r->homework_type == 0): ?>
                                                <span class="badge bg-light text-dark">عادی</span>
                                            <?php elseif ($r->homework_type == 1): ?>
                                                <div class="mb-1"><span class="badge bg-purple text-white">شخصی</span></div>

                                                <!-- Accordion Container Block -->
                                                <div class="accordion hw-student-accordion" id="accordion-parent-<?= $r->id ?>" style="min-width: 170px;">
                                                    <div class="accordion-item border-0">
                                                        <h2 class="accordion-header" id="heading-<?= $r->id ?>">
                                                            <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $r->id ?>" aria-expanded="false" aria-controls="collapse-<?= $r->id ?>">
                                                                نمایش لیست دانش آموزان
                                                            </button>
                                                        </h2>
                                                        <div id="collapse-<?= $r->id ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?= $r->id ?>" data-bs-parent="#accordion-parent-<?= $r->id ?>">
                                                            <div class="accordion-body border rounded-bottom mt-1">
                                                                <?php
                                                                // DB Inner Join fetching user profile metadata definitions
                                                                $studentQuery = $conn->prepare("
                                                                    SELECT 
                                                                        p.username, 
                                                                        c.fname, 
                                                                        c.lname 
                                                                    FROM private_homework_students p
                                                                    INNER JOIN dbpersonal c ON p.username = c.codemeli
                                                                    WHERE p.homework_id = ?
                                                                ");
                                                                $studentQuery->execute([$r->id]);
                                                                $students = $studentQuery->fetchAll(PDO::FETCH_OBJ);

                                                                if (!empty($students)):
                                                                    foreach ($students as $student):
                                                                        $fullName = trim((string)$student->fname . ' ' . (string)$student->lname);
                                                                        if ($fullName === '') $fullName = 'بدون نام';
                                                                        ?>
                                                                        <div class="student-item-badge">
                                                                            <span class="fw-bold text-dark"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></span>
                                                                            <span class="text-muted ms-1">(<?= htmlspecialchars((string)$student->username, ENT_QUOTES, 'UTF-8') ?>)</span>
                                                                        </div>
                                                                    <?php
                                                                    endforeach;
                                                                else:
                                                                    ?>
                                                                    <small class="text-danger d-block text-center">هیچ دانش آموزی یافت نشد!</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-break"><?= htmlspecialchars((string)$r->description, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int)$r->class_id ?></td>
                                        <td>
                                            <?php
                                            $out = deadline_jalali_date_only($r->deadline);
                                            echo '<span style="direction:ltr; unicode-bidi:embed;">' . htmlspecialchars($out, ENT_QUOTES, 'UTF-8') . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r->file_path)): ?>
                                                <a href="<?= url($r->file_path) ?>" target="_blank" rel="noopener">دانلود</a>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $st = (string)$r->status;
                                            if ($st === 'pending') echo '<span class="badge bg-warning">در حال بررسی</span>';
                                            elseif ($st === 'approved') echo '<span class="badge bg-success">تایید شده</span>';
                                            else echo '<span class="badge bg-danger">رد شده</span>';
                                            ?>
                                        </td>
                                        <td><?= $r->admin_username ?></td>

                                        <td>
                                            <?= !empty($r->approved_by) ? htmlspecialchars((string)$r->approved_by, ENT_QUOTES, 'UTF-8') : '—' ?>
                                        </td>
                                        <td class="gap-1">
                                            <form method="POST" onsubmit="return confirm('آیا از حذف این تکلیف اطمینان دارید؟ تکلیف و سوابق مربوطه به برای مثال نمرات دانش آموزان مربوطه و ... نیز حذف خواهد شد. اخطار!!');">
                                                <input type="hidden" name="homework_id" value="<?= $r->id ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                            </form>
                                            <a href="<?= url('admin/course/homeworks.php?class_id=' . (int)$r->class_id) ?>"
                                               class="btn btn-sm btn-primary">ویرایش</a>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center">درخواستی برای نمایش وجود ندارد.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>
</body>
</html>