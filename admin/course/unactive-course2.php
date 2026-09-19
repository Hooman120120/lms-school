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
if (!Permision('permision_viewcourse')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if(isset($_GET['disable'])){
    $disable='UPDATE codtbl_course SET courseactive=? WHERE codecourse=?';
    $qupd=$conn->prepare($disable);
    $qupd->execute([0,$_GET['disable']]);
    header('location: ' . url('admin/course/unactive-course.php'));
    die();
}
if(isset($_GET['enable'])){
    $disable='UPDATE codtbl_course SET courseactive=? WHERE codecourse=?';
    $qupd=$conn->prepare($disable);
    $qupd->execute([1,$_GET['enable']]);
    header('location: ' . url('admin/course/unactive-course.php'));
    die();
}
if(isset($_GET['active'])){
    $disable='UPDATE codtbl_course SET activecourse=? WHERE codecourse=?';
    $qupd=$conn->prepare($disable);
    $qupd->execute([1,$_GET['active']]);
    header('location: ' . url('admin/course/unactive-course.php'));
    die();
}
if(isset($_GET['close'])) {
    if (Permision('permision_deletcourse')) {
        include_once '../../quizconfig.php';
        include_once '../../inc/function-quiz.php';
        if(!checkEntityGroupQuiz($_GET['close'])) {
            $delgroups = 'DELETE FROM `_ugroups` WHERE ugtitle=?';
            $dquiz = $ponn->prepare($delgroups);
            $dquiz->execute([$_GET['close']]);
        }
        //
        $disable = 'DELETE FROM  codtbl_course  WHERE codecourse=?';
        $qupd = $conn->prepare($disable);
        $qupd->execute([ $_GET['close']]);
        header('location: ' . url('admin/course/unactive-course.php'));
        die();
    }
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> بایگانی سال‌های قبل | <?php echo __TITLE ?></title>
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
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div style="padding:10px;"><style>
                        .custom-tabs .nav-link {
                            border: none;
                            border-radius: 50px;
                            margin-right: 10px;
                            padding: 10px 25px;
                            font-weight: bold;
                            color: #555;
                            background: linear-gradient(145deg, #f0f0f0, #e0e0e0);
                            transition: all 0.3s ease;
                            box-shadow: 3px 3px 8px rgba(0,0,0,0.1);
                        }

                        .custom-tabs .nav-link:hover {
                            background: linear-gradient(145deg, #4e73df, #1cc88a);
                            color: #fff;
                            transform: translateY(-3px);
                            box-shadow: 5px 5px 15px rgba(0,0,0,0.2);
                        }

                        .custom-tabs .nav-link.active {
                            background: linear-gradient(145deg, #1cc88a, #36b9cc);
                            color: #fff;
                            font-size: 1.05rem;
                            box-shadow: 5px 5px 15px rgba(0,0,0,0.25);
                        }
                    </style>
                    <ul class="nav nav-tabs mb-3 custom-tabs" id="courseTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="type1-tab" data-bs-toggle="tab" data-bs-target="#type1" type="button" role="tab">
                                آموزشی
                            </button>
                        </li>
                        <?php
                        $currentUser = $_SESSION['admin']['user'];
                        if (PermisionUser('skill_permission', $currentUser)): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="type2-tab" data-bs-toggle="tab" data-bs-target="#type2" type="button" role="tab">
                                    مهارتی
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="row row-sm">
                    <div class="tab-content custom-tab-pane">
                        <!-- Type 1 tab -->
                        <div class="tab-pane fade show active" id="type1" role="tabpanel">

                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-2">
                                        <div>
                                            <h6 class="main-content-label mb-1">بایگانی سال‌های قبل </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-2">
                                        <a href="?paye=all" class="btn btn-info shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'all') ? 'active' : ''; ?>">
                                            همه
                                        </a>
                                    </div>

                                    <div class="col-8">
                                        <!-- Elementary -->
                                        <div class="btn-group dropdown me-2 mb-3">
                                            <a href="?paye=ابتدایی" class="btn btn-info shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'ابتدایی') ? 'active' : ''; ?>">
                                                <i class="fa fa-graduation-cap"></i> گروه ابتدایی
                                            </a>
                                            <button type="button" class="btn btn-info shadow rounded dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'اول') ? 'active' : ''; ?>" href="?paye=اول">کلاس اول دبستان</a></li>
                                                <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوم') ? 'active' : ''; ?>" href="?paye=دوم">کلاس دوم دبستان</a></li>
                                                <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'چهارم') ? 'active' : ''; ?>" href="?paye=چهارم">کلاس چهارم دبستان</a></li>
                                                <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'پنجم') ? 'active' : ''; ?>" href="?paye=پنجم">کلاس پنجم دبستان</a></li>
                                                <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'ششم') ? 'active' : ''; ?>" href="?paye=ششم">کلاس ششم دبستان</a></li>
                                            </ul>
                                        </div>

                                        <!-- Middle School -->
                                        <div class="btn-group dropdown me-2 mb-3">
                                            <a href="?paye=متوسطه_اول" class="btn btn-success shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'متوسطه_اول') ? 'active' : ''; ?>">
                                                <i class="fa fa-book"></i> متوسطه اول
                                            </a>
                                            <button type="button" class="btn btn-success shadow rounded dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'هفتم') ? 'active' : ''; ?>" href="?paye=هفتم">هفتم</a></li>
                                                <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'هشتم') ? 'active' : ''; ?>" href="?paye=هشتم">هشتم</a></li>
                                                <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'نهم') ? 'active' : ''; ?>" href="?paye=نهم">نهم</a></li>
                                            </ul>
                                        </div>

                                        <!-- High School -->
                                        <div class="btn-group dropdown me-2 mb-3">
                                            <a href="?paye=متوسطه_دوم" class="btn btn-warning shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'متوسطه_دوم') ? 'active' : ''; ?>">
                                                <i class="fa fa-star"></i> متوسطه دوم
                                            </a>
                                            <button type="button" class="btn btn-warning shadow rounded dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <!-- دهم -->
                                                <li class="dropstart d-flex flex-row-reverse">
                                                    <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم') ? 'active' : ''; ?>" href="?paye=دهم">دهم</a>
                                                    <a class="dropdown-item dropdown-toggle" style="width: 25%;" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"></a>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,ریاضی') ? 'active' : ''; ?>" href="?paye=دهم,ریاضی">ریاضی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,تجربی') ? 'active' : ''; ?>" href="?paye=دهم,تجربی">تجربی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,انسانی') ? 'active' : ''; ?>" href="?paye=دهم,انسانی">انسانی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,فنی') ? 'active' : ''; ?>" href="?paye=دهم,فنی">فنی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,کارودانش') ? 'active' : ''; ?>" href="?paye=دهم,کارودانش">کارودانش</a></li>
                                                    </ul>
                                                </li>
                                                <!-- یازدهم -->
                                                <li class="dropstart d-flex flex-row-reverse">
                                                    <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم') ? 'active' : ''; ?>" href="?paye=یازدهم">یازدهم</a>
                                                    <a class="dropdown-item dropdown-toggle" style="width: 25%;" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"></a>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,ریاضی') ? 'active' : ''; ?>" href="?paye=یازدهم,ریاضی">ریاضی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,تجربی') ? 'active' : ''; ?>" href="?paye=یازدهم,تجربی">تجربی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,انسانی') ? 'active' : ''; ?>" href="?paye=یازدهم,انسانی">انسانی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,فنی') ? 'active' : ''; ?>" href="?paye=یازدهم,فنی">فنی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,کارودانش') ? 'active' : ''; ?>" href="?paye=یازدهم,کارودانش">کارودانش</a></li>
                                                    </ul>
                                                </li>
                                                <!-- دوازدهم -->
                                                <li class="dropstart d-flex flex-row-reverse">
                                                    <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم') ? 'active' : ''; ?>" href="?paye=دوازدهم">دوازدهم</a>
                                                    <a class="dropdown-item dropdown-toggle" style="width: 25%;" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"></a>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,ریاضی') ? 'active' : ''; ?>" href="?paye=دوازدهم,ریاضی">ریاضی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,تجربی') ? 'active' : ''; ?>" href="?paye=دوازدهم,تجربی">تجربی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,انسانی') ? 'active' : ''; ?>" href="?paye=دوازدهم,انسانی">انسانی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,فنی') ? 'active' : ''; ?>" href="?paye=دوازدهم,فنی">فنی</a></li>
                                                        <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,کارودانش') ? 'active' : ''; ?>" href="?paye=دوازدهم,کارودانش">کارودانش</a></li>
                                                    </ul>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>اقدامات</th>
                                            <th>کد پایه</th>
                                            <th>نام پایه</th>
                                            <th> موفق</th>
                                            <th>منتظر</th>
                                            <th>وضعیت ثبت نام</th>
                                            <th>فعالسازی</th>


                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        function filter_paye($conn) {
                                            if (!isset($_GET['paye'])) {
                                                return [];
                                            }
                                            $sql = 'SELECT * FROM codtbl_course WHERE activecourse = ? AND course_type = 0';
                                            $params = [0];
                                            $validPayeValues = ['all', 'ابتدایی', 'متوسطه_اول', 'متوسطه_دوم', 'اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم', 'ریاضی', 'تجربی', 'انسانی', 'فنی', 'کارودانش'];

                                            $paye = $_GET['paye'];
                                            if ($paye !== 'all') {
                                                $payeParts = explode(',', $paye);
                                                foreach ($payeParts as $part) {
                                                    if (!in_array($part, $validPayeValues)) {
                                                        return [];
                                                    }
                                                }
                                                if (count($payeParts) > 1) {
                                                    $sql .= ' AND (paye LIKE ? OR paye LIKE ?)';
                                                    $params[] = '%' . $payeParts[0] . '%';
                                                    $params[] = '%' . $payeParts[1] . '%';
                                                } else {
                                                    $sql .= ' AND paye LIKE ?';
                                                    $params[] = '%' . $paye . '%';
                                                }
                                            }

                                            $sql .= ' ORDER BY idcourse DESC';
                                            $query = $conn->prepare($sql);
                                            $query->execute($params);
                                            return $query->fetchAll(PDO::FETCH_OBJ);
                                        }

                                        $result = filter_paye($conn);
                                        if (count($result) > 0) {
                                            foreach ($result as $res) { ?>
                                                <tr>
                                                    <td class="wd-20">
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false"
                                                                            aria-haspopup="true"
                                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown" type="button">
                                                                        اقدام <i
                                                                            class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-8">
                                                                        <a class="dropdown-item text-success "   href="add-Course.php?edit=<?php echo $res->codecourse ?>&id=<?php echo $res->idcourse ?>" >
                                                                            <i class="fe fe-edit-2"></i>ویرایش پایه
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="movie.php?code=<?php echo $res->codecourse ?>"
                                                                        ><i class="fe fe-film"></i>
                                                                            افزودن فیلم
                                                                        </a>

                                                                        <a class="dropdown-item "   href="addon-doc.php?code=<?php echo $res->codecourse ?>">
                                                                            <i class="fe fe-book-open"></i>
                                                                            افزودن جزوه

                                                                        </a>

                                                                        <div class="dropdown-item disabled  " >
                                                                            <i class="fe fe-cpu"></i>   تنظیم برنامه کلاسی

                                                                        </div>
                                                                        <div class="dropdown-item  disabled">
                                                                            <i class="fa fa-question"></i>   تنظیم برنامه آزمون

                                                                        </div>
                                                                        <div class="dropdown-item disabled ">
                                                                            <i class="fa fa-chalkboard-teacher"> </i><?php echo m('action.choose_teacher2') ?>

                                                                        </div>
                                                                        <a class="dropdown-item"
                                                                           href="list-student.php?code=<?php echo $res->codecourse ?>"
                                                                        ><i class="fa fa-chess-board"></i>
                                                                            لیست دانش آموزان
                                                                        </a>

                                                                        <?php
                                                                        if (Permision('permision_deletcourse')) { ?>
                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?close=<?php echo $res->codecourse ?>"
                                                                               onclick="return confirm('آیا با حذف این پایه موافقید موافقید؟')"
                                                                            ><i class="fe fe-x-square"></i> حذف دائم پایه
                                                                            </a>
                                                                        <?php  } ?>


                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="wd-20"><?php echo $res->codecourse ?></td>

                                                    <td><?php echo htmlentities($res->coursename) ?>
                                                    </td>
                                                    <td class="wd-20"><?php echo CountRegisterCourse($res->codecourse,1) ?></td>
                                                    <td class="wd-20"><?php echo CountRegisterCourse($res->codecourse,0) ?></td>

                                                    <td class="wd-20">
                                                        <?php if ($res->courseactive == 1) { ?>
                                                            <a href="?disable=<?php echo $res->codecourse ?>"
                                                               class="btn btn-sm ripple btn-success btn-icon"><i
                                                                    class="fe fe-check"></i></a>
                                                        <?php } else { ?>
                                                            <a href="?enable=<?php echo $res->codecourse ?>"
                                                               class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                    class="fe fe-x"></i></a>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="wd-20">
                                                        <a href="?active=<?php echo $res->codecourse ?>"
                                                           class="btn btn-sm ripple btn-info btn-icon"><i
                                                                class="si si-plus"></i></a>
                                                    </td>

                                                </tr>
                                            <?php }
                                        }
                                        ?>
                                              </tbody>
                                            </table>
                                           </div>
                                       </div>
                                 </div>
                             </div>
                        </div>

<!--                    type 2 tab 2-->
                        <?php
                        $currentUser = $_SESSION['admin']['user'];
                        if (PermisionUser('skill_permission', $currentUser)): ?>
                        <div class="tab-pane fade" id="type2" role="tabpanel">

                            <div class="col-lg-12">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-9">
                                                <div>
                                                    <h6 class="main-content-label mb-1">بایگانی سال‌های قبل </h6>
                                                    <p class="text-muted card-sub-title">
                                                    </p>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="table">
                                            <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                                <thead>
                                                <tr>
                                                    <th>اقدامات</th>
                                                    <th>کد پایه</th>
                                                    <th>نام پایه</th>
                                                    <th> موفق</th>
                                                    <th>منتظر</th>
                                                    <th>وضعیت ثبت نام</th>
                                                    <th>فعالسازی</th>


                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? AND course_type = 1 ORDER BY idcourse DESC ';
                                                $query = $conn->prepare($sql);
                                                $query->execute([0]);
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result
                                                             as $res) { ?>
                                                        <tr>
                                                            <td class="wd-20">
                                                                <div class="text-wrap">
                                                                    <div class="text-center">
                                                                        <div class="dropdown">
                                                                            <button aria-expanded="false"
                                                                                    aria-haspopup="true"
                                                                                    class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                    data-bs-toggle="dropdown" type="button">
                                                                                اقدام <i
                                                                                        class="fas fa-caret-down mr-1"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu tx-8">
                                                                                <a class="dropdown-item text-success "   href="add-Course.php?edit=<?php echo $res->codecourse ?>&id=<?php echo $res->idcourse ?>" >
                                                                                    <i class="fe fe-edit-2"></i>ویرایش پایه
                                                                                </a>
                                                                                <a class="dropdown-item"
                                                                                   href="movie.php?code=<?php echo $res->codecourse ?>"
                                                                                ><i class="fe fe-film"></i>
                                                                                    افزودن فیلم
                                                                                </a>

                                                                                <a class="dropdown-item "   href="addon-doc.php?code=<?php echo $res->codecourse ?>">
                                                                                    <i class="fe fe-book-open"></i>
                                                                                    افزودن جزوه

                                                                                </a>

                                                                                <div class="dropdown-item disabled  " >
                                                                                    <i class="fe fe-cpu"></i>   تنظیم برنامه کلاسی

                                                                                </div>
                                                                                <div class="dropdown-item  disabled">
                                                                                    <i class="fa fa-question"></i>   تنظیم برنامه آزمون

                                                                                </div>
                                                                                <div class="dropdown-item disabled ">
                                                                                    <i class="fa fa-chalkboard-teacher"> </i><?php echo m('action.choose_teacher2') ?>

                                                                                </div>
                                                                                <a class="dropdown-item"
                                                                                   href="list-student.php?code=<?php echo $res->codecourse ?>"
                                                                                ><i class="fa fa-chess-board"></i>
                                                                                    لیست دانش آموزان
                                                                                </a>

                                                                                <?php
                                                                                if (Permision('permision_deletcourse')) { ?>
                                                                                    <hr>
                                                                                    <a class="dropdown-item text-danger "
                                                                                       href="?close=<?php echo $res->codecourse ?>"
                                                                                       onclick="return confirm('آیا با حذف این پایه موافقید موافقید؟')"
                                                                                    ><i class="fe fe-x-square"></i> حذف دائم پایه
                                                                                    </a>
                                                                                <?php  } ?>


                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="wd-20"><?php echo $res->codecourse ?></td>

                                                            <td><?php echo htmlentities($res->coursename) ?>
                                                            </td>
                                                            <td class="wd-20"><?php echo CountRegisterCourse($res->codecourse,1) ?></td>
                                                            <td class="wd-20"><?php echo CountRegisterCourse($res->codecourse,0) ?></td>

                                                            <td class="wd-20">
                                                                <?php if ($res->courseactive == 1) { ?>
                                                                    <a href="?disable=<?php echo $res->codecourse ?>"
                                                                       class="btn btn-sm ripple btn-success btn-icon"><i
                                                                                class="fe fe-check"></i></a>
                                                                <?php } else { ?>
                                                                    <a href="?enable=<?php echo $res->codecourse ?>"
                                                                       class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                                class="fe fe-x"></i></a>
                                                                <?php } ?>
                                                            </td>
                                                            <td class="wd-20">
                                                                <a href="?active=<?php echo $res->codecourse ?>"
                                                                   class="btn btn-sm ripple btn-info btn-icon"><i
                                                                            class="si si-plus"></i></a>
                                                            </td>

                                                        </tr>
                                                    <?php }
                                                }
                                                ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>


                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Get all tab buttons
            var tabs = document.querySelectorAll('#courseTab button');

            // Check if a tab was previously active
            var activeTab = localStorage.getItem('activeCourseTab');
            if (activeTab) {
                var tabToShow = document.querySelector(`#courseTab button[data-bs-target="${activeTab}"]`);
                if (tabToShow) {
                    var tabInstance = new bootstrap.Tab(tabToShow);
                    tabInstance.show();
                }
            }

            // Save active tab when clicked
            tabs.forEach(function(tab) {
                tab.addEventListener('shown.bs.tab', function (event) {
                    localStorage.setItem('activeCourseTab', event.target.getAttribute('data-bs-target'));
                });
            });
        });
    </script>
</body>
</html>
