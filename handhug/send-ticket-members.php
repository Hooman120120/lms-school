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
        $section = $_POST['section']; // ارتباط با
        $pririoty = $_POST['pririoty']; // درجه اهمیت
        $detail = $_POST['detail']; // متن پیام
        $title = $_POST['title']; // موضوع
        date_default_timezone_set("Asia/Tehran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $sql = 'INSERT INTO ticket_topic(username,ticket_title,metod,ticket_section,ticket_pririoty,ticket_status,op_status,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
        $query = $conn->prepare($sql);
        $query->execute([$_SESSION['auth']['user'], $title, 5, $section, $pririoty, 'منتظر تایید پشتیبانی', 'pending', $datenow, $timenow, $ip, $os]);
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

$groupSql = 'SELECT * FROM handhug_groups WHERE id = ?';
$groupStmt = $conn->prepare($groupSql);
$groupStmt->execute([$_GET['group']]);
$group = $groupStmt->fetch(PDO::FETCH_OBJ);

$countMembersSql = 'SELECT COUNT(*) AS countMembers FROM dbcourse WHERE codecourse = ? AND group_id = ?';
$countMembersStmt = $conn->prepare($countMembersSql);
$countMembersStmt->execute([$group->codecourse, $_GET['group']]);
$countMembers = $countMembersStmt->fetch(PDO::FETCH_OBJ);

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
    <title> ارسال پیام |<?php echo __TITLE ?></title>
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
                                <h5 class="mb-3 font-weight-bold tx-14">پیام به اعضای گروه</h5>
                            </div>
                            <div class="card-body">
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
                                                                شد و منتظر تایید پشتیبان می باشد
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
                                                                        <option value="0"><?php echo '' ?></option>
                                                                    <?php
                                                                    $sql = 'SELECT username FROM dbcourse WHERE codecourse = ? AND group_id = ?';
                                                                    $stmt = $conn->prepare($sql);
                                                                    $stmt->execute([$group->codecourse, $_GET['group']]);
                                                                    $members = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                                    foreach ($members as $member) {
                                                                        if ($member->username == $_SESSION['auth']['user']) {
                                                                            continue;
                                                                        }
                                                                        $person = retPerson($member->username);
                                                                    ?>
                                                                        <option value="<?= $person->codemeli ?>"><?php echo $person->fname . ' ' . $person->lname ?></option>
                                                                    <?php } ?>
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
                                                                ارسال پیام
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
                                                            foreach (
                                                                $result

                                                                as $ri
                                                            ) {
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