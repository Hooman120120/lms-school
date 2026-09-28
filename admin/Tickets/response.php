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
if (!isset($_GET['ticket'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!Permision('permision_viewsupport')) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (isset($_POST['closed']) || isset($_POST['opened']) || isset($_POST['sub'])) {
    AdminRequireTreeOperation(isset($_POST['sub']) ? 'tickets.inbox.reply' : 'tickets.inbox.status',
        'tickets.all', 'tickets.open', 'tickets.emergency', 'tickets.referred', 'public-relations.safe-call');
}
if (isset($_POST['closed']) and isset($_GET['ticket'])) {
    $ticketid = intval($_GET['ticket']);
    $update = 'UPDATE ticket_topic SET ticket_status=?,metod=? WHERE ticket_num=?';
    $act = $conn->prepare($update);
    $act->execute(['بسته شده', 3, $ticketid]);

}
if (isset($_POST['opened']) and isset($_GET['ticket'])) {
    $ticketid = intval($_GET['ticket']);
    $update = 'UPDATE ticket_topic SET ticket_status=?,metod=? WHERE ticket_num=?';
    $act = $conn->prepare($update);
    $act->execute(['منتظر پاسخ کارشناس', 2, $ticketid]);

}
if (isset($_POST['sub']) and isset($_GET['ticket'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $ticketid = intval($_GET['ticket']);
        $detail = $_POST['txt'];
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        if (strlen($detail) > 2) {
            $insert = 'INSERT INTO ticket_meta (ticket_num,detail,useradder,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?)';
            $quer = $conn->prepare($insert);
            $quer->execute([$ticketid, $detail, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
            //

            $update = 'UPDATE ticket_topic SET ticket_status=?,metod=?,responser=? WHERE ticket_num=?';
            $act = $conn->prepare($update);
            $act->execute(['پاسخ داده شده', 2, $_SESSION['admin']['user'], $ticketid]);

        }
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
    <title> پاسخ به تیکت | <?php echo __TITLE ?></title>
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

    <!-- Main Content-->
    <div class="main-content  pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="col-lg-12 col-xl-12 col-md-12">
                    <div class="card custom-card">
                        <div class="card-header">
                            <?php
                            $check = 'SELECT * FROM ticket_topic WHERE ticket_num=?';
                            $query = $conn->prepare($check);
                            $query->execute([$_GET['ticket']]);
                            if ($query->rowCount() > 0) {
                                $refult = $query->fetch();
                            }

                            ?>
                            <h5 class="mb-3 font-weight-bold tx-14">مشاهده پیام شماره : <?php echo $_GET['ticket'] ?>
                                # </h5>
                            <span>موضوع : <?php echo htmlentities($refult['ticket_title']) ?></span>
                            <div class="row">
                                <div class="col-xl-3 pd-b-5">
                        <span>وضعیت :
                            <span class="badge badge-warning-light"><?php echo htmlentities($refult['ticket_status']) ?></span></span>
                                </div>
                                <div class="col-xl-3 pd-b-5">
                                 <span>ارتباط با :
                            <span class="badge badge-success-light"><?php
                                switch ($refult['ticket_section']) {
                                    case '1':
                                        $section = __MOSHAVER;
                                        break;
                                    case '2':
                                        $section = __LOGINROOM;
                                        break;
                                    case '3':
                                        $section = __OFFICIAL;
                                        break;
                                    case '4':
                                        $section = __TECHER;
                                        break;
                                    case '5':
                                        $section = __QUIZ;
                                        break;
                                    case '6':
                                        $section = __MAINTENANCE;
                                        break;
                                    case '7':
                                        $section = __BAZRESI;
                                        break;
                                    case '8':
                                        $section = __BOSS;
                                        break;
                                }


                                echo $section; ?></span></span>
                                </div>
                                <div class="col-xl-3 pd-b-5">
                                 <span>اولویت :
                            <span class="badge badge-info-light"><?php echo htmlentities($refult['ticket_pririoty']) ?></span></span>
                                </div>
                                <div class="col-xl-3 pd-b-5">
                                 <span>کد ملی:
                          <?php echo htmlentities($refult['username']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="main-chat-body" id="ChatBody">
                                <?php
                                $sql = 'SELECT * FROM ticket_meta WHERE ticket_num=?';
                                $ququ = $conn->prepare($sql);
                                $ququ->execute([$_GET['ticket']]);
                                $retur = $ququ->fetchAll(PDO::FETCH_OBJ);
                                if ($ququ->rowCount() > 0){
                                foreach ($retur

                                as $gmc){
                                if (retAdmin($gmc->useradder)) {
                                    echo ' <div class="media ">';
                                    $admin = 1;
                                } else {
                                    echo ' <div class="media flex-row-reverse">';
                                    $admin = 0;
                                }
                                ?>
                                <div class="main-img-user online">
                                </div>
                                <div class="main-msg-wrapper">
                                    <span><?php if ($admin == 1) {
                                            $fu = retAdmin($gmc->useradder);
                                            $title = 'کارمند:';
                                        } else {

                                            $fu = retPerson($gmc->useradder);
                                            if (typeUser($gmc->useradder) == 1) {
                                                $title = ' دانش آموز:';
                                            } else {
                                                $title = ' همکار آموزشی:';
                                            }
                                        }
                                        echo $title . ' ' . $fu->fname . ' ' . $fu->lname; ?> </span>
                                    <div class="media-body mr-3">
                                        <span> <?php echo htmlentities($gmc->detail) ?> </span>
                                    </div>
                                    <div>
                                                    <span class="text-muted">
                                                        تاریخ ارسال:
                                                        <?php echo $gmc->timecreated; ?>
                                                        -
                                                           <?php
                                                           $convert = $gmc->datecreated;
                                                           $convert = explode('-', $convert);
                                                           $convert = gregorian_to_jalali($convert[0], $convert[1], $convert[2]);
                                                           $convert = implode('/', $convert);
                                                           echo $convert; ?>
                                                    </span>
                                    </div>
                                </div>
                            </div>
                            <?php }
                            } ?>
                        </div>
                        <?php if ($refult['ticket_status'] != 'بسته شده') { ?>
                        <form method="post" enctype="multipart/form-data">
                            <div class="main-chat-footer">
                                <nav class="nav">

                                </nav>


                                <input class="form-control" name="txt"
                                       placeholder="پیام خود را اینجا بنویسید ..." type="text" required>
                                <?php
                                $hash = bin2hex(random_bytes(32));
                                $_SESSION['token'] = $hash;
                                ?>
                                <input class="form-control" name="token" type="hidden" value="<?php echo $hash ?>">

                                <button type="submit" name="sub" class="main-msg-send" style="display: contents"
                                ><i class="far fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>


                    </div>

                    <div class="card-body">
                        <hr>
                        <div class="text-center">
                            <form method="post">
                                <button class="btn btn-light" name="closed"
                                        onClick="return confirm('درصورت بستن پیام، امکان ارسال پاسخ برای این پیام دیگر میسر نمی شود');">
                                    <i class="fe fe-bell-off"></i> پایان دادن به این گفتگو
                                </button>
                            </form>
                            <?php } else { ?>
                            <hr>
                            <div class="text-center">
                                این تیکت در وضعیت بسته شده قرار داد و امکان ارسال پاسخ جدید وجود ندارد
                                <form method="post">
                                    <button class="btn btn-light" name="opened"
                                            onClick="return confirm('آیا از بازکردن این گفتگو مطمئنید؟');">
                                        <i class="fe fe-bell-off"></i>از سرگیری مجدد گفتگو
                                    </button>
                                </form>
                                <?php } ?>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>