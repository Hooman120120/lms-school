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
if (isset($_GET['TicketId']) and !CheckTicketValid($_GET['TicketId'], $_SESSION['auth']['user'])) {
    header('location:' . url('Support/AllTicket.php'));
    exit;
}
//* End Check Session JUST ONE Session
if (isset($_POST['closed']) and isset($_GET['TicketId'])) {
    $ticketid = intval($_GET['TicketId']);
    $update = 'UPDATE ticket_topic SET ticket_status=?,metod=? WHERE ticket_num=?';
    $act = $conn->prepare($update);
    $act->execute(['بسته شده', 3, $ticketid]);
}
if (isset($_POST['sub']) and isset($_GET['TicketId'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $ticketid = intval($_GET['TicketId']);
        $detail = $_POST['txt'];
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        if (strlen($detail) > 2) {
            $insert = 'INSERT INTO ticket_meta (ticket_num,detail,useradder,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?)';
            $quer = $conn->prepare($insert);
            $quer->execute([$ticketid, $detail, $_SESSION['auth']['user'], $datenow, $timenow, $ip, $os]);
            //
            $useradder = 'SELECT responser FROM ticket_topic WHERE ticket_num=?';
            $rqw = $conn->prepare($useradder);
            $rqw->execute([$ticketid]);
            $question = $rqw->fetch();
            if ($rqw->rowCount() > 0) {
                if ($question['responser'] == NULL) {
                    $metod = 0;
                } else {
                    $metod = 1;
                }
            }
            $update = 'UPDATE ticket_topic SET ticket_status=? , metod=? WHERE ticket_num=?';
            $act = $conn->prepare($update);
            $act->execute(['منتظر پاسخ کارشناس', $metod, $ticketid]);
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
    <title> پیام های پشتیبانی| <?php echo __TITLE ?></title>
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


                        <?php if (!isset($_GET['TicketId'])){ ?>

                        <div class="card-header">


                            <h5 class="mb-3 font-weight-bold tx-14">مشاهده همه پیام ها </h5>
                        </div>
                        <div class="card-body">
                            <nav class="nav main-nav-line main-nav-line-chat card-body">


                                <a class="nav-link active " data-bs-toggle="tab" href="#flow">جاری</a>
                                <a class="nav-link " data-bs-toggle="tab" href="#closed">بسته شده </a>

                            </nav>
                            <div class="tab-content main-chat-list">
                                <div class="tab-pane active " id="flow">
                                    <div class="main-chat-list tab-pane">
                                        <?php
                                        $dsql = 'SELECT * FROM ticket_topic WHERE username=? ORDER BY ticket_num DESC ';
                                        $query2 = $conn->prepare($dsql);
                                        $query2->execute([$_SESSION['auth']['user']]);
                                        $result = $query2->fetchAll(PDO::FETCH_OBJ);
                                        if ($query2->rowCount() > 0) {
                                            $if = 1;
                                            foreach ($result

                                                     as $ri) {
                                                if ($ri->ticket_status != 'بسته شده') {


                                                    ?>
                                                    <a class="media <?php if ($ri->ticket_status == 'پاسخ داده شده') {
                                                        echo 'selected';
                                                    } ?> " href="AllTicket.php?TicketId=<?php echo $ri->ticket_num ?>">

                                                        <div class="media-body mr-3">
                                                            <div class="media-contact-name">
                                                                <span> <?php
                                                                    switch ($ri->ticket_section) {
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
                                                                    echo $section ?> </span>
                                                                <span># <?php echo $ri->ticket_num ?></span>
                                                            </div>
                                                            <p><?php echo $ri->ticket_status; ?></p>
                                                        </div>
                                                    </a>
                                                    <?php
                                                }
                                            }
                                        }
                                        ?>


                                    </div><!-- main-chat-list -->
                                </div><!-- main-chat-list -->

                                <div class="tab-pane" id="closed">

                                    <div class="main-chat-list tab-pane">
                                        <?php
                                        $dsql = 'SELECT * FROM ticket_topic WHERE username=? ORDER BY ticket_num DESC ';
                                        $query2 = $conn->prepare($dsql);
                                        $query2->execute([$_SESSION['auth']['user']]);
                                        $result = $query2->fetchAll(PDO::FETCH_OBJ);
                                        if ($query2->rowCount() > 0) {
                                            $if = 1;
                                            foreach ($result

                                                     as $ri) {
                                                if ($ri->ticket_status == 'بسته شده') {

                                                    ?>
                                                    <a class="media  "
                                                       href="AllTicket.php?TicketId=<?php echo $ri->ticket_num ?>">

                                                        <div class="media-body mr-3">
                                                            <div class="media-contact-name">
                                                                <span> <?php
                                                                    switch ($ri->ticket_section) {
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
                                                                    echo $section ?> </span>
                                                                <span># <?php echo $ri->ticket_num ?></span>
                                                            </div>
                                                            <p><?php echo $ri->ticket_status; ?></p>
                                                        </div>
                                                    </a>
                                                    <?php
                                                }
                                            }
                                        }
                                        ?>


                                    </div><!-- main-chat-list -->
                                </div><!-- main-chat-list -->


                            </div>

                        </div>


                    </div>
                    <?php } else {
                    if ($_GET['TicketId'] != '') {
                        $ticketid = $_GET['TicketId'];
                        $check = 'SELECT * FROM ticket_topic WHERE username=? AND ticket_num=?';
                        $query = $conn->prepare($check);
                        $query->execute([$_SESSION['auth']['user'], $ticketid]);
                        if ($query->rowCount() > 0) {
                            $refult = $query->fetch();
                        }
                    }
                    ?>
                    <div class="card-header">
                        <h5 class="mb-3 font-weight-bold tx-14">مشاهده پیام شماره : <?php echo $_GET['TicketId'] ?>
                            # </h5>
                        <span>موضوع : <?php echo htmlentities($refult['ticket_title']) ?></span>
                        <div class="row">
                            <div class="col-xl-4 pd-b-5">
                        <span>وضعیت :
                            <span class="badge badge-warning-light"><?php echo htmlentities($refult['ticket_status']) ?></span></span>
                            </div>
                            <div class="col-xl-4 pd-b-5">
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
                            <div class="col-xl-4 pd-b-5">
                                 <span>اولویت :
                            <span class="badge badge-info-light"><?php echo htmlentities($refult['ticket_pririoty']) ?></span></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="main-chat-body" id="ChatBody">
                            <?php
                            $sql = 'SELECT * FROM ticket_meta WHERE ticket_num=?';
                            $ququ = $conn->prepare($sql);
                            $ququ->execute([$ticketid]);
                            $retur = $ququ->fetchAll(PDO::FETCH_OBJ);
                            if ($ququ->rowCount() > 0){
                            foreach ($retur

                            as $gmc){
                            if ($gmc->useradder == $_SESSION['auth']['user']) {
                                echo ' <div class="media ">';
                            } else {
                                echo ' <div class="media flex-row-reverse">';
                            }
                            ?>

                            <div class="main-img-user online">
                            </div>
                            <div class="main-msg-wrapper">
                                    <span <?php if ($gmc->useradder == $_SESSION['auth']['user']) {
                                        $fullname = $_SESSION['auth']['name'] . ' ' . $_SESSION['auth']['famili'];
                                    } else {
                                        $fn = retAdmin($gmc->useradder);
                                        $fullname = '<i class="ti ti-headphone-alt text-info"></i> ' . $fn->gender . ' ' . $fn->lname;
                                    } ?>><?php echo $fullname ?>:</span>
                                <div class="media-body mr-3">
                                    <span><?php echo htmlentities($gmc->detail) ?>  </span>
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


                        <?php
                        }
                        }
                        ?>


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
                        </div>
                        <?php } else { ?>
                            <hr>
                            <div class="text-center">
                                این پیام در وضعیت بسته شده قرار داد و امکان ارسال پاسخ جدید وجود ندارد، درصورت لزوم یک
                                پیام جدید ایجاد کنید
                            </div>
                            <?php
                        }
                        }
                        ?>
                    </div>

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

