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

// Protecting against SESSION Hijacking
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['auth']['os'] != GetOs()) {
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
    header('location: index.php');
    exit;
}

if (isset($_GET['addcheckout']) && $_GET['addcheckout'] != '') {
    $course_code = intval($_GET['addcheckout']);
    $price_model = (isset($_GET['priceModel']) && $_GET['priceModel'] === 'without') ? 'without' : 'with';
    $username = $_SESSION['auth']['user'];
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

    // 1. Check if course is already active/purchased for this user
    if (!checkactCourse($username, $course_code)) {

        // Fetch course pricing details
        $stmt = $conn->prepare("SELECT coursefee, coursefee_without FROM codtbl_course WHERE codecourse = ?");
        $stmt->execute([$course_code]);
        $course_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($course_data) {
            $fee = ($price_model === 'without' && !empty($course_data['coursefee_without']))
                ? $course_data['coursefee_without']
                : $course_data['coursefee'];

            // 2. Check if the course is already sitting in the checkout table (cart)
            $check_cart = $conn->prepare("SELECT id FROM checkout WHERE username = ? AND coursecode = ?");
            $check_cart->execute([$username, $course_code]);
            $existing_cart_item = $check_cart->fetch();

            if ($existing_cart_item) {
                // UPDATE existing price model and fee in cart
                try {
                    $update_cart = $conn->prepare("UPDATE checkout SET fee = ?, price_type = ? WHERE username = ? AND coursecode = ?");
                    $update_cart->execute([$fee, $price_model, $username, $course_code]);
                } catch (\Exception $e) {
                    $update_cart = $conn->prepare("UPDATE checkout SET fee = ? WHERE username = ? AND coursecode = ?");
                    $update_cart->execute([$fee, $username, $course_code]);
                }

                $msg = 'نوع پشتیبانی و قیمت محصول در سبد خرید بروزرسانی شد';
            } else {
                // INSERT new item into cart
                try {
                    $add_cart = $conn->prepare("INSERT INTO checkout (username, coursecode, fee, price_type) VALUES (?, ?, ?, ?)");
                    $add_cart->execute([$username, $course_code, $fee, $price_model]);
                } catch (\Exception $e) {
                    $add_cart = $conn->prepare("INSERT INTO checkout (username, coursecode, fee) VALUES (?, ?, ?)");
                    $add_cart->execute([$username, $course_code, $fee]);
                }

                $msg = 'محصول با موفقیت به سبد خرید اضافه شد';
            }

            if ($is_ajax) {
                if (ob_get_length()) ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'success', 'message' => $msg]);
                exit();
            }

            $_SESSION['massege'] = 100;
            header('location: course.php');
            exit();
        }
    } else {
        if ($is_ajax) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => m('msg.course_already_active')]);
            exit();
        }
        $_SESSION['massege'] = 31;
        header('location: course.php');
        exit();
    }
}

include_once 'temp.html';


if (isset($_SESSION['massege'])) {
    $verify = $_SESSION['massege'];
    unset($_SESSION['massege']);
}

if (isset($_GET['del']) and $_GET['del'] != '') {
    $del = intval($_GET['del']);
    if (!checkout($_SESSION['auth']['user'], $del)) {
        $delete = 'DELETE FROM checkout WHERE username=? AND coursecode=?';
        $actdelete = $conn->prepare($delete);
        $actdelete->execute([$_SESSION['auth']['user'], $del]);
        header('location: course.php');
    } else {
        header('location: course.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title> <?php echo m('page.plans_and_courses') ?> | <?php echo __TITLE ?></title>
    <?php include_once 'inc/link.php'; ?>

    <style>
        .top-nav-container { text-align: right; margin-bottom: 30px; }
        .main-tabs { display: flex; justify-content: flex-start; direction: rtl; gap: 10px; }

        .btn-main {
            /* رنگ پس‌زمینه به صورت گرادینت ملایم */
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 50px; /* دکمه‌های کپسولی */
            color: #444;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 5px;
            border: 1px solid #fff;
        }

        /* حالت هاور (وقتی موس روی دکمه میره) */
        .btn-main:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
            color: #000;
        }

        /* استایل دکمه‌ای که انتخاب شده (Active) */
        .btn-main.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            box-shadow: 0 8px 15px rgba(102, 126, 234, 0.4);
            border: none;
        }

        /* افکت کلیک شدن */
        .btn-main:active {
            transform: translateY(1px);
        }

        /* تنظیمات پایه‌ها */
        .grade-group { display: none; flex-direction: row; flex-wrap: wrap; justify-content: flex-start; gap: 15px; direction: rtl; }
        .grade-group.active { display: flex !important; }

        .grade-card {
            direction: rtl; position: relative; width: 100px; height: 120px;
            background: #fff; border: 2px solid #eee; border-radius: 20px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            cursor: pointer; overflow: hidden; transition: all 0.4s ease; z-index: 1;
        }
        .grade-card span { font-size: 2rem; font-weight: 900; z-index: 2; }
        .grade-card small { font-weight: bold; z-index: 2; }
        .grade-card:hover span, .grade-card:hover small { color: white; }
        .grade-card::before {
            content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; border-radius: 50%;
            transform: translate(-50%, -50%); transition: width 0.6s ease, height 0.6s ease; z-index: -1;
        }
        .grade-card:hover::before { width: 300px; height: 300px; border-radius: 0; }

        /* رنگ پایه‌ها */
        /* رنگ کارت‌ها بر اساس جایگاه در فهرست، نه عدد پایه — تا فهرست‌های دلخواه هم رنگ بگیرند */
        .grade-c1::before { background: #ff9800; } .grade-c2::before { background: #ff5722; }
        .grade-c3::before { background: #e91e63; } .grade-c4::before { background: #9c27b0; }
        .grade-c5::before { background: #673ab7; } .grade-c6::before { background: #0d47a1; }
        .grade-c7::before { background: #03a9f4; } .grade-c8::before { background: #00bcd4; }
        .grade-c9::before { background: #1d86db; }
        /* --- سیستم ردیفی (Grid) جدید --- */
        .results-area-style {
            display: flex;
            flex-wrap: wrap;
            margin: 30px -10px 0 -10px;
            direction: rtl;
            justify-content: flex-start; /* کارت‌ها رو از سمت راست می‌چینه و اگه کم باشن وسط نمی‌بره */
            align-items: stretch;
        }

        /* تنظیم ستون‌ها: موبایل ۲ تا، دسکتاپ ۴ تا */
        .course-item {
            padding: 10px;
            flex: 0 0 50%; /* در موبایل دقیقاً نصف صفحه */
            max-width: 50%;
            display: flex; /* برای اینکه ارتفاع کارت‌ها در یک ردیف هم‌اندازه بمونه */
        }

        @media (min-width: 992px) {
            .course-item {
                flex: 0 0 25%; /* در دسکتاپ دقیقاً یک چهارم صفحه */
                max-width: 25%;
            }
        }

        .course-card-vertical {
            background: #ffffff;
            border-radius: 25px;
            overflow: hidden;
            position: relative;
            transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 20px rgba(0,0,0,0.02);
        }

        /* افکت هوشمند هنگام هاور کل کارت */
        .course-card-vertical:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }

        /* بخش تصویر با افکت زوم */
        .card-image-wrapper {
            position: relative;
            width: 100%;
            height: 300px;
            overflow: hidden;
        }

        .card-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain; /* Prevents cropping */
            background-color: #f8f9fa; /* Optional: adds a subtle background for padding spaces */
            transition: transform 0.8s ease;
        }

        .course-card-vertical:hover .card-image-wrapper img {
            transform: scale(1.1);
        }

        /* نشان (Badge) شناور روی عکس */
        .grade-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            z-index: 2;
        }

        /* بخش جزئیات با تراز بندی دقیق */
        .card-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(to bottom, #ffffff, #fcfcfc);
        }

        .course-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #2d3436;
            line-height: 1.6;
            margin-bottom: 15px;
            height: 52px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* استایل قیمت مدرن */
        .price-container {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }

        .price-value {
            font-size: 1.2rem;
            font-weight: 900;
            color: #00b894;
        }

        /* دکمه‌های شیک و متمایز */
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-view {
            flex: 2;
            background: #2d3436;
            color: white !important;
            border-radius: 15px;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            text-decoration: none;
            transition: 0.3s;
            font-size: 14px;
        }

        .btn-view:hover {
            background: #000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-add-cart {
            flex: 1;
            background: #ff7675;
            color: white !important;
            height: 48px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .btn-add-cart i { font-size: 20px; transition: 0.3s; }
        .btn-add-cart .cart-text {
            position: absolute;
            right: -100px;
            font-size: 12px;
            font-weight: bold;
            transition: 0.3s;
        }

        /* هاور دکمه سبد خرید که متن رو هل میده بیرون */
        .btn-add-cart:hover {
            flex: 2.5;
            background: #d63031;
        }

        .btn-add-cart:hover i { transform: translateX(-35px); }
        .btn-add-cart:hover .cart-text { right: 20px; }
        /* تنظیمات اختصاصی برای نمایشگرهای کوچک (گوشی) */
        @media (max-width: 576px) {
            .results-area-style {
                padding: 0 15px; /* ایجاد فاصله از لبه‌های گوشی */
            }

            .course-card-vertical {
                margin-bottom: 20px;
                /* می‌توانید ارتفاع عکس را در موبایل کمی کمتر کنید تا جای زیادی نگیرد */
            }

            .card-image-wrapper {
                height: 180px;
            }

            .course-title {
                font-size: 1rem; /* فونت کمی کوچک‌تر برای موبایل */
            }
        }
        @media (max-width: 576px) {
            .btn-add-cart {
                flex: 1.5 !important;
            }
            .btn-add-cart i {
                transform: translateX(-30px);
            }
            .btn-add-cart .cart-text {
                right: 15px !important;
                opacity: 1 !important;
            }
        }

        .price-models-container {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .price-model-card {
            flex: 1;
            background: #fff;
            border: 2px solid #eee;
            border-radius: 14px;
            padding: 16px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .price-model-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }

        .price-model-card.selected {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2) !important;
            transform: scale(1.03);
        }

        .price-model-with { border-color: #28a745; }
        .price-model-without { border-color: #007bff; }

        .price-model-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            opacity: 0.85;
        }

        .price-model-card.disabled-card {
            opacity: 0.7;
            cursor: not-allowed !important;
            pointer-events: none;
            border-style: dashed !important;
        }
    </style>
</head>

<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<div class="page">
    <?php
    include_once 'menu.php';
    include_once 'header.php';

    if ($_SESSION['auth']['keyactive'] != NULL and $_SESSION['auth']['truekey'] == 0) {
        // بخش کد تایید
        ?>
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row row-sm">
                                <div class="col-6">
                                    <div class="card-item-title">
                                        <label class="main-content-label tx-13 font-weight-bold mb-2">کد تایید شما</label>
                                        <span class="d-block tx-12 mb-0 text-muted">این کد را در اختیار مسئول ثبت نام خود قرار دهید</span>
                                    </div>
                                    <p class="mb-0 tx-30 mt-2"><b class="text-primary"><?php echo $_SESSION['auth']['keyactive'] ?></b></p>
                                </div>
                                <div class="col-6">
                                    <img src="assets/img/pngs/work11.png" class="best-emp">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        if (!isset($_GET['IdCourse'])) {
            ?>
            <div class="main-content side-content pt-0">
                <div class="container-fluid">
                    <div class="inner-body">
                        <div class="courses-wrapper w-100 pt-3" style="direction: rtl; text-align: right;">

                            <?php
                            $instGroups = inst_level_groups();
                            $instMulti  = count($instGroups) > 1;
                            $gi = 0;
                            ?>
                            <?php if ($instMulti) { ?>
                                <h6 class="mb-3 text-muted" style="font-weight: bold;"><?php echo m('picker.step1') ?></h6>
                                <div class="top-nav-container">
                                    <div class="main-tabs">
                                        <?php foreach ($instGroups as $gslug => $ginfo) { ?>
                                            <button class="btn-main" onclick="switchGroup('grp<?php echo $gi ?>', this)"><?php echo htmlspecialchars($ginfo['label'], ENT_QUOTES, 'UTF-8') ?></button>
                                            <?php $gi++;
                                        } ?>
                                    </div>
                                </div>
                            <?php } ?>

                            <div id="grade-section" style="display: <?php echo $instMulti ? 'none' : 'block' ?>; margin-top: 30px;">
                                <h6 class="mb-3 text-muted" style="font-weight: bold;"><?php echo $instMulti ? m('picker.step2') : m('picker.step1') ?></h6>
                                <div id="grade-cards-container">
                                    <?php $gi = 0;
                                    foreach ($instGroups as $gslug => $ginfo) { ?>
                                        <div id="grp<?php echo $gi ?>" class="grade-group<?php echo $instMulti ? '' : ' active' ?>">
                                            <?php $ci = 0;
                                            foreach ($ginfo['items'] as $item) {
                                                $ci++;
                                                $face = ($item['value'] !== null && $item['value'] !== '')
                                                    ? inst_fa_digits($item['value'])
                                                    : $item['label'];
                                                ?>
                                                <div class="grade-card grade-c<?php echo (($ci - 1) % 9) + 1 ?>"
                                                     onclick="fetchCourses(<?php echo json_encode($item['slug'], JSON_UNESCAPED_UNICODE) ?>)">
                                                    <span><?php echo htmlspecialchars($face, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <small><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </div>
                                            <?php } ?>
                                        </div>
                                        <?php $gi++;
                                    } ?>
                                </div>
                            </div>

                            <div id="courses-section" style="display: none; margin-top: 40px;">
                                <h5 id="dynamic-course-title" class="mb-4 text-primary" style="font-weight: 900; border-right: 4px solid; padding-right: 10px;">
                                    <?php echo m('label.educational_courses') ?>
</h5>
                                <div id="results-area" class="results-area-style">
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            // بخش جزئیات پایه (IdCourse)
            if ($_GET['IdCourse'] != '' && Getcourse($_GET['IdCourse'])) {
                $idcourse = intval($_GET['IdCourse']);
                $sql1 = 'SELECT * FROM codtbl_course WHERE codecourse=? ';
                $query1 = $conn->prepare($sql1);
                $query1->execute([$idcourse]);
                $tri = $query1->fetch();
                ?>
                <div class="main-content side-content pt-0">
                    <div class="container-fluid">
                        <div class="inner-body">
                            <div class="row row-sm">
                                <div class="col-lg-12 col-md-12 pd-t-30">
                                    <div class="card custom-card productdesc">
                                        <div class="card-body h-100">
                                            <div class="row">
                                                <?php if (isset($verify) && $verify == 100) { ?>
                                                    <div class="alert alert-success w-100" role="alert">
                                                        طرح مورد نظر به سبد خرید افزوده شد. <a href="checkout.php" class="btn btn-success btn-sm">مشاهده سبد</a>
                                                    </div>
                                                <?php } ?>

                                                <div class="col-xl-5 col-lg-12 col-md-12">
                                                    <div class="product-carousel">
                                                        <img src="<?php echo $tri['piclink'] ?>" class="img-fluid mx-auto d-block">
                                                        <div class="text-center mt-4 mb-4 btn-list">
                                                            <?php
                                                            if (!checkactCourse($_SESSION['auth']['user'], $idcourse)) {
                                                                if (checkout($_SESSION['auth']['user'], $idcourse)) { ?>
                                                                    <a href="?addcheckout=<?php echo $idcourse ?>" class="btn ripple btn-success">افزودن به سبد خرید</a>
                                                                <?php } else { ?>
                                                                    <a href="?del=<?php echo $idcourse ?>" class="btn ripple btn-secondary">حذف از سبد</a>
                                                                    <a href="checkout.php" class="btn ripple btn-primary">مشاهده و خرید</a>
                                                                <?php }
                                                            } ?>
                                                            <a href="<?php echo $tri['detail_link'] ?>" class="btn ripple btn-info" target="_blank">توضیحات بیشتر</a>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-xl-7 col-lg-12 col-md-12">
                                                    <div class="mt-4 mb-4">
                                                        <h4 class="mt-1 mb-3"><?php echo htmlentities($tri['coursename']) ?></h4>
                                                        <h5 class="mb-2">قیمت: <b class="text-success"><?php echo number_format($tri['coursefee']); ?> تومان</b></h5>
                                                        <p class="tx-13 text-muted"><?php echo htmlentities($tri['coursemuted']); ?></p>
                                                        <h6 class="mt-4 fs-16">توضیحات کامل:</h6>
                                                        <p><?php echo $tri['coursemeta'] ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            } else {
                echo '<script>window.location.href="course.php"</script>';
            }
        }
    }
    ?>

    <?php include 'footers.php'; ?>
</div>
<script>
    function switchGroup(groupId, element) {
        // نمایش بخش انتخاب پایه و مخفی کردن بخش محصولات قبلی
        document.getElementById('grade-section').style.display = 'block';
        document.getElementById('courses-section').style.display = 'none';

        // مدیریت نمایش گروه پایه‌ها (مثل قبل)
        const allGroups = document.querySelectorAll('.grade-group');
        allGroups.forEach(group => {
            group.classList.remove('active');
            group.style.display = 'none';
        });

        const activeGroup = document.getElementById(groupId);
        if (activeGroup) {
            activeGroup.classList.add('active');
            activeGroup.style.display = 'flex';
        }

        // استایل دکمه فعال
        const allTabs = document.querySelectorAll('.btn-main');
        allTabs.forEach(tab => tab.classList.remove('active'));
        element.classList.add('active');
    }
    function fetchCourses(gradeName) {
        // اول مطمئن شویم بخش‌ها نمایش داده می‌شوند
        $("#grade-section").show(); // نمایش بخش انتخاب پایه
        $("#courses-section").show(); // نمایش بخش محصولات

        // تغییر متن تیتر سوم
        $("#dynamic-course-title").text(<?php echo json_encode(m('picker.courses_of'), JSON_UNESCAPED_UNICODE) ?> + gradeName);

        // درخواست Ajax ساده و مستقیم
        $.ajax({
            url: 'fetch_courses.php', // اگه فایل دقیقاً کنار همین فایل هست، این آدرس درسته
            type: 'GET',
            data: { grade: gradeName },
            success: function(data) {
                $('#results-area').html(data);
            },
            error: function() {
                // اگر پیدا نشد، این مسیر رو تست کن (برای لوکال هاست sky)
                $.ajax({
                    url: '/sky/fetch_courses.php',
                    type: 'GET',
                    data: { grade: gradeName },
                    success: function(data) {
                        $('#results-area').html(data);
                    }
                });
            }
        });
    }

    let selectedModels = {};

    function selectPriceModel(codecourse, modelType, element) {
        // Target container of THIS specific card only
        const cardContainer = element.closest('.course-card-vertical');

        // Deselect existing items in this card
        if (cardContainer) {
            cardContainer.querySelectorAll('.price-model-card').forEach(el => {
                el.classList.remove('selected');
            });
        }

        // Select clicked card
        element.classList.add('selected');
        selectedModels[codecourse] = modelType;

        // Show action button
        const addBtn = document.getElementById(`add-btn-${codecourse}`);
        if (addBtn) addBtn.style.display = 'flex';
    }

    function addToCart(codecourse, withPrice, withoutPrice) {
        const modelType = selectedModels[codecourse] || 'with';

        $.ajax({
            url: 'course.php',
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                addcheckout: codecourse,
                priceModel: modelType
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('محصول با موفقیت به سبد خرید اضافه شد');
                    // Reloads page immediately after user acknowledges alert
                    window.location.reload();
                } else {
                    // Displays specific backend error (e.g., "already in cart")
                    alert(response.message || 'خطا در ثبت درخواست');
                }
            },
            error: function(xhr, status, error) {
                console.log(xhr.responseText);
                alert('خطا در ارتباط با سرور');
            }
        });
    }
</script>
</body>
</html>