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
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
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
if (isset($_POST['submit']) and $_POST['detail'] != '') {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $section = $_POST['section'];
        $pririoty = $_POST['pririoty'];
        $detail = $_POST['detail'];
        $title = $_POST['title'];
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        if ($section == 2) {
            $metod = 4;
        } else {
            $metod = 0;
        }
        $sql = 'INSERT INTO ticket_topic(username,ticket_title,metod,ticket_section,ticket_pririoty,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?,?,?)';
        $query = $conn->prepare($sql);
        $query->execute([$_SESSION['auth']['user'], $title, $metod, $section, $pririoty, $datenow, $timenow, $ip, $os]);
        $lastId = $conn->lastInsertId();
        if ($lastId) {
            $tsql = 'INSERT INTO ticket_meta (ticket_num,detail,useradder,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?)';
            $query1 = $conn->prepare($tsql);
            $query1->execute([$lastId, $detail, $_SESSION['auth']['user'], $datenow, $timenow, $ip, $os]);
            $verify = 100;


        }
    }
}
include_once '../temp.html';

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
    <title> ارسال پیام پشتیبانی |<?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
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
                <div class="col-lg-12 col-xl-12 col-md-12">
                    <div class="card custom-card">
                        <div class="card-header">
                            <h5 class="mb-3 font-weight-bold tx-14">پیام پشتیبانی</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-primary alert-dismissible fade show" role="alert">
                                <h5 class="alert-heading">کاربر گرامی لطفاً قبل از ثبت پیام به پشتیبان به نکات زیر توجه
                                    فرمایید
                                </h5>
                                <p> 1- درخواست های شما بصورت برخط ثبت شده و به کارشناس پشتیبان ارجاع داده خواهد شد،
                                    بنابراین
                                    از ارسال مجدد درخواست در پیام جدید خودداری کنید.</p>

                                <p>3- برای مشاهده پاسخ پیام خود و یا پاسخ به نظر کارشناس از پنل پیگیری درخواست اقدام
                                    کنید.</p>
                                <p>4- چنانچه موردی مورد سوال بیشتر کاربران قرار گیرد به جهت آشنایی دیگر کاربران بصورت
                                    اطلاعیه و یا پیام در
                                    سایت نمایش داده خواهد شد.</p>


                                <p>7- سعی کنید فارسی تایپ کنید و موضوع و محتوا را بصورت دقیق بیان بفرمائید</p>

                            </div>
                            <section class="section">
                                <div class="row">
                                    <div class="col-xl-6">
                                        <div class="card">
                                            <form method="post">
                                                <div class="card-body">
                                                    <?php if (isset($verify) and $verify == 100) { ?>
                                                        <div class="alert alert-success" role="alert">
                                                            <span class="alert-inner--icon"><i
                                                                        class=" mdi mdi-asterisk"></i></span>

                                                            درخواست پیام شما به شماره <?php echo $lastId . '#' ?> ایجاد
                                                            شد
                                                        </div>
                                                    <?php } ?>
                                                    <h5 class="card-title">ثبت پیام جدید</h5>
                                                    <div class="row">
                                                        <div class="col-xl-8">

                                                            <label for="subject" class="form-label">ارتباط با: <span
                                                                        style="color: red">*</span></label>
                                                            <select class="form-select" name="section"
                                                                    aria-label="Default select example"
                                                                    id="subject">
                                                                <option value="1"><?php echo __MOSHAVER ?></option>
                                                                <option value="2"><?php echo __LOGINROOM ?></option>
                                                                <option value="3"><?php echo __OFFICIAL ?></option>
                                                                <option value="4"><?php echo __TECHER ?></option>
                                                                <option value="5"><?php echo __QUIZ ?></option>
                                                                <option value="6"><?php echo __MAINTENANCE ?></option>
                                                                <option value="7"><?php echo __BAZRESI ?></option>
                                                                <option value="8"><?php echo __BOSS ?></option>
                                                            </select>

                                                        </div>
                                                        <div class="col-xl-4">
                                                            <label for="sens" class="form-label">درجه اهمیت: <span
                                                                        style="color: red">*</span></label>
                                                            <select class="form-select" name="pririoty"
                                                                    aria-label="Default select example"
                                                                    id="sens">
                                                                <option>کم اهمیت</option>
                                                                <option>متوسط</option>
                                                                <option>پر اهمیت</option>
                                                                <option>فوری</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-xl-12">
                                                            <label for="sens" class="form-label">موضوع: <span
                                                                        style="color: red">*</span></label>
                                                            <input class="form-control" name="title" type="text"
                                                                   required>
                                                            <?php
                                                            $hash = bin2hex(random_bytes(32));
                                                            $_SESSION['token'] = $hash;
                                                            ?>
                                                            <input class="form-control" name="token" type="hidden"
                                                                   value="<?php echo $hash ?>">

                                                        </div>

                                                    </div>
                                                    <label for="detail" class="form-label"> متن پیام :<span
                                                                style="color: red">*</span></label>
                                                    <textarea name="detail" id="detail" class="form-control"
                                                              style="height: 140px" required></textarea>


                                                    <div class="text-center mg-t-10">
                                                        <button type="submit" class="btn btn-success " name="submit">
                                                            ارسال درخواست پشتیبانی
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="col-xl-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">مشاهده و پیگیری پیام ها</h5>
                                                <table class="table  table-hover">
                                                    <thead>
                                                    <tr style="text-align: center; background-color: #57ffff;">

                                                        <th>شماره پیام</th>
                                                        <th>موضوع</th>
                                                        <th>وضعیت</th>
                                                    </tr>
                                                    </thead>
                                                    <?php
                                                    $dsql = 'SELECT * FROM ticket_topic WHERE username=? ORDER BY ticket_num DESC ';
                                                    $query2 = $conn->prepare($dsql);
                                                    $query2->execute([$_SESSION['auth']['user']]);
                                                    $result = $query2->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query2->rowCount() > 0) {
                                                        $if = 1;
                                                        foreach ($result

                                                                 as $ri) {
                                                            if ($if < 10 and $ri->ticket_status != 'بسته شده') {
                                                                ?>
                                                                <tbody>

                                                                <tr onclick="window.location.href='AllTicket.php?TicketId='+'<?php echo $ri->ticket_num ?> '; "
                                                                    style="text-align: center;<?php if ($ri->ticket_status == 'پاسخ داده شده') {
                                                                        echo 'background-color: yellow;';
                                                                    } ?>">

                                                                    <td>#<?php echo $ri->ticket_num; ?></td>
                                                                    <td><?php echo $ri->ticket_title; ?></td>
                                                                    <td><?php echo $ri->ticket_status; ?></td>
                                                                </tr>

                                                                </tbody>
                                                                <?php
                                                            }
                                                            $if++;
                                                        }
                                                    }
                                                    ?>

                                                </table>
                                                <div class="text-center d-grid gap-2 mt-3">

                                                    <button type="submit" class="btn btn-primary " name="viewticket"
                                                            onclick="window.location.href='AllTicket.php' "><span
                                                                style="font-size: 20px;">مشاهده همه پیام ها</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
