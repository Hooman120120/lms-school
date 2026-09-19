<?php
include_once 'dbconfig.php';
include_once 'inc/jdf.php';
include_once 'inc/function.php';
include_once 'inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once 'blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: login.php');
    die();
}

//--End of Protecting against SESSION Hijaking

//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
function hasDemo($username)
{
    global $conn;

    $sql = "SELECT * 
            FROM dbcourse_demo 
            INNER JOIN course_demo 
                ON dbcourse_demo.codecourse = course_demo.codecourse 
            WHERE dbcourse_demo.username = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$username]);
    $courses = $stmt->fetchAll(PDO::FETCH_OBJ);

    if ($stmt->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}
function showDemo($username)
{
    global $conn;

    // کوئری دوره‌های دمو
    $sql2 = 'SELECT dbcourse_demo.*, course_demo.coursename
             FROM dbcourse_demo
             INNER JOIN course_demo ON dbcourse_demo.codecourse = course_demo.codecourse
             WHERE dbcourse_demo.username = ?';
    $stmt2 = $conn->prepare($sql2);
    $stmt2->execute([$username]);
    $demos = $stmt2->fetchAll(PDO::FETCH_OBJ);

    // شروع کارت
    echo '<div class="card custom-card">
            <div class="card-header border-bottom-0 pb-0">
                <div>
                    <div class="d-flex">
                        <label class="main-content-label my-auto pt-2">دوره های موقت ثبت نام شده</label>
                    </div>
                    <span class="d-block tx-12 mt-2 mb-0 text-muted">دوره های موقت برای شما</span>
                </div>
            </div>
            <div class="card-body">';

    // رندر دوره‌های دمو
    if (!empty($demos)) {
        foreach ($demos as $course) {
            $coursename = htmlspecialchars($course->coursename);
            $codecourse = urlencode($course->codecourse);
            $link = url("Course/mycourse.php?code=$codecourse&demo");
            echo '<div class="row mt-1">
                    <div class="col-8">
                        <span>' . $coursename . '</span>
                    </div>
                    <div class="col-4">
                        <a href="' . $link . '">
                            <div class="d-flex">
                                <button class="btn btn-block btn-outline-secondary">ورود به دوره</button>
                            </div>
                        </a>
                    </div>
                  </div>';
        }
    }


    // پایان کارت
    echo '</div></div>';
}

$suspension_mode = isset($_SESSION['auth']['suspension_mode'])
    ? (int)$_SESSION['auth']['suspension_mode']
    : 0;



//* End Check Session JUST ONE Session

include_once 'temp.html';

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
    <title> حساب کاربری | <?php echo __TITLE ?></title>
    <?php include_once 'inc/link.php'; ?>
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
        include_once 'menu.php';
        include_once 'header.php';
        ?>
        <!-- Main Content-->
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <?php if (isset($_SESSION['auth']) and $_SESSION['auth']['role'] == 2) { ?>
                        <!--                    داشبورد همکار آموزشی-->

                        <?php $dqt = 'SELECT * FROM dbfactor WHERE useradder=? AND status !=? ORDER BY  idfactor DESC ';
                        $qrt = $conn->prepare($dqt);
                        $qrt->execute([$_SESSION['auth']['user'], 'موفق']);
                        $readio = $qrt->fetchAll(PDO::FETCH_OBJ);
                        if ($qrt->rowCount() > 0) { ?>
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row row-sm">
                                        <div class="example">
                                            <div class="alert alert-warning mb-0" role="alert">
                                                <h4 class="alert-heading">شما فاکتور پرداخت نشده دارید!</h4>
                                                <p> لطفا جهت فعالسازی حساب کاربری دانش آموز، نسبت به پرداخت آن سریعتر اقدام
                                                    کنید</p>
                                                <p>روش های پرداخت :
                                                <ol>
                                                    <li>پرداخت آنلاین</li>
                                                    <li>واریز به حساب شبا</li>
                                                    <li>کارت به کارت</li>
                                                </ol>
                                                </p>
                                                <hr>
                                                <div class="text-center">
                                                    <a href="Collague/away-financial.php"
                                                        class="btn ripple btn-outline-warning">مشاهده
                                                        صورتحساب</a>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php } ?>
                        <!--     <div class="alert alert-warning" role="alert">-->
                        <!--    <span class="alert-inner--icon"><i class="fe fe-info"></i></span>-->
                        <!--    <span class="alert-inner--text"><strong>خبر جدید!</strong>  دوره ها از روز چهارشنبه 10 بهمن مطابق برنامه درحال برگزاری است</span>-->
                        <!--</div>-->

                    <?php } ?>
                    <?php if (isset($_SESSION['auth']) and $_SESSION['auth']['role'] == 1) { ?>
                        <!--                داشبورد دانش آموز-->
                        <?php
                        if (isset($courseregister) and $courseregister == 1) {     // این بخش درصورت فعال نبودن دوره ظاهر میشود
                            if ($_SESSION['auth']['keyactive'] != NULL and $_SESSION['auth']['truekey'] == 0) {
                                $sql = 'SELECT keyactive FROM dblogin WHERE username=?';
                                $query = $conn->prepare($sql);
                                $query->execute([$_SESSION['auth']['user']]);
                                $key = $query->fetch(); ?>
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row row-sm">
                                            <div class="col-6">
                                                <div class="card-item-title">
                                                    <label class="main-content-label tx-13 font-weight-bold mb-2">کد تایید
                                                        شما </label>
                                                    <span class="d-block tx-12 mb-0 text-muted">این کد را در اختیار مسئول ثبت نام خود قرار دهید</span>
                                                </div>
                                                <p class="mb-0 tx-30 mt-2"><b
                                                        class="text-primary text-center"><?php echo $_SESSION['auth']['keyactive'] ?></b>
                                                </p>

                                            </div>
                                            <div class="col-6">
                                                <img src="assets/img/pngs/work11.png" alt="تصویر" class="best-emp">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row row-sm">
                                            <span class="d-block tx-15 mb-5 text-muted">جهت مشاهده کلاس ها بعد از فعال سازی و ثبت نام توسط مسئول ثبت نام به همین حساب مراجعه کنید</span>
                                            <span class="d-block tx-15 mb-5 text-muted">حتما از بخش <a
                                                    href="<?php echo url('Profile/user.php') ?>"
                                                    class="btn btn-primary"> حساب کاربری</a>، اطلاعات خود را بررسی و ویرایش نمایید</span>
                                        </div>
                                    </div>
                                </div>
                                <?php if (hasDemo($_SESSION["auth"]["user"])) {
                                    showDemo($_SESSION["auth"]["user"]);
                                } ?>
                            <?php }
                            if (isfactorNotpay($_SESSION['auth']['user'])) {
                            ?>
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row row-sm">
                                            <div class="example">
                                                <div class="alert alert-warning mb-0" role="alert">
                                                    <h4 class="alert-heading">شما فاکتور پرداخت نشده دارید!</h4>
                                                    <p> لطفا جهت فعالسازی حساب کاربری خود نسبت به پرداخت آن سریعتر اقدام
                                                        کنید</p>
                                                    <hr>
                                                    <div class="text-center">
                                                        <a href="pay/finance.php" class="btn ripple btn-outline-warning">مشاهده
                                                            صورتحساب</a>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if (hasDemo($_SESSION["auth"]["user"])) {
                                    showDemo($_SESSION["auth"]["user"]);
                                } ?>
                            <?php
                            } // اعلان فاکتور پرداخت نشده برای دانش آموز
                            if (isfactorNotpay_collague($_SESSION['auth']['user'])) {
                            ?>
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row row-sm">
                                            <div class="example">
                                                <div class="alert alert-warning mb-0" role="alert">
                                                    <h4 class="alert-heading">شما فاکتور پرداخت نشده دارید!</h4>
                                                    <p> لطفا جهت فعالسازی حساب کاربری خوداز مسئول ثبت نام خود بخواهید ،
                                                        فاکتور شما را پرداخت نماید</p>


                                                </div>
                                            </div>
                                        </div>
                                        <?php showDemo($_SESSION["auth"]["user"]); ?>
                                    </div>
                                </div>
                                <?php if (hasDemo($_SESSION["auth"]["user"])) {
                                    showDemo($_SESSION["auth"]["user"]);
                                } ?>
                            <?php
                            } // اعلان فاکتور پرداخت نشده توسط همکار
                            if (uncourse($_SESSION['auth']['user'])) { ?>
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row row-sm">
                                            <div class="example">
                                                <div class="alert alert-warning mb-0" role="alert">
                                                    <h4 class="alert-heading">شما تا کنون دوره ای ثبت نام نکرده اید</h4>
                                                    <p> جهت بهره مندی از مطالب و دوره ها، نسبت به خرید دوره اقدام کنید</p>
                                                    <hr>
                                                    <div class="text-center">
                                                        <a href="course.php" class="btn ripple btn-outline-warning">مشاهده
                                                            دوره ها</a>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card custom-card">
                                    <div class="card-header  border-bottom-0 pb-0">
                                        <div>
                                            <div class="d-flex">
                                                <label class="main-content-label my-auto pt-2"> لیست گروه های بغل دستی موقت</label>
                                            </div>
                                            <span class="d-block tx-12 mt-2 mb-0 text-muted">بغل دستی های موقت شما </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mt-1">
                                            <div class="col-8">
                                                <span class=""><?php echo "گروه  بغل دستی موقت"; ?></span>
                                            </div>
                                            <div class="col-4">
                                                <a href="<?php echo url('handhug/myhandhug.php') ?>">
                                                    <div class="d-flex">
                                                        <button class="btn btn-block btn-outline-secondary">ورود به بغل دستی
                                                        </button>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if (hasDemo($_SESSION["auth"]["user"])) {
                                    showDemo($_SESSION["auth"]["user"]);
                                } ?>
                            <?php } // اعلان ثبت نام نکردن هیچ دوره ای تاکنون
                        } else { ?>
                            <!--Row-->
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class="fe fe-info"></i></span>
                                <span class="alert-inner--text"><strong>اطلاعیه!</strong>حتما در کانال ایتا دوره خود عضو شوید، با ورود به دوره و زدن دکمه ورود به کانال ایتا</span>
                            </div>


                            <div class="row row-sm  mt-lg-4">
                                <div class="col-sm-12 col-lg-12 col-xl-12">
                                    <div class="card bg-primary custom-card card-box">
                                        <div class="card-body p-4">
                                            <div class="row align-items-center">
                                                <div class="col-xl-8 col-sm-6 col-12 img-bg ">
                                                    <h4 class="d-flex  mb-3">
                                                        <span class="font-weight-bold text-white "><?php echo $_SESSION['auth']['name'] . ' ' . $_SESSION['auth']['famili'] ?></span>
                                                    </h4>
                                                    <p class="tx-white mb-1">
                                                        به مدرسه آنلاین موسسه هنرآموزش خوش آمدید
                                                    </p>
                                                    <?php
                                                    $sql = "SELECT COUNT(*) AS count FROM suport_message WHERE receiver_username = ? AND status = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_SESSION['auth']['user'], 'sent']);
                                                    $message = $stmt->fetch(PDO::FETCH_OBJ);
                                                    if ($message->count != 0) {
                                                    ?>
                                                        <h5 class="mt-4 pb-0 d-flex tx-white">شما<strong class="ms-2 me-2 border-bottom border-5"><?= $message->count ?> پیام خوانده نشده</strong>دارید.</h5>
                                                    <?php } ?>
                                                </div>
                                                <img src="assets/img/pngs/work10.png" alt="user-img">
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($suspension_mode == 0 and strlen($_SESSION['auth']['user']) != 11) : ?>
                                        <div class="card custom-card" style="background-image: url('<?= url('../assets/img/handhug/doreha.webp') ?>');  background-size: cover; background-position: center; background-repeat: no-repeat;">
                                            <div class="card-header  border-bottom-0 pb-0">
                                                <div>
                                                    <div class="d-flex">
                                                        <label class="main-content-label my-auto pt-2">دوره های ثبت نام شده</label>
                                                    </div>
                                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">دوره های فعال برای شما </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <?php
                                                $sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND codtbl_course.course_type NOT IN (30, 31, 32)';
                                                $query = $conn->prepare($sql);
                                                $query->execute([1, 1, $_SESSION['auth']['user']]);
                                                $reserved = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($reserved as $course) {

                                                ?>
                                                        <div class="row mt-1">
                                                            <div class="col-8">
                                                                <span class=""><?php echo $course->coursename; ?></span>
                                                            </div>
                                                            <div class="col-4">
                                                                <a href="<?php echo url('Course/mycourse.php?code=') . $course->codecourse ?> ">
                                                                    <div class="d-flex">

                                                                        <button class="btn btn-block btn-outline-secondary">ورود به
                                                                            دوره
                                                                        </button>

                                                                    </div>
                                                                </a>

                                                            </div>
                                                        </div>
                                                <?php
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="card custom-card">
                                            <div class="card-header  border-bottom-0 pb-0">
                                                <div>
                                                    <div class="d-flex">
                                                        <label class="main-content-label my-auto pt-2">لیست گروه های بغل دستی</label>
                                                    </div>
                                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">بغل دستی های فعال شما </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <?php
                                                $sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND codtbl_course.course_type IN (30, 31, 32)';
                                                $query = $conn->prepare($sql);
                                                $query->execute([1, 1, $_SESSION['auth']['user']]);
                                                $reserved = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($reserved as $course) {

                                                ?>
                                                        <div class="row mt-1">
                                                            <div class="col-8">
                                                                <span class=""><?php echo $course->coursename; ?></span>
                                                            </div>
                                                            <div class="col-4">
                                                                <a href="<?php echo url('handhug/myhandhug.php?code=') . $course->codecourse ?>">
                                                                    <div class="d-flex">

                                                                        <button class="btn btn-block btn-outline-secondary">ورود به بغل دستی
                                                                        </button>

                                                                    </div>
                                                                </a>
                                                            </div>
                                                        </div>
                                                <?php }
                                                } ?>
                                            </div>
                                        </div>

                                    <?php elseif ($suspension_mode == 1) : ?>
                                        <div class="alert alert-danger" role="alert" style="border-radius:12px; padding:16px; line-height:1.9;">
                                            <h4 style="margin:0 0 10px 0;">حساب کاربری شما در حالت تعلیق است.</h4>
                                            <p style="margin:0;">
                                                اگر این پیام را مشاهده می‌کنید، لطفاً برای بررسی و رفع مشکل با ما تماس بگیرید:
                                            </p>
                                            <ul style="margin:10px 0 0 0; padding-right:18px;">
                                                <li>از طریق بخش <strong>پشتیبانی</strong></li>
                                                <li>از طریق بخش <strong>ارتباط با ما</strong></li>
                                                <li>یا تماس با شماره: <a>91098185-021
                                                    </a></li>
                                            </ul>
                                        </div>

                                    <?php elseif (strlen($_SESSION['auth']['user']) == 11) : ?>
                                        <div class="alert alert-danger d-flex flex-column align-items-center text-center" role="alert" style="border-radius:12px; padding:16px; line-height:1.9;">
                                            <h4 style="margin:0 0 10px 0;">اطلاعات حساب کاربری شما تکمیل نشده است.</h4>
                                            <p style="margin:0;">
                                                برای دسترسی به دوره های خریداری شده، بایستی از طریق دکمه زیر، اطلاعات خود را تکمیل نمایید:
                                            </p>
                                            <a href="/Profile/user.php">
                                                <button class="btn btn-success">تکمیل اطلاعات</button>
                                            </a>
                                            <ul style="margin:10px 0 0 0; padding-right:18px;">
                                                <li>از طریق بخش <strong>پشتیبانی</strong> می توانید تماس رایگان برقرار کنید </li>
                                                <li>یا با شماره: <a>91098185-021 تماس بگیرید
                                                    </a></li>
                                            </ul>
                                        </div>

                                    <?php endif; ?>
                                    <?php
                                    $sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND codtbl_course.course_type IN (30, 31, 32)';
                                    $query = $conn->prepare($sql);
                                    $query->execute([1, 1, $_SESSION['auth']['user']]);
                                    $reserved = $query->fetchAll(PDO::FETCH_OBJ);
                                    if ($query->rowCount() <= 0) {
                                    ?>
                                        <div class="card custom-card">
                                            <div class="card-header  border-bottom-0 pb-0">
                                                <div>
                                                    <div class="d-flex">
                                                        <label class="main-content-label my-auto pt-2"> لیست گروه های بغل دستی موقت</label>
                                                    </div>
                                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">بغل دستی های موقت </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="row mt-1">
                                                    <div class="col-8">
                                                        <span class=""><?php echo "گروه  بغل دستی موقت"; ?></span>
                                                    </div>
                                                    <div class="col-4">
                                                        <a href="<?php echo url('handhug/myhandhug.php') ?>">
                                                            <div class="d-flex">
                                                                <button class="btn btn-block btn-outline-secondary">ورود به بغل دستی
                                                                </button>
                                                            </div>
                                                        </a>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>

                                    <?php if (hasDemo($_SESSION["auth"]["user"])) {
                                        showDemo($_SESSION["auth"]["user"]);
                                    } ?>
                                </div>


                            </div>
                            <!--Row -->

                    <?php
                        }
                    }
                    ?>


                </div>
            </div>
        </div>
        <!-- End Row -->


        <!-- Main Footer-->
        <?php include 'footers.php'; ?>
        <!--End Footer-->


</body>

</html>