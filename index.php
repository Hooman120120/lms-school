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

    $sql2 = 'SELECT dbcourse_demo.*, course_demo.coursename
             FROM dbcourse_demo
             INNER JOIN course_demo ON dbcourse_demo.codecourse = course_demo.codecourse
             WHERE dbcourse_demo.username = ?';
    $stmt2 = $conn->prepare($sql2);
    $stmt2->execute([$username]);
    $demos = $stmt2->fetchAll(PDO::FETCH_OBJ);

    echo '<div class="card custom-card">
            <div class="card-header border-bottom-0 pb-0">
                <div>
                    <div class="d-flex">
                        <label class="main-content-label my-auto pt-2">دوره‌های فوق برنامه ثبت نام شده</label>
                    </div>
                    <span class="d-block tx-12 mt-2 mb-0 text-muted">دوره‌های فوق برنامه برای شما</span>
                </div>
            </div>
            <div class="card-body">';

    // رندر دوره‌های فوق برنامه

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
                                <button class="btn btn-block btn-outline-secondary">' . m('action.enter_course') . '</button>
                            </div>
                        </a>
                    </div>
                  </div>';
        }
    }
    echo '</div></div>';
}

$suspension_mode = isset($_SESSION['auth']['suspension_mode'])
    ? (int)$_SESSION['auth']['suspension_mode']
    : 0;
//notif get
$activeNotifs = ActiveDailyNotifications();

// news
$activeNews = ActivePanelNews();

include_once 'panel/user_calendar.php';
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
                    <!--     داشبورد همکار آموزشی-->
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
                                            <p> <?php echo m('msg.pay_to_activate') ?></p>
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
                    <!--<div class="alert alert-warning" role="alert">-->
                    <!--    <span class="alert-inner--icon"><i class="fe fe-info"></i></span>-->
                    <!--    <span class="alert-inner--text"><strong>خبر جدید!</strong>  پایه ها از روز چهارشنبه 10 بهمن مطابق برنامه درحال برگزاری است</span>-->
                    <!--</div>-->

                <?php } ?>
                <?php if (isset($_SESSION['auth']) and $_SESSION['auth']['role'] == 1) { ?>
                    <!--                داشبورد دانش آموز-->
                    <?php
                    if (isset($courseregister) and $courseregister == 1) {     // این بخش درصورت فعال نبودن پایه ظاهر میشود
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
                                                <h4 class="alert-heading"><?php echo m('msg.no_course_yet') ?></h4>
                                                <p> <?php echo m('msg.buy_course_prompt') ?></p>
                                                <hr>
                                                <div class="text-center">
                                                    <a href="course.php" class="btn ripple btn-outline-warning"><?php echo m('action.view_courses') ?></a>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
<!--                            <div class="card custom-card">-->
<!--                                <div class="card-header  border-bottom-0 pb-0">-->
<!--                                    <div>-->
<!--                                        <div class="d-flex">-->
<!--                                            <label class="main-content-label my-auto pt-2"> لیست گروه های بغل دستی موقت</label>-->
<!--                                        </div>-->
<!--                                        <span class="d-block tx-12 mt-2 mb-0 text-muted">بغل دستی های موقت شما </span>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                                <div class="card-body">-->
<!--                                    <div class="row mt-1">-->
<!--                                        <div class="col-8">-->
<!--                                            <span class="">--><?php //echo "گروه  بغل دستی موقت"; ?><!--</span>-->
<!--                                        </div>-->
<!--                                        <div class="col-4">-->
<!--                                            <a href="--><?php //echo url('handhug/myhandhug.php') ?><!--">-->
<!--                                                <div class="d-flex">-->
<!--                                                    <button class="btn btn-block btn-outline-secondary">ورود به بغل دستی-->
<!--                                                    </button>-->
<!--                                                </div>-->
<!--                                            </a>-->
<!--                                        </div>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                            </div>-->
                            <?php if (hasDemo($_SESSION["auth"]["user"])) {
                                showDemo($_SESSION["auth"]["user"]);
                            } ?>
                        <?php } // اعلان ثبت نام نکردن هیچ پایه ای تاکنون
                    } else { ?>
                        <!--Row-->
                        <!--                        <div class="alert alert-success" role="alert">-->
                        <!--                            <span class="alert-inner--icon"><i class="fe fe-info"></i></span>-->
                        <!--                            <span class="alert-inner--text"><strong>اطلاعیه!</strong>حتما در کانال ایتا پایه خود عضو شوید، با ورود به پایه و زدن دکمه ورود به کانال ایتا</span>-->
                        <!--                        </div>-->

                        <div class="row row-sm  mt-lg-4">
                            <div class="col-sm-12 col-lg-12 col-xl-12">
                                <div class="row align-items-center">

                                    <div class="col-lg-3 col-md-5 col-12 text-center mt-lg-4 mt-0 mb-3 mb-md-0">
                                        <?php if ($_SESSION['auth']['pic']): ?>
                                            <img src="<?php echo $_SESSION['auth']['pic']; ?>" class="img-fluid rounded shadow-sm"
                                                 style="max-width: 200px; width: 100%; height: auto; aspect-ratio: 1/1; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="assets/img/bigprof.png" class="img-fluid rounded shadow-sm"
                                                 style="max-width: 200px; width: 100%; height: auto; aspect-ratio: 1/1; object-fit: cover;">
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-lg-6 col-md-7 col-12 text-right mt-lg-4 mt-0">
                                        <div class="mb-2"><strong class="text-muted">نام :</strong> <span
                                                    class="h6"><?php echo $_SESSION ['auth']['name']; ?></span></div>
                                        <div class="mb-2"><strong class="text-muted">نام خانوادگی :</strong> <span
                                                    class="h6"><?php echo $_SESSION ['auth']['famili']; ?></span></div>
                                        <div class="mb-2"><strong class="text-muted"><?php echo m('label.grade_colon_sp') ?></strong> <span
                                                    class="h6"><?php echo $_SESSION ['auth']['paye']; ?></span></div>
                                    </div>

                                </div>
                                <?php if ($suspension_mode == 0 and strlen($_SESSION['auth']['user']) != 11) :
                                    renderUserCalendar();
                                    ?>
                                    <div class="row mt-4 mb-4">

                                        <!-- ستون تابلو اعلانات -->
                                        <div class="col-12 col-lg-6 mb-3 mb-lg-0">
                                            <div class="card card-box h-100" style="background:#fff;color:#000;">
                                                <div class="card-body p-4">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="#000" viewBox="0 0 24 24" style="margin-left:8px;">
                                                            <path d="M3 10v4a1 1 0 0 0 1 1h2l3 5h2l-2.5-5H13l6 3V6l-6 3H4a1 1 0 0 0-1 1z" />
                                                        </svg>
                                                        <strong>تابلو اعلانات</strong>
                                                    </div>

                                                    <?php if (count($activeNotifs) > 0): ?>
                                                        <div class="mt-3 pl-2" style="max-height: 300px; overflow-y: auto; overflow-x: hidden;">
                                                            <?php foreach ($activeNotifs as $notif):
                                                                $image_path_db = $notif->image_path ?? '';
                                                                if (!empty($image_path_db)) {
                                                                    $image_url = $image_path_db;
                                                                    $image_fs =  $image_path_db;
                                                                } else {
                                                                    $image_url = '';
                                                                    $image_fs = '';
                                                                }
                                                                ?>
                                                                <div class="alert alert-info mb-3" style="background-color: #f8f9fa; border: 1px solid #e2e8f0; color: #333; border-radius: 8px;">
                                                                    <div style="line-height: 1.8; font-size: 14px;">
                                                                        <?php echo nl2br(htmlspecialchars($notif->text)); ?>
                                                                    </div>
                                                                    <?php if (!empty($image_path_db) && file_exists($image_fs)): ?>
                                                                        <div class="mt-2 d-flex justify-content-center">
                                                                            <div style="position: relative; display: flex; justify-content: center; align-items: center; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; overflow: hidden;">
                                                                                <img src="<?php echo htmlspecialchars($image_url); ?>"
                                                                                     alt="تصویر اعلان"
                                                                                     style="position: static; float: none; max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer; display: block; margin: 0; padding: 0; border: none;"
                                                                                     data-bs-toggle="modal"
                                                                                     data-bs-target="#imageModal"
                                                                                     data-image-src="<?php echo htmlspecialchars($image_url); ?>">
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="d-flex align-items-center mt-2">
                                                                        <span class="badge badge-primary" style="background-color: #0d6efd;">جدید</span>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center text-muted" style="padding:30px 0;">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" fill="#888" viewBox="0 0 24 24" style="opacity:0.6; display:block; margin:0 auto;">
                                                                <path d="M3 10v4a1 1 0 0 0 1 1h2l3 5h2l-2.5-5H13l6 3V6l-6 3H4a1 1 0 0 0-1 1z" />
                                                            </svg>
                                                            <div style="margin-top:10px;">شما در ۲۴ ساعت گذشته اعلانی ندارید</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ستون اخبار -->
                                        <div class="col-12 col-lg-6">
                                            <div class="card card-box h-100" style="background:#fff;color:#000;">
                                                <div class="card-body p-4">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="#000" viewBox="0 0 24 24" style="margin-left:8px;">
                                                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                                        </svg>
                                                        <strong>اخبار</strong>
                                                    </div>

                                                    <?php if (count($activeNews) > 0): ?>
                                                        <div class="mt-3 pl-2" style="max-height: 300px; overflow-y: auto; overflow-x: hidden;">
                                                            <?php foreach ($activeNews as $news_item):
                                                                $img_path_db = $news_item->image_path ?? '';
                                                                if (!empty($img_path_db)) {
                                                                    $img_url = $img_path_db;   // already correct
                                                                    $img_fs =  $img_path_db;
                                                                } else {
                                                                    $img_url = '';
                                                                    $img_fs = '';
                                                                }
                                                                ?>
                                                                <div class="alert mb-2" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 8px; overflow: hidden;">
                                                                    <div class="d-flex align-items-center mb-1">
                                                                        <span class="badge badge-success ml-2">خبر</span>
                                                                        <small class="text-muted" dir="ltr">
                                                                            <?php echo function_exists('jdate') ? jdate('Y/m/d', strtotime($news_item->created_at)) : substr($news_item->created_at, 0, 10); ?>
                                                                        </small>
                                                                    </div>
                                                                    <div style="line-height: 1.8; font-size: 14px; word-break: break-word;">
                                                                        <?php echo nl2br(htmlspecialchars($news_item->text)); ?>
                                                                    </div>
                                                                    <?php if (!empty($img_path_db) && file_exists($img_fs)): ?>
                                                                        <div class="mt-2 d-flex justify-content-center">
                                                                            <div style="position: relative; display: flex; justify-content: center; align-items: center; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; overflow: hidden;">
                                                                                <img src="<?php echo htmlspecialchars($img_url); ?>"
                                                                                     alt="تصویر خبر"
                                                                                     style="position: static; float: none; max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer; display: block; margin: 0; padding: 0; border: none;"
                                                                                     data-bs-toggle="modal"
                                                                                     data-bs-target="#imageModal"
                                                                                     data-image-src="<?php echo htmlspecialchars($img_url); ?>">
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center text-muted" style="padding:30px 0;">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" fill="#888" viewBox="0 0 24 24" style="opacity:0.6; display:block; margin:0 auto;">
                                                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
                                                            </svg>
                                                            <div style="margin-top:10px;">خبر جدیدی وجود ندارد</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal (placed once outside the row) -->
                                        <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">تصویر</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                                                    </div>
                                                    <div class="modal-body text-center">
                                                        <img id="modalImage" src="" alt="تصویر بزرگ" style="max-width:100%; max-height:80vh;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>


                                    <!--                                    <div class="card custom-card">-->
                                    <!--                                        <div class="card-header  border-bottom-0 pb-0">-->
                                    <!--                                            <div>-->
                                    <!--                                                <div class="d-flex">-->
                                    <!--                                                    <label class="main-content-label my-auto pt-2">لیست گروه های بغل دستی</label>-->
                                    <!--                                                </div>-->
                                    <!--                                                <span class="d-block tx-12 mt-2 mb-0 text-muted">بغل دستی های فعال شما </span>-->
                                    <!--                                            </div>-->
                                    <!--                                        </div>-->
                                    <!--                                        <div class="card-body">-->
                                    <!--                                            --><?php
//                                            $sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND codtbl_course.course_type IN (30, 31, 32)';
//                                            $query = $conn->prepare($sql);
//                                            $query->execute([1, 1, $_SESSION['auth']['user']]);
//                                            $reserved = $query->fetchAll(PDO::FETCH_OBJ);
//                                            if ($query->rowCount() > 0) {
//                                                foreach ($reserved as $course) {
//
//                                                    ?>
                                    <!--                                                    <div class="row mt-1">-->
                                    <!--                                                        <div class="col-8">-->
                                    <!--                                                            <span class="">--><?php //echo $course->coursename; ?><!--</span>-->
                                    <!--                                                        </div>-->
                                    <!--                                                        <div class="col-4">-->
                                    <!--                                                            <a href="--><?php //echo url('handhug/myhandhug.php?code=') . $course->codecourse ?><!--">-->
                                    <!--                                                                <div class="d-flex">-->
                                    <!---->
                                    <!--                                                                    <button class="btn btn-block btn-outline-secondary">ورود به بغل دستی-->
                                    <!--                                                                    </button>-->
                                    <!---->
                                    <!--                                                                </div>-->
                                    <!--                                                            </a>-->
                                    <!--                                                        </div>-->
                                    <!--                                                    </div>-->
                                    <!--                                                --><?php //}
//                                            } ?>
                                    <!--                                        </div>-->
                                    <!--                                    </div>-->

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
                                            <?php echo m('msg.complete_profile_for_courses') ?>
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
                                <!--                                --><?php
                                //                                $sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND codtbl_course.course_type IN (30, 31, 32)';
                                //                                $query = $conn->prepare($sql);
                                //                                $query->execute([1, 1, $_SESSION['auth']['user']]);
                                //                                $reserved = $query->fetchAll(PDO::FETCH_OBJ);
                                //                                if ($query->rowCount() <= 0) {
                                //                                    ?>
                                <!--                                    <div class="card custom-card">-->
                                <!--                                        <div class="card-header  border-bottom-0 pb-0">-->
                                <!--                                            <div>-->
                                <!--                                                <div class="d-flex">-->
                                <!--                                                    <label class="main-content-label my-auto pt-2"> لیست گروه های بغل دستی موقت</label>-->
                                <!--                                                </div>-->
                                <!--                                                <span class="d-block tx-12 mt-2 mb-0 text-muted">بغل دستی های موقت </span>-->
                                <!--                                            </div>-->
                                <!--                                        </div>-->
                                <!--                                        <div class="card-body">-->
                                <!--                                            <div class="row mt-1">-->
                                <!--                                                <div class="col-8">-->
                                <!--                                                    <span class="">--><?php //echo "گروه  بغل دستی موقت"; ?><!--</span>-->
                                <!--                                                </div>-->
                                <!--                                                <div class="col-4">-->
                                <!--                                                    <a href="--><?php //echo url('handhug/myhandhug.php') ?><!--">-->
                                <!--                                                        <div class="d-flex">-->
                                <!--                                                            <button class="btn btn-block btn-outline-secondary">ورود به بغل دستی-->
                                <!--                                                            </button>-->
                                <!--                                                        </div>-->
                                <!--                                                    </a>-->
                                <!---->
                                <!--                                                </div>-->
                                <!--                                            </div>-->
                                <!--                                        </div>-->
                                <!--                                    </div>-->
                                <!--                                --><?php //} ?>

                                <!--                                --><?php //if (hasDemo($_SESSION["auth"]["user"])) {
                                //                                    showDemo($_SESSION["auth"]["user"]);
                                //                                } ?>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('imageModal');
            if (modal) {
                modal.addEventListener('show.bs.modal', function(event) {
                    var trigger = event.relatedTarget;
                    var imgUrl = trigger.getAttribute('data-image-src');
                    document.getElementById('modalImage').src = imgUrl;
                });
            }
        });
    </script>

</body>

</html>