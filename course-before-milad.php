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
//* End Check Session JUST ONE Session
if ($_SESSION['auth']['role'] != 1) {
    header('location: index.php');
    exit;
}
include_once 'temp.html';
if (isset($_GET['addcheckout']) and $_GET['addcheckout'] != '') {
    if (checkout($_SESSION['auth']['user'], $_GET['addcheckout'])) {
        if (getfee($_GET['addcheckout']) != false) {
            if (!checkactCourse($_SESSION['auth']['user'], $_GET['addcheckout'])) {
                $fee = getfee($_GET['addcheckout']);
                $add_cart = 'INSERT INTO checkout (username,coursecode,fee) VALUES (?,?,?)';
                $incart = $conn->prepare($add_cart);
                $incart->execute([$_SESSION['auth']['user'], $_GET['addcheckout'], $fee]);
                $_SESSION['massege'] = 100;
                header('location: course.php');
                die();
            } else {
                $_SESSION['massege'] = 31;
                header('location: course.php');
                die();
            }
        } else {
            $_SESSION['massege'] = 37;
            header('location: course.php');
            die();
        }
    } else {
        $_SESSION['massege'] = 53;
        header('location: course.php');
        die();
    }
}
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
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> طرح ها و دوره ها | <?php echo __TITLE ?></title>
    <?php include_once 'inc/link.php'; ?>
    <!-- <style>
        .category-title {
            font-size: 20px;
            font-weight: 700;
            margin: 45px 0 20px;
        }

        .course-carousel {
            display: flex;
            overflow-x: auto;
            gap: 15px;
            cursor: grab;
            user-select: none;
            padding-bottom: 15px;
        }

        .course-carousel::-webkit-scrollbar {
            display: none;
        }

        .course-item {
            flex: 0 0 calc(10px);
            max-width: calc(100% - 10px);
        }
    </style> -->
    <style>
        .category-title {
            font-size: 20px;
            font-weight: 700;
            margin: 45px 0 20px;
            padding: 45px 0 20px;
        }

        .course-carousel {
            display: flex;
            overflow-x: auto;
            gap: 15px;
            cursor: grab;
            user-select: none;
            padding-bottom: 15px;
        }

        .course-carousel:active {
            cursor: grabbing;
        }

        .course-carousel::-webkit-scrollbar {
            display: none;
        }

        .course-item {
            display: flex;
            flex: 0 0 calc(33.333% - 10px);
            max-width: calc(33.333% - 10px);
        }

        .course-item .card {
            height: 100%;
        }

        @media (max-width: 991px) {
            .course-item {
                flex: 0 0 calc(50% - 10px);
                max-width: calc(50% - 10px);
            }
        }

        @media (max-width: 576px) {
            .course-item {
                flex: 0 0 90%;
                max-width: 90%;
            }
        }

        .course-carousel img,
        .course-carousel a {
            pointer-events: auto;
            user-drag: none;
            -webkit-user-drag: none;
        }

        .course-carousel {
            touch-action: pan-y;
        }

        .product-grid {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .product-image img {
            height: 200px;
            object-fit: cover;
        }

        .product-content .text-muted {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            /* تعداد خطوط */
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 40px;
        }

        .product-content {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .product-content .price {
            margin-top: auto;
        }
    </style>
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

        if ($_SESSION['auth']['keyactive'] != NULL and $_SESSION['auth']['truekey'] == 0) {
            $sql = 'SELECT keyactive FROM dblogin WHERE username=?';
            $query = $conn->prepare($sql);
            $query->execute([$_SESSION['auth']['user']]);
            $key = $query->fetch(); ?>
            <div class="main-content side-content pt-0">
                <div class="container-fluid">
                    <div class="inner-body">
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
                                            href="<?php echo url('Profile/user.php') ?>" class="btn btn-primary"> حساب کاربری</a>، اطلاعات خود را بررسی و ویرایش نمایید</span>
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
                            <div class="row row-sm">

                                <?php
                                $categories = [
                                    'ابتدایی' => ['چهارم', 'پنجم', 'ششم'],
                                    'متوسطه اول' => ['هفتم', 'هشتم', 'نهم'],
                                    'متوسطه دوم' => ['دهم', 'یازدهم', 'دوازدهم'],
                                    'المپیاد' => ['المپیاد']
                                ];

                                $demoSql = 'SELECT * FROM course_demo';
                                $demoStmt = $conn->prepare($demoSql);
                                $demoStmt->execute();
                                $demos = $demoStmt->fetchAll(PDO::FETCH_OBJ);

                                foreach ($categories as $title => $grades):

                                    $likes = [];
                                    $params = [];

                                    foreach ($grades as $g) {
                                        $likes[] = "paye LIKE ?";
                                        $params[] = "%$g%";
                                    }

                                    $sql = "
                                        SELECT * FROM codtbl_course
                                        WHERE (" . implode(' OR ', $likes) . ")
                                        AND courseactive = 1
                                        ORDER BY idcourse DESC
                                    ";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    $courses = $stmt->fetchAll(PDO::FETCH_OBJ);

                                    if (!$courses) continue;
                                ?>

                                    <!-- 🔹 عنوان دسته -->
                                    <div class="col-12">
                                        <div class="category-title"><?php echo $title; ?></div>
                                    </div>

                                    <!-- 🔹 کاروسل -->
                                    <div class="col-12">
                                        <div class="course-carousel">

                                            <?php foreach ($courses as $fr): ?>
                                                <div class="course-item">
                                                    <div class="card custom-card">
                                                        <div class="p-0 ht-100p">
                                                            <div class="product-grid">

                                                                <div class="product-image">
                                                                    <a href="course.php?IdCourse=<?php echo $fr->codecourse ?>" class="image">
                                                                        <img class="pic-1" src="<?php echo $fr->piclink ?>">
                                                                    </a>

                                                                    <span class="product-discount-label bg-primary">
                                                                        <?php echo $fr->paye ?>
                                                                    </span>

                                                                    <div class="product-link">
                                                                        <a href="?addcheckout=<?php echo $fr->codecourse ?>">
                                                                            <i class="fa fa-shopping-cart"></i>
                                                                            <span>افزودن به سبد خرید</span>
                                                                        </a>
                                                                        <a href="course.php?IdCourse=<?php echo $fr->codecourse ?>">
                                                                            <i class="fas fa-eye"></i>
                                                                            <span>اطلاعات بیشتر</span>
                                                                        </a>
                                                                    </div>
                                                                </div>

                                                                <div class="product-content">
                                                                    <h3 class="title text-primary">
                                                                        <a href="course.php?IdCourse=<?php echo $fr->codecourse ?>">
                                                                            <?php echo htmlentities($fr->coursename) ?>
                                                                        </a>
                                                                    </h3>

                                                                    <div class="text-muted">
                                                                        <?php echo htmlentities($fr->coursemuted) ?>
                                                                    </div>

                                                                    <div class="price">
                                                                        <span class="text-danger">
                                                                            <?php echo number_format($fr->coursefee) ?> تومان
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>

                                        </div>
                                    </div>

                                <?php endforeach; ?>

                            </div>
                        </div>

                    </div>
                </div>
                <?php } else {
                if ($_GET['IdCourse'] != '') {
                    if (Getcourse($_GET['IdCourse'])) {
                        $idcourse = intval($_GET['IdCourse']);
                ?>
                        <div class="main-content side-content pt-0">
                            <div class="container-fluid">
                                <div class="inner-body">
                                    <?php $sql1 = 'SELECT * FROM codtbl_course WHERE codecourse=? ';
                                    $query1 = $conn->prepare($sql1);
                                    $query1->execute([$idcourse]);
                                    $tri = $query1->fetch();
                                    ?>
                                    <!-- Row -->
                                    <div class="row row-sm">
                                        <div class="col-lg-12 col-md-12 pd-t-30">
                                            <div class="card custom-card productdesc ">
                                                <div class="card-body h-100">
                                                    <div class="row">
                                                        <?php if (isset($verify) and $verify == 100) { ?>
                                                            <div class="alert alert-success" role="alert">
                                                                <span class="alert-inner--icon"><i
                                                                        class="fa fa-shopping-cart"></i></span>
                                                                <button aria-label="بستن" class="close"
                                                                    data-bs-dismiss="alert"
                                                                    type="button">
                                                                    <span aria-hidden="true">×</span>
                                                                </button>
                                                                طرح/ دوره مورد نظر به سبد خرید افزوده شد
                                                                <a href="checkout.php">
                                                                    <button class="btn ripple btn-success pd-x-20"> مشاهده
                                                                        سبد
                                                                        خرید
                                                                    </button>
                                                                </a>
                                                            </div>
                                                        <?php
                                                        }
                                                        if (isset($verify) and $verify == 53) { ?>
                                                            <div class="alert alert-danger" role="alert">
                                                                <span class="alert-inner--icon"><i
                                                                        class="fa fa-shopping-cart"></i></span>
                                                                <button aria-label="بستن" class="close"
                                                                    data-bs-dismiss="alert"
                                                                    type="button">
                                                                    <span aria-hidden="true">×</span>
                                                                </button>
                                                                امکان افزودن مجدد این طرح / دوره به سبد خرید وجود ندارد
                                                            </div>
                                                        <?php
                                                        }
                                                        if (isset($verify) and $verify == 37) { ?>
                                                            <div class="alert alert-danger" role="alert">
                                                                <span class="alert-inner--icon"><i
                                                                        class="fa fa-shopping-cart"></i></span>
                                                                <button aria-label="بستن" class="close"
                                                                    data-bs-dismiss="alert"
                                                                    type="button">
                                                                    <span aria-hidden="true">×</span>
                                                                </button>
                                                                این دوره وجود ندارد یا فعال نیست
                                                            </div>
                                                        <?php
                                                        } ?>
                                                        <div class="col-xl-5 col-lg-12 col-md-12">
                                                            <div class="row">
                                                                <div class="col-md-7 offset-md-1 col-sm-9 col-xl-10">
                                                                    <div class="product-carousel">
                                                                        <div id="carousel" class="carousel slide"
                                                                            data-ride="false">
                                                                            <div class="carousel-inner">
                                                                                <div class="carousel-item active"><img
                                                                                        src="<?php echo $tri['piclink'] ?>"
                                                                                        alt="img"
                                                                                        class="img-fluid mx-auto d-block">
                                                                                    <div class="text-center mt-4 mb-4 btn-list">
                                                                                        <?php
                                                                                        if (!checkactCourse($_SESSION['auth']['user'], $idcourse)) {
                                                                                            if (checkout($_SESSION['auth']['user'], $idcourse)) {
                                                                                                $mass = 1; ?>
                                                                                                <a href="?addcheckout=<?php echo $idcourse ?> "
                                                                                                    class="btn ripple btn-success">
                                                                                                    <i
                                                                                                        class="fe fe-shopping-cart"> </i>
                                                                                                    افزودن به سبد خرید </a>
                                                                                            <?php } else {
                                                                                                $mass = 2; ?>
                                                                                                <a href="?del=<?php echo $idcourse ?>"
                                                                                                    class="btn ripple btn-secondary">
                                                                                                    <i
                                                                                                        class="fe fe-trash"> </i>
                                                                                                    حذف از سبد خرید </a>
                                                                                                <a href="<?php echo url('checkout.php') ?>"
                                                                                                    class="btn ripple btn-primary">
                                                                                                    <i
                                                                                                        class="fe fe-shopping-cart"> </i>
                                                                                                    مشاهده و خرید </a>
                                                                                        <?php }
                                                                                        } else {
                                                                                            $mass = 3;
                                                                                        }
                                                                                        ?>
                                                                                        <a href="<?php echo $tri['detail_link'] ?>"
                                                                                            class="btn ripple btn-info"
                                                                                            target="_blank">
                                                                                            <i class="fe fe-info"> </i>
                                                                                            توضیحات بیشتر </a>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-7 col-lg-12 col-md-12">
                                                            <div class="mt-4 mb-4">
                                                                <h4 class="mt-1 mb-3"><?php echo htmlentities($tri['coursename']) ?></h4>
                                                                <h5 class="mb-2 pd-t-5 ">قیمت: <b
                                                                        class="text-success"><?php echo number_format($tri['coursefee']); ?>
                                                                        تومان </b></h5>
                                                                <p class="tx-13 text-muted"><?php echo htmlentities($tri['coursemuted']); ?></p>
                                                                <?php if (isset($mass) and $mass == 2) {
                                                                    echo '
                                                        <span class="tx-13 text-warning mb-2"><i class="fe fe-bell"></i>
                                                            این (دوره/طرح) به سبد خرید شما
                                                            اضافه شده است.</span>';
                                                                }
                                                                if (isset($mass) and $mass == 3) {
                                                                    echo '
                                                        <p class="tx-13 text-danger mb-2"><i class="ti ti-info-alt"></i>
                                                            شما قبلا این دوره را ثبت نام
                                                            کردید.</p>';
                                                                }
                                                                if (isset($mass) and $mass == 1) {
                                                                    echo '
                                                        <p class="tx-13 text-success mb-2"><i class="fa fa-check"></i>
                                                            ثبت نام فعال است</p>';
                                                                } ?>
                                                                <h6 class="mt-4 fs-16">توضیحات:</h6>
                                                                <p><?php echo $tri['coursemeta'] ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End Row -->
                                </div>
                            </div>
                        </div>
        <?php
                    } else {
                        echo '<script>window.location.href="course.php"</script>';
                    }
                } else {
                    echo '<script>window.location.href="course.php"</script>';
                }
            }
        }
        ?>
        <!-- Main Footer-->
        <?php include 'footers.php'; ?>
        <!--End Footer-->
        <!-- <script>
            const slider = document.getElementById('gradeSlider');
            let isDown = false,
                startX, scrollLeft;

            slider.addEventListener('mousedown', e => {
                isDown = true;
                startX = e.pageX - slider.offsetLeft;
                scrollLeft = slider.scrollLeft;
            });
            ['mouseleave', 'mouseup'].forEach(e => slider.addEventListener(e, () => isDown = false));
            slider.addEventListener('mousemove', e => {
                if (!isDown) return;
                e.preventDefault();
                slider.scrollLeft = scrollLeft - (e.pageX - startX) * 2;
            });

            document.querySelectorAll('.grade-item').forEach(i => {
                i.addEventListener('click', () => {
                    const f = document.createElement('form');
                    f.method = 'POST';
                    const inp = document.createElement('input');
                    inp.name = 'search-paye';
                    inp.value = i.dataset.grade;
                    f.appendChild(inp);
                    document.body.appendChild(f);
                    f.submit();
                });
            });
        </script> -->
        <script>
            document.querySelectorAll('.course-carousel').forEach(carousel => {

                let isDown = false;
                let startX = 0;
                let scrollLeft = 0;
                let dragged = false;

                carousel.addEventListener('mousedown', e => {
                    if (e.button !== 0) return;
                    isDown = true;
                    dragged = false;
                    startX = e.pageX;
                    scrollLeft = carousel.scrollLeft;
                    carousel.classList.add('dragging');
                    e.preventDefault();
                });

                window.addEventListener('mouseup', () => {
                    isDown = false;
                    setTimeout(() => dragged = false, 80);
                    carousel.classList.remove('dragging');
                });

                carousel.addEventListener('mousemove', e => {
                    if (!isDown) return;
                    e.preventDefault();
                    dragged = true;
                    const x = e.pageX;
                    const walk = (x - startX) * 1.6;
                    carousel.scrollLeft = scrollLeft - walk;
                });

                carousel.addEventListener('mouseleave', () => {
                    isDown = false;
                    carousel.classList.remove('dragging');
                });

                /* ⛔ جلوگیری کامل از کلیک موقع درگ */
                carousel.addEventListener('click', e => {
                    if (dragged) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }, true);

                /* 📱 Touch */
                carousel.addEventListener('touchstart', e => {
                    startX = e.touches[0].pageX;
                    scrollLeft = carousel.scrollLeft;
                }, {
                    passive: true
                });

                carousel.addEventListener('touchmove', e => {
                    const x = e.touches[0].pageX;
                    const walk = (x - startX) * 1.6;
                    carousel.scrollLeft = scrollLeft - walk;
                }, {
                    passive: true
                });

            });
        </script>

</body>

</html>