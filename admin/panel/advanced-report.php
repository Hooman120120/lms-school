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
if (!Permision('permision_manage_suporter')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['report'])) {
    $op = $_POST['op'];
    $type = $_POST['type'];
    $count = $_POST['count'];
    $start = $_POST['startdate'];
    $start = explode('/', $start);
    $start = jalali_to_gregorian($start[0], $start[1], $start[2]);
    $start = implode('-', $start);
    $end = $_POST['enddate'];
    $end = explode('/', $end);
    $end = jalali_to_gregorian($end[0], $end[1], $end[2]);
    $end = implode('-', $end);
    $_SESSION['reportCall']=array(
            "op"=>$op,
            "type"=>$type,
            "count"=>$count,
            "start"=>$start,
            "end"=>$end

    );


}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> گزارش پیشرفته تماس | <?php echo __TITLE ?></title>
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
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div>
                                    <h6 class="main-content-label mb-1">گزارش پیشرفته تماس</h6>
                                    <p class="text-muted card-sub-title">لطفا اطلاعات خواسته شده را جهت تولید گزارش
                                        وارد کنید
                                    </p>
                                </div>

                            </div>
                            <form method="post" data-parsley-validate="">
                                <div class="row">
                                    <div class="col-xl-2 mb-2">
                                        <label for="sens" class="form-label mg-t-15">انتخاب اپراتور :<span
                                                    style="color: red">*</span>
                                        </label>
                                        <select class="form-control select2 "
                                                name="op"
                                                aria-describedby="basic-addon2" required>
                                            <?php
                                            $sql = 'SELECT * FROM dbadmin ORDER BY dakheli ASC ';
                                            $query = $conn->prepare($sql);
                                            $query->execute();
                                            $reds = $query->fetchAll(PDO::FETCH_OBJ);
                                            if ($query->rowCount() > 0) {

                                                ?>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php foreach ($reds as $red) { ?>
                                                    <option><?php echo $red->username ?></option>
                                                <?php }
                                            }
                                            ?>


                                        </select>
                                    </div>
                                    <div class="col-xl-2 mb-2">
                                        <label for="sens" class="form-label mg-t-15">نوع تماس :<span
                                                    style="color: red">*</span>
                                        </label>
                                        <select class="form-control select2 "
                                                name="type"
                                                aria-describedby="basic-addon2" required>
                                            <option label="اقدام لازم">
                                            </option>
                                            <option value="1" selected>تماس خروجی</option>
                                            <option value="2" disabled>تماس ورودی</option>


                                        </select>
                                    </div>

                                    <div class="col-xl-2">
                                        <label for="sens" class="form-label mg-t-15">حلقه گزارش:<span
                                                    style="color: red">*</span>
                                        </label>
                                        <input class="form-control "
                                               required value="1000000"
                                               type="number" name="count">
                                    </div>

                                    <div class="col-xl-3">
                                        <p>از تاریخ :<span
                                                    style="color: red">*</span>
                                        </p>

                                        <div class="input-group ">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fe fe-calendar lh--9 op-6"></i>
                                                </div>
                                            </div>
                                            <input class="form-control fc-datepicker"
                                                   placeholder="1404/09/10" required
                                                   type="text" name="startdate">
                                        </div>
                                    </div>
                                    <div class="col-xl-3 ">
                                        <p>تا تاریخ :<span
                                                    style="color: red">*</span>
                                        </p>

                                        <div class="input-group ">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fe fe-calendar lh--9 op-6"></i>
                                                </div>
                                            </div>
                                            <?php
                                            $datenow = date('Y-m-d');
                                            $datenow = explode('-', $datenow);
                                            $datenow = gregorian_to_jalali($datenow[0], $datenow[1], $datenow[2]);
                                            $datenow = implode('/', $datenow);
                                            ?>
                                            <input class="form-control fc-datepicker"
                                                   value="<?php echo $datenow ?>" required
                                                   type="text" name="enddate">
                                        </div>
                                    </div>
                                </div>
                                <div class=" text-right mg-t-20">
                                    <button type="submit" name="report" class="btn btn-sm btn-primary"><i
                                                class="fe fe-bar-chart-2"></i> جستجو و تولید گزارش
                                    </button>

                                </div>
                            </form>

                        </div>
                    </div>
                    <?php if(isset($_SESSION['reportCall'])) { ?>
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="text-center mg-b-30">
                                <?php
                                $datestart = explode('-', $_SESSION['reportCall']['start']);
                                $datestart = gregorian_to_jalali($datestart[0], $datestart[1], $datestart[2]);
                                $datestart = implode('/', $datestart);

                                $dateend = explode('-', $_SESSION['reportCall']['end']);
                                $dateend = gregorian_to_jalali($dateend[0], $dateend[1], $dateend[2]);
                                $dateend = implode('/', $dateend);

                                ?>
                              گزارش برای پشتیبان <span class="tx-danger">  <?php echo $_SESSION['reportCall']['op']; ?></span>  از تاریخ <span class="tx-danger"><?php echo $datestart ?></span> تا تاریخ <span class="tx-danger"><?php echo $dateend ?></span>
                                </div>


                                <div class="col-md-6 col-sm-6 col-lg-6  col-xl-6">
                                    <div class="card custom-card overflow-hidden">
                                        <?php

                                        $report=reportCallLMS($_SESSION['reportCall']['start'],$_SESSION['reportCall']['end'],$_SESSION['reportCall']['op']);

                                        ?>
                                        <ul class="crypto-transcation list-unstyled mg-b-0">
                                            <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                <div class="media-body ">
                                                    <p class="tx-medium mg-b-3 tx-13"> تماس موفق</p>
                                                    <p class="tx-11 mg-b-0 tx-gray-500">گزارشات توسط پشتیبان</p>
                                                </div>
                                                <div class="text-right mr-auto my-auto">
                                                    <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS VoiceCall Successful']  ?></h5>
                                                </div>
                                            </li>
                                            <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                <div class="media-body ">
                                                    <p class="tx-medium mg-b-3 tx-13"> عدم پاسخگویی تماس</p>
                                                    <p class="tx-11 mg-b-0 tx-gray-500">گزارشات توسط پشتیبان</p>
                                                </div>
                                                <div class="text-right mr-auto my-auto">
                                                    <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS VoiceCall NOT']  ?></h5>
                                                </div>
                                            </li>
                                            <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                <div class="media-body ">
                                                    <p class="tx-medium mg-b-3 tx-13"> متنی موفق</p>
                                                    <p class="tx-11 mg-b-0 tx-gray-500">گزارشات  توسط پشتیبان</p>
                                                </div>
                                                <div class="text-right mr-auto my-auto">
                                                    <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS TEXTSUPPORT Successful']  ?></h5>
                                                </div>
                                            </li>
                                            <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                <div class="media-body ">
                                                    <p class="tx-medium mg-b-3 tx-13"> عدم پاسخگویی متنی</p>
                                                    <p class="tx-11 mg-b-0 tx-gray-500">گزارشات   توسط پشتیبان</p>
                                                </div>
                                                <div class="text-right mr-auto my-auto">
                                                    <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS TEXTSUPPORT NOT']  ?></h5>
                                                </div>
                                            </li>

                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6 col-lg-6  col-xl-6">
                                    <div class="card custom-card overflow-hidden">
                                        <?php

                                        $start=$_SESSION['reportCall']['start'].' 00:01';
                                        $end=$_SESSION['reportCall']['end'].' 23:59';
                                        $op=retAdmin($_SESSION['reportCall']['op']);
                                        $op=$op->dakheli;
                                        $simotel=reportCallSimotel($start,$end,$op,$_SESSION['reportCall']['count']);
                                        ?>
                                        <ul class="crypto-transcation list-unstyled mg-b-0">
                                            <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                <div class="media-body ">
                                                    <p class="tx-medium mg-b-3 tx-13">پاسخ داده شده</p>
                                                    <p class="tx-11 mg-b-0 tx-gray-900"> زمان : <?php  echo $simotel['TIME ANSWERED'] ?> </p>
                                                    <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                </div>
                                                <div class="text-right mr-auto my-auto">
                                                    <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['ANSWERED']  ?></h5>
                                                </div>
                                            </li>
                                            <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                <div class="media-body ">
                                                    <p class="tx-medium mg-b-3 tx-13">عدم پاسخ</p>
                                                    <p class="tx-11 mg-b-0 tx-gray-900"> زمان : <?php  echo $simotel['TIME NO ANSWERED'] ?> </p>
                                                    <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                </div>
                                                <div class="text-right mr-auto my-auto">
                                                    <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['NO ANSWERED']  ?></h5>
                                                </div>
                                            </li>
                                            <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                <div class="media-body ">
                                                    <p class="tx-medium mg-b-3 tx-13"> کل تماس شما</p>
                                                    <p class="tx-11 mg-b-0 tx-gray-900"> زمان : <?php  echo $simotel['TIME TOTAL'] ?> </p>
                                                    <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                </div>
                                                <div class="text-right mr-auto my-auto">
                                                    <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['ANSWERED']+$simotel['NO ANSWERED'];  ?></h5>
                                                </div>
                                            </li>


                                        </ul>
                                    </div>
                                </div>




                            </div>
                        </div>
                    </div>
                    <?php }

                    unset($_SESSION['reportCall']);
                    ?>

                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
