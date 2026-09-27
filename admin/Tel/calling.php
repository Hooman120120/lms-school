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
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_viewcall')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_SESSION['act-call'])) {
    header('location: ' . url('admin/Tel/uncall.php'));
    die();
}

if (isset($_GET['back'])) {
    unset($_SESSION['act-call']);
    header('location: ' . url('admin/Tel/managecall.php'));
    die();
}

switch ($_SESSION['act-call']['box']) {
    case 'mob1':
        $box = 'موبایل 1';
        break;
    case 'mob2':
        $box = 'موبایل 2';
        break;
    case 'mob3':
        $box = 'موبایل 3';
        break;
    case 'static1':
        $box = 'تلفن ثابت 1';
        break;
    case 'static2':
        $box = 'تلفن ثابت 2';
        break;
    case 'static3':
        $box = 'تلفن ثابت 3';
        break;
}
if (isset($_POST['save-comment'])) {
    // IMPORTANT: do this once (or in dbconfig.php)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    try {
        $conn->beginTransaction();

        $act = $_POST['act'] ?? null;
        $txt = trim($_POST['detail'] ?? '');
        $detail2Arr = $_POST['detail2'] ?? [];
        $detail3 = trim($_POST['detail3'] ?? '');

        // normalize detail2
        if (!is_array($detail2Arr)) $detail2Arr = [$detail2Arr];
        $detail2 = implode(',', array_map('trim', $detail2Arr));

        // server-side required checks (do not trust HTML required)
        if ($act === null || $txt === '' || $detail3 === '' || $detail2 === '') {
            throw new RuntimeException('Missing required fields (act/detail/detail2/detail3).');
        }

        // nextcall should ONLY be processed when act == 1
        $nextcallDb = null;
        if ($act === '1') {
            $raw = trim($_POST['nextcall'] ?? '');

            // Expect Jalali YYYY/MM/DD exactly
            if (!preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $raw)) {
                throw new RuntimeException('Bad nextcall format: ' . $raw);
            }

            [$jy, $jm, $jd] = array_map('intval', explode('/', $raw));
            $g = jalali_to_gregorian($jy, $jm, $jd);

            // Ensure YYYY-MM-DD
            $nextcallDb = sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
        }

        // Update tel_db
        $update = 'UPDATE tel_db SET act = ?, nextcall = ? WHERE id = ?';
        $conn->prepare($update)->execute([$act, $nextcallDb, $_SESSION['act-call']['id']]);

        // Insert tel_detail
        date_default_timezone_set('Asia/Tehran');
        $datenow = date('Y-m-d');
        $timenow = date('H:i:s');

        $insert = 'INSERT INTO tel_detail(dbtel,txt,txt2,txt3,box,useradder,datecreated,timecreated)
                   VALUES (?,?,?,?,?,?,?,?)';
        $conn->prepare($insert)->execute([
            $_SESSION['act-call']['id'],
            $txt,
            $detail2,
            $detail3,
            $_SESSION['act-call']['box'],
            $_SESSION['admin']['user'],
            $datenow,
            $timenow
        ]);

        $conn->commit();
        header('location: ' . url('admin/Tel/calling.php?back'));
        exit;

    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();

        // Log the exact cause (this is what will reveal your "1 out of 10")
        error_log('TEL_DETAIL_FAIL: ' . $e->getMessage() . ' | POST=' . json_encode($_POST, JSON_UNESCAPED_UNICODE));

        echo "<div class='alert alert-danger'>خطا در ثبت گزارش. لطفاً دوباره تلاش کنید.</div>";
    }
}

if (isset($_POST['olgo'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $olgo = $_POST['act'];
        $sms = retTelDb($_SESSION['act-call']['id']);
        switch ($_SESSION['act-call']['box']) {
            case 'mob1':
                $tel = $sms['mob1'];
                break;
            case 'mob2':
                $tel = $sms['mob2'];
                break;
            case 'mob3':
                $tel = $sms['mob3'];
                break;
            case 'static1':
                $tel = $sms['static1'];
                break;
            case 'static2':
                $tel = $sms['static2'];
                break;
            case 'static3':
                $tel = $sms['static3'];
                break;
        }
        switch ($olgo) {
            case '1':
                $text = 'فرهیخته' . ';' . 'گرامی' . ';' . 'بخشنامه شماره 400/354 وزارت آموزش و پرورش و بسیج دانش آموزی' . ';' . 'اطلاعات بیشتر و یا با گروه ایتا' . PHP_EOL . 'eitaa.com/bevosatiran' . PHP_EOL;
                $olgo = 181536;
                break;
            case '2':
                $text = 'از طرف موسسه هنرآموزش با شما تماس گرفتم جهت ثبت نام در پایه های آموزشی درسی با پایین ترین هزینه در کشور' . PHP_EOL .
                    ';' . 'همچنین دریافت جایزه ویژه با این شماره' . ';' . '02191098185' . ';' . 'و در کانال ایتا موسسه' . PHP_EOL .
                    'https://eitaa.com/onlinehonaramoozesh' . PHP_EOL .
                    'عضو شوید.' . PHP_EOL;

                $olgo = 182604;
                break;
            case '3':
                $text = '113 ';
                $olgo = 113;
                break;
            case '4':
                $text = '114 ';
                $olgo = 114;
                break;
        }

        tel_sms($tel, $text, $olgo, $_SESSION['act-call']['id'], $_SESSION['act-call']['box']);
    }
}

if (isset($_POST['general-sms'])) {
    if (!empty($_POST['detail'])) {
        $sms_text = $_POST['detail'];
        $sms_info = retTelDb($_SESSION['act-call']['id']); // گرفتن اطلاعات تماس

        // فقط موبایل 1
        $mobile = $sms_info['mob1'];

        if (!empty($mobile)) {
            $username = '09127945631'; // نام کاربری پیامکی
            $password = '&Amoozesh!1404%'; // پسورد پیامکی

            $data = [
                'username' => $username,
                'password' => $password,
                'to' => $mobile,
                'from' => '50004001945631', // شماره خط پیامکی شما
                'text' => $sms_text
            ];

            $ch = curl_init('https://rest.payamak-panel.com/api/SendSMS/SendSMS');
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            // ثبت در دیتابیس
            date_default_timezone_set("Asia/Tehran");
            $datenow = date("Y-m-d");
            $timenow = date("H:i:s");
            $insert = 'INSERT INTO tel_sms(dbtel, txt, box, useradder, datecreated, timecreated, response) VALUES (?,?,?,?,?,?,?)';
            $rtl = $conn->prepare($insert);
            $rtl->execute([$_SESSION['act-call']['id'], $sms_text, 'mob1', $_SESSION['admin']['user'], $datenow, $timenow, $response]);
        } else {
            echo "<div class='alert alert-danger'>شماره موبایل 1 معتبر نیست!</div>";
        }
    }
}


if (isset($_GET['call-mob1'])) {
$call = retTelDb($_GET['call-mob1']);
if ($call) {
call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
$_SESSION['act-call'] = array(
"id" => $_GET['call-mob1'],
"box" => 'mob1',
"group" => $call['group'],
"state" => $call['state'],
"city" => $call['city'],
"act" => $_SESSION['act-call']['act'],
"fullname" => $call['fname'] . ' ' . $call['lname']
);

header('location: ' . url('admin/Tel/calling.php'));
die();
}
}
if (isset($_GET['call-mob2'])) {
$call = retTelDb($_GET['call-mob2']);
if ($call) {
call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
$_SESSION['act-call'] = array(
"id" => $_GET['call-mob2'],
"box" => 'mob2',
"group" => $call['group'],
"state" => $call['state'],
"city" => $call['city'],
"act" => $_SESSION['act-call']['act'],
"fullname" => $call['fname'] . ' ' . $call['lname']
);
header('location: ' . url('admin/Tel/calling.php'));
die();
}
}
if (isset($_GET['call-mob3'])) {
$call = retTelDb($_GET['call-mob3']);
if ($call) {
call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
$_SESSION['act-call'] = array(
"id" => $_GET['call-mob3'],
"group" => $call['group'],
"state" => $call['state'],
"city" => $call['city'],
"box" => 'mob3',
"act" => $_SESSION['act-call']['act'],
"fullname" => $call['fname'] . ' ' . $call['lname']
);
header('location: ' . url('admin/Tel/calling.php'));
die();
}
}
if (isset($_GET['call-static1'])) {
$call = retTelDb($_GET['call-static1']);
if ($call) {
call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
$_SESSION['act-call'] = array(
"id" => $_GET['call-static1'],
"box" => 'static1',
"group" => $call['group'],
"state" => $call['state'],
"city" => $call['city'],
"act" => $_SESSION['act-call']['act'],
"fullname" => $call['fname'] . ' ' . $call['lname']
);
header('location: ' . url('admin/Tel/calling.php'));
die();
}
}
if (isset($_GET['call-static2'])) {
$call = retTelDb($_GET['call-static2']);
if ($call) {
call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
$_SESSION['act-call'] = array(
"id" => $_GET['call-static2'],
"box" => 'static2',
"group" => $call['group'],
"state" => $call['state'],
"city" => $call['city'],
"act" => $_SESSION['act-call']['act'],
"fullname" => $call['fname'] . ' ' . $call['lname']
);
header('location: ' . url('admin/Tel/calling.php'));
die();
}
}
if (isset($_GET['call-static3'])) {
$call = retTelDb($_GET['call-static3']);
if ($call) {
// call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
$_SESSION['act-call'] = array(
"id" => $_GET['call-static3'],
"box" => 'static3',
"group" => $call['group'],
"state" => $call['state'],
"city" => $call['city'],
"act" => $_SESSION['act-call']['act'],
"fullname" => $call['fname'] . ' ' . $call['lname']
);
header('location: ' . url('admin/Tel/calling.php'));
die();
}
}
include_once '../../temp.html';
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
    <title> درحال برقراری تماس |<?php echo __TITLE ?></title>
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
                    <div class="row row-sm">
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <h6 class="main-content-label mb-1">ثبت گزارش تماس</h6>
                                                <p class="text-muted card-sub-title"><img
                                                        style="width: 25px; height: 25px;"
                                                        src="../../assets/img/call1.gif">در
                                                    حال برقراری ارتباط با: <span class="text-black"
                                                        style="font-size: 16px;"><?php echo $_SESSION['act-call']['fullname'] ?></span>
                                                    از گروه :
                                                    <span class="text-warning">
                                                        <?php $group = retTelGroup($_SESSION['act-call']['group']);
                                                        echo $group['namegroup'];
                                                        ?>
                                                    </span>
                                                </p>
                                                <p class="text-muted card-sub-title">استان :
                                                    <span
                                                        class="text-black"><?php echo $_SESSION['act-call']['state'] ?>
                                                    </span>
                                                    | شهر :
                                                    <span class="text-black"><?php echo $_SESSION['act-call']['city'] ?>
                                                    </span>
                                                    | تماس با :
                                                    <span class="text-black"><?php echo $box ?>
                                                    </span>
                                                    | آخرین وضعیت قبلی :
                                                    <span class="text-black"><?php
                                                                                switch ($_SESSION['act-call']['act']) {
                                                                                    case '0':
                                                                                        $act = 'تماس گرفته نشده ';
                                                                                        break;
                                                                                    case '1':
                                                                                        $act = 'نیاز به تماس مجدد';
                                                                                        break;
                                                                                    case '2':
                                                                                        $act = 'تماس پایان یافته';
                                                                                        break;
                                                                                    case '3':
                                                                                        $act = 'شماره اشتباه';
                                                                                        break;
                                                                                    case '4':
                                                                                        $act = 'شماره تکراری';
                                                                                        break;
                                                                                    case '5':
                                                                                        $act = 'عدم پاسخ';
                                                                                        break;
                                                                                }

                                                                                echo $act ?>
                                                    </span>


                                                </p>
                                            </div>

                                        </div>
                                        <div class="col-3 text-left">
                                            <a href="?back" class="btn btn-sm btn-primary"><i
                                                    class="fa fa-backward"></i> بازگشت به صفحه قبل </a>

                                        </div>
                                    </div>

                                    <hr>
                                    <div class="row">
                                        <div class="col-6">
                                        </div>
                                        <div class="col-6 ">
                                            <div class="text-wrap">
                                                <div class="text-left">
                                                    <div class="dropdown">
                                                        <button aria-expanded="false" aria-haspopup="true"
                                                            class="btn ripple btn-outline-success dropdown-toggle"
                                                            data-bs-toggle="dropdown" type="button">
                                                            نیاز به تماس مجدد دارید اکنون؟ <i
                                                                class="fas fa-caret-down mr-1"></i>
                                                        </button>
                                                        <div class="dropdown-menu tx-8">
                                                            <a class="dropdown-item  "
                                                                href="?call-mob1=<?php echo $_SESSION['act-call']['id'] ?>">
                                                                <i class="fe fe-phone-call"></i>تماس
                                                                موبایل 1
                                                            </a>
                                                            <a class="dropdown-item  "
                                                                href="?call-mob2=<?php echo $_SESSION['act-call']['id'] ?>">
                                                                <i class="fe fe-phone-call"></i>تماس
                                                                موبایل 2
                                                            </a>

                                                            <a class="dropdown-item  "
                                                                href="?call-mob3=<?php echo $_SESSION['act-call']['id'] ?>">
                                                                <i class="fe fe-phone-call"></i>تماس
                                                                موبایل 3
                                                            </a>
                                                            <hr>
                                                            <a class="dropdown-item  "
                                                                href="?call-static1=<?php echo $_SESSION['act-call']['id'] ?>">
                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                ثابت 1
                                                            </a>
                                                            <a class="dropdown-item  "
                                                                href="?call-static2=<?php echo $_SESSION['act-call']['id'] ?>">
                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                ثابت 2
                                                            </a>
                                                            <a class="dropdown-item  "
                                                                href="?call-static3=<?php echo $_SESSION['act-call']['id'] ?>">
                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                ثابت 3
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <div class="row">
                                <div class="col-xl-6">
                                    <div class="card custom-card">
                                        <div class="card-body">
                                            <h6 class="main-content-label mb-1">اقدامات تماس</h6>
                                            <form method="post" data-parsley-validate="">
                                                <div class="row">


                                                    <div class="col-xl-12 mb-2">
                                                        <label for="sens" class="form-label mg-t-15">اقدام لازم<span
                                                                style="color: red">*</span>
                                                        </label>
                                                        <select class="form-control select2 " name="act" id="act"
                                                            onchange="actHandler()" aria-describedby="basic-addon2"
                                                            required>
                                                            <option label="اقدام لازم">
                                                            </option>
                                                            <option value="1">نیاز به تماس مجدد</option>
                                                            <option value="2">تماس پایان یافته</option>
                                                            <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                            <option value="4">شماره تکراری است</option>
                                                            <option value="5">عدم پاسخ</option>

                                                        </select>
                                                    </div>
                                                    <div class="col-xl-12 mb-2 detail">
                                                        <p class="mg-b-10 "> توضیحات تماس 1 <span
                                                                style="color: red">*</span></p>
                                                        <textarea class="form-control" name="detail"
                                                            required></textarea>
                                                    </div>
                                                    <div class="col-xl-12 mb-2">
                                                        <p class="mg-b-10 "> توضیحات تماس 2 <span
                                                                style="color: red">*</span></p>
                                                        <select class="form-control select2" required=""
                                                            name="detail2[]" multiple aria-describedby="basic-addon2">
                                                            <option label="نوع دانش آموز">
                                                            </option>
                                                            <?php
                                                            $sql = "SELECT * FROM type_vip";
                                                            $stmt = $conn->prepare($sql);
                                                            $stmt->execute();
                                                            $vips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                            foreach ($vips as $vip) {
                                                            ?>
                                                                <option value="<?= $vip->name ?>"><?= $vip->name ?></option>
                                                            <?php } ?>
                                                            <option value="نامعلوم">نامعلوم</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-xl-12 mb-2">
                                                        <p class="mg-b-10 "> توضیحات تماس 3 <span
                                                                style="color: red">*</span></p>
                                                        <textarea class="form-control" name="detail3"
                                                            required></textarea>
                                                    </div>
                                                    <div class="text-center mg-10">
                                                        <button type="submit" class="btn btn-success "
                                                            name="save-comment"><i class="fa fa-save"></i>
                                                            ثبت اطلاعات و بازگشت به منوی قبل
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>

                                        </div>
                                    </div>

                                </div>
                                <div class="col-xl-6">
                                    <div class="card custom-card">
                                        <div class="card-body">
                                            <h6 class="main-content-label mb-1">ارسال پیامک خدماتی تعیین شده </h6>
                                            <hr>
                                            <form method="post" data-parsley-validate="">
                                                <div class="row">

                                                    <div class="col-8">


                                                        <select class="form-control select2 " name="act"
                                                            aria-describedby="basic-addon2" required>
                                                            <option label="اقدام لازم">
                                                            </option>
                                                            <option value="1" disabled>الگو1 / ویژه مدیران</option>
                                                            <option value="2">الگو 2 / ویژه دانش آموزان</option>
                                                            <option value="3" disabled>الگو 3 / ارسال لینک ثبت نام
                                                            </option>
                                                            <option value="4" disabled>الگو 4 / ارسال لینک گروه ایتا
                                                            </option>
                                                        </select>
                                                        <?php
                                                        $hash = bin2hex(random_bytes(32));
                                                        $_SESSION['token'] = $hash;
                                                        ?>
                                                        <input class="form-control" name="token" type="hidden"
                                                            value="<?php echo $hash ?>">
                                                    </div>
                                                    <div class="col-4">
                                                        <button type="submit" class="btn btn-success " name="olgo"><i
                                                                class="fe fe-message-square"></i>
                                                            ارسال پیامک
                                                        </button>
                                                    </div>

                                                </div>
                                            </form>


                                        </div>
                                    </div>

                                    <div class="card custom-card">
                                        <div class="card-body">
                                            <h6 class="main-content-label mb-1">ارسال پیامک عادی </h6>
                                            <hr>
                                            <form method="post" data-parsley-validate="">
                                                <div class="row">
                                                    <div class="col-8">
                                                        <textarea class="form-control" name="detail" required></textarea>
                                                    </div>
                                                    <div class="col-4">
                                                        <button type="submit" class="btn btn-info " name="general-sms"><i class="fe fe-message-square"></i>
                                                            ارسال پیامک
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>


                                        </div>
                                    </div>

                                </div>
                            </div>
                            <div class="card custom-card">
                                <div class="card-body">
                                    <h6 class="main-content-label mb-1">اقدامات ثبت شده تماس</h6>
                                    <table class="table text-nowrap text-md-nowrap table-bordered">
                                        <thead>
                                            <tr>
                                                <th class="wd-150">تاریخ</th>
                                                <th class="wd-150">ثبت کننده</th>
                                                <th class="wd-100">تماس با</th>
                                                <th>توضیحات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $pri = 'SELECT * FROM tel_detail WHERE dbtel=?';
                                            $wer = $conn->prepare($pri);
                                            $wer->execute([$_SESSION['act-call']['id']]);
                                            $result = $wer->fetchAll(PDO::FETCH_OBJ);
                                            if ($wer->rowCount() > 0) {
                                                foreach ($result as $ret) {

                                            ?>
                                                    <tr>
                                                        <?php
                                                        $dateno = $ret->datecreated;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        $dateno = $ret->timecreated . ' - ' . $dateno;
                                                        ?>
                                                        <td><?php echo $dateno ?></td>
                                                        <td><?php $admin = retAdmin($ret->useradder);
                                                            echo $admin->fname . ' ' . $admin->lname; ?></td>
                                                        <td>
                                                            <?php
                                                            switch ($ret->box) {
                                                                case 'mob1':
                                                                    $boox = 'موبایل 1';
                                                                    break;
                                                                case 'mob2':
                                                                    $boox = 'موبایل 2';
                                                                    break;
                                                                case 'mob3':
                                                                    $boox = 'موبایل 3';
                                                                    break;
                                                                case 'static1':
                                                                    $boox = 'تلفن ثابت 1';
                                                                    break;
                                                                case 'static2':
                                                                    $boox = 'تلفن ثابت 2';
                                                                    break;
                                                                case 'static3':
                                                                    $boox = 'تلفن ثابت 3';
                                                                    break;
                                                            }
                                                            echo $boox;
                                                            ?>

                                                        </td>
                                                        <td><?php echo htmlentities($ret->txt) ?></td>
                                                    </tr>
                                            <?php }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card custom-card">
                                <div class="card-body">
                                    <h6 class="main-content-label mb-1">پیامک های ارسالی</h6>
                                    <table class="table text-nowrap text-md-nowrap table-bordered">
                                        <thead>
                                            <tr>
                                                <th class="wd-150">تاریخ</th>
                                                <th class="wd-150">ارسال کننده</th>
                                                <th class="wd-100">پیام به</th>
                                                <th>متن/الگو پیامک</th>
                                                <th class="wd-100">نتیجه ارسال</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $prs = 'SELECT * FROM tel_sms WHERE dbtel=?';
                                            $wers = $conn->prepare($prs);
                                            $wers->execute([$_SESSION['act-call']['id']]);
                                            $results = $wers->fetchAll(PDO::FETCH_OBJ);
                                            if ($wers->rowCount() > 0) {
                                                foreach ($results as $rets) {

                                            ?>
                                                    <tr>
                                                        <?php
                                                        $datenoo = $rets->datecreated;
                                                        $datenoo = explode('-', $datenoo);
                                                        $datenoo = gregorian_to_jalali($datenoo[0], $datenoo[1], $datenoo[2]);
                                                        $datenoo = implode('/', $datenoo);
                                                        $datenoo = $rets->timecreated . ' - ' . $datenoo;
                                                        ?>
                                                        <td><?php echo $datenoo ?></td>
                                                        <td><?php $admins = retAdmin($rets->useradder);
                                                            echo $admins->fname . ' ' . $admins->lname; ?></td>
                                                        <td>
                                                            <?php
                                                            switch ($rets->box) {
                                                                case 'mob1':
                                                                    $keyvan = 'موبایل 1';
                                                                    break;
                                                                case 'mob2':
                                                                    $keyvan = 'موبایل 2';
                                                                    break;
                                                                case 'mob3':
                                                                    $keyvan = 'موبایل 3';
                                                                    break;
                                                                case 'static1':
                                                                    $keyvan = 'تلفن ثابت 1';
                                                                    break;
                                                                case 'static2':
                                                                    $keyvan = 'تلفن ثابت 2';
                                                                    break;
                                                                case 'static3':
                                                                    $keyvan = 'تلفن ثابت 3';
                                                                    break;
                                                            }
                                                            echo $keyvan;
                                                            ?>
                                                        </td>
                                                        <td><?php echo $rets->txt ?></td>
                                                        <td><?php if (strlen($rets->response) > 5) {
                                                                echo 'ارسال شده';
                                                            } else {
                                                                echo 'خطا';
                                                            } ?>
                                                        </td>
                                                    </tr>
                                            <?php
                                                }
                                            }
                                            ?>

                                        </tbody>
                                    </table>
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

        <script>
            const act_select_element = document.querySelector('#act');

            function actHandler() {
                if (act_select_element.value == 1) {

                    let datepicker = `<div class="col-xl-4 mb-2 datepicker">
                                                    <label class="form-label">تاریخ تماس بعدی:<span
                                                            style="color: red">*</span></label>

                                                        <div class="input-group mg-t-10">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text">
                                                                    <i class="fe fe-calendar lh--9 op-6"></i>
                                                                </div>
                                                            </div>
                                                            <input class="form-control fc-datepicker"
                                                                placeholder="1404/02/12" required type="text"
                                                                name="nextcall">
                                                        </div>
                                                </div>`;
                    document.querySelector('.detail').insertAdjacentHTML('beforebegin', datepicker);

                    $(".fc-datepicker").datepicker({
                        format: 'YYYY/MM/DD'
                    });
                } else {
                    if (document.querySelector('.datepicker')) {
                        document.querySelector('.datepicker').remove();
                    }
                }
            }
        </script>
</body>

</html>