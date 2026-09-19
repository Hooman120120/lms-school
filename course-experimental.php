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

if ($_SESSION['auth']['ip'] !== GetIp() && $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: login.php');
    die();
}

$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: login.php');
    die();
}

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
                header('location: course-experimental.php');
                die();
            } else {
                $_SESSION['massege'] = 31;
                header('location: course-experimental.php');
                die();
            }
        } else {
            $_SESSION['massege'] = 37;
            header('location: course-experimental.php');
            die();
        }
    } else {
        $_SESSION['massege'] = 53;
        header('location: course-experimental.php');
        die();
    }
}
if (isset($_SESSION['massege'])) {
    $verify = $_SESSION['massege'];
    unset($_SESSION['massege']);
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
    <title>دوره‌ها | <?php echo __TITLE ?></title>
    <?php include_once 'inc/link.php'; ?>

    <style>
        .grade-slider-wrapper {
            overflow: hidden;
            margin-bottom: 25px;
        }

        .grade-slider {
            display: flex;
            gap: 12px;
            cursor: grab;
            user-select: none;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding-bottom: 10px;
        }

        .grade-slider::-webkit-scrollbar {
            display: none;
        }

        .grade-item {
            flex: 0 0 auto;
            padding: 10px 22px;
            background: #f1f1f1;
            border-radius: 30px;
            font-size: 14px;
            white-space: nowrap;
            transition: .2s;
        }

        .grade-item:hover,
        .grade-item.active {
            background: #007bff;
            color: #fff;
        }
    </style>
</head>

<body class="main-body leftmenu">

    <div class="page">
        <?php include_once 'menu.php';
        include_once 'header.php'; ?>

        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
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
                    <!-- 🔹 Grade Carousel -->
                    <div class="grade-slider-wrapper mt-3">
                        <div class="grade-slider" id="gradeSlider">
                            <?php
                            $grades = ['', 'ابتدایی', 'متوسطه_اول', 'متوسطه_دوم', 'المپیاد'];
                            $selected = $_POST['search-paye'] ?? '';
                            foreach ($grades as $g) {
                                $active = ($g === $selected) ? 'active' : '';
                                echo "<div class='grade-item $active' data-grade='$g'>" . ($g ?: 'همه') . "</div>";
                            }
                            ?>
                        </div>
                    </div>

                    <?php
                    $pro = !empty($_POST['search-paye']) ? '%' . $_POST['search-paye'] . '%' : '%%';
                    $active = 1;

                    $sql = "SELECT * FROM codtbl_course 
        WHERE paye LIKE :pro AND courseactive=:active 
        ORDER BY idcourse DESC";

                    $q = $conn->prepare($sql);
                    $q->bindParam(':pro', $pro);
                    $q->bindParam(':active', $active);
                    $q->execute();
                    $courses = $q->fetchAll(PDO::FETCH_OBJ);
                    ?>

                    <div class="row row-sm">
                        <?php foreach ($courses as $fr): ?>
                            <div class="col-md-6 col-lg-4 col-xl-3 col-sm-6">
                                <div class="card custom-card">
                                    <div class="product-grid">
                                        <div class="product-image">
                                            <a href="course.php?IdCourse=<?php echo $fr->codecourse ?>">
                                                <img class="pic-1" src="<?php echo $fr->piclink ?>">
                                            </a>
                                            <span class="product-discount-label bg-primary"><?php echo $fr->paye ?></span>
                                            <div class="product-link">
                                                <a href="?addcheckout=<?php echo $fr->codecourse ?>">
                                                    <i class="fa fa-shopping-cart"></i>
                                                    <span>افزودن به سبد خرید</span>
                                                </a>
                                                <a href="course.php?IdCourse=<?php echo $fr->codecourse ?>">
                                                    <i class="fas fa-eye"></i>
                                                    <span>اطلاعات بیشتر </span>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="product-content">
                                            <h3 class="title">
                                                <a href="course.php?IdCourse=<?php echo $fr->codecourse ?>">
                                                    <?php echo htmlentities($fr->coursename) ?>
                                                </a>
                                            </h3>
                                            <div class="text-muted"><?php echo htmlentities($fr->coursemuted) ?></div>
                                            <div class="price text-danger">
                                                <?php echo number_format($fr->coursefee) ?> تومان
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </div>

        <?php include 'footers.php'; ?>
    </div>

    <script>
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
    </script>

</body>

</html>