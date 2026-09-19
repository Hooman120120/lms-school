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

// محافظت از نشست
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

// فقط ادمین
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    die();
}

// چک کردن وجود کد پایه
if (!isset($_GET['code']) or $_GET['code'] == '') {
    header('location: ' . url('Course/allcourse.php'));
    die();
}

$course = returnCourse($_GET['code']);

// فقط درس‌هایی که برای کلاس همین دانش‌آموز تعریف شده‌اند.
$classStmt = $conn->prepare('SELECT class_id FROM dbcourse WHERE username = ? AND codecourse = ? LIMIT 1');
$classStmt->execute([$_SESSION['auth']['user'], (int)$_GET['code']]);
$studentClassId = (int)$classStmt->fetchColumn();
$sql = "SELECT d.* FROM class_dars cd INNER JOIN dbdars d ON d.id = cd.dars_id WHERE cd.class_id = ? AND d.codecourse = ? ORDER BY d.dars";
$stmt = $conn->prepare($sql);
$stmt->execute([$studentClassId, (int)$_GET['code']]);
$darsHa = $stmt->fetchAll(PDO::FETCH_OBJ);

include_once '../temp.html';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>انتخاب درس | <?php echo $course->coursename ?> </title>
    <?php include_once '../inc/link.php'; ?>
</head>
<body class="main-body leftmenu">

<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<div class="page">
    <?php include_once '../menu.php';
    include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="row row-sm ">
                    <div class="col-12">
                        <div class="card custom-card text-center shadow">
                            <div class="card-header d-flex flex-row justify-content-between">
                                <h5 class="mb-2 font-weight-bold text-right">
                                    انتخاب درس برای پایه: <span
                                            class="text-primary"><?php echo $course->coursename; ?></span>
                                </h5>
                                <div>
                                    <a href="mycourse.php?code=<?= $_GET['code'] ?>"
                                       class="btn btn-warning float-left mb-3 mt-n2">بازگشت</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($_GET['type'] == 'addon') { ?>
                                    <p class="text-muted mb-4">لطفاً یکی از درس‌های زیر را انتخاب کنید تا جزوات مربوط به
                                        آن
                                        نمایش داده شود.</p>
                                <?php } else { ?>
                                    <p class="text-muted mb-4">لطفاً یکی از درس‌های زیر را انتخاب کنید تا فیلم های مربوط
                                        به
                                        آن
                                        نمایش داده شود.</p>
                                <?php } ?>
                                <div class="d-flex flex-column col-md-8 col-lg-6 col-xl-6 align-items-center mx-auto">
                                    <!-- دکمه همه درس‌ها -->
                                    <?php if ($darsHa) { ?>
                                        <a href="<?= $_GET['type'] ?>.php?code=<?php echo $_GET['code']; ?>&dars=all"
                                           class="btn btn-outline-dark btn-block m-2 px-4 py-2 text-center">
                                            همه درس‌ها
                                        </a>

                                        <?php foreach ($darsHa as $dars) { ?>
                                            <a href="<?= $_GET['type'] ?>.php?code=<?php echo $_GET['code']; ?>&dars=<?php echo $dars->id; ?>"
                                               class="btn btn-outline-primary btn-block m-2 px-4 py-2 text-center">
                                                <?php echo htmlspecialchars($dars->dars); ?>
                                            </a>
                                        <?php }
                                    } else { ?>
                                        <p class="text-danger mt-3 text-center">هیچ درسی برای این پایه ثبت نشده است.</p>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../footers.php'; ?>

</body>
</html>
