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
if (!AdminCanUsePermissionScope('dashboard.summary.overview.manager.ready-messages', ['permision_manage_chat'])) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['addmessage'])) {
    $message_text = $_POST['message_text'];
    $sql = "INSERT INTO suport_message_constant (content) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$message_text]);
    header('Location: ready-message.php');
    die();
}

if (isset($_GET['delete']) and !isset($_GET['delete_all_usages'])) {
    $sql = "DELETE FROM suport_message_constant WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id']]);
    $_SESSION['message'] = 'پیام آماده موردنظر با موفقیت حذف شد';
    header('Location: ready-message.php');
    die();
}

if (isset($_GET['delete']) and isset($_GET['delete_all_usages'])) {
    $select = "SELECT * FROM suport_message_constant WHERE id = ?";
    $stm = $conn->prepare($select);
    $stm->execute([$_GET['id']]);
    $message = $stm->fetch(PDO::FETCH_OBJ);
    $content = $message->content;
    //
    $sql = "DELETE FROM suport_message_constant WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id']]);
    //
    $del = "DELETE FROM suport_message WHERE message_text = ?";
    $stmt2 = $conn->prepare($del);
    $stmt2->execute([$content]);
    $_SESSION['message'] = 'پیام های ارسال شده و پیام آماده با موفقیت حذف شدند';
    header('Location: ready-message.php');
    die();
}
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> پیام های آماده | <?php echo __TITLE ?></title>
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

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <?php if (isset($_SESSION['message'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?php echo $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']);
                    } ?>
                    <div class="col-lg-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-xl-12">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:12px;">
                                                <h6 class="main-content-label mb-1">افزودن پیام آماده</h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید پیام‌های آماده برای استفاده در گفتگو با دانش آموزان را ایجاد و مدیریت کنید. این پیام‌ها به کارشناسان و پشتیبانان کمک می‌کنند تا پاسخ‌های متداول را سریع‌تر، یکپارچه‌تر و با حفظ کیفیت برای دانش آموزان ارسال کنند."
                                                      aria-label="راهنمای افزودن پیام آماده"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                            </div>

                                            <p class="text-muted card-sub-title">
                                                پیام‌های آماده برای گفتگو با دانش آموزان
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php if (isset($erordublicate)) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $erordublicate ?>
                                    </div>
                                <?php } ?>
                                <?php if (isset($erortoken)) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $erortoken ?>
                                    </div>
                                <?php } ?>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">
                                        <div class="col-lg-12 mg-t-20">
                                            <p class="mg-b-10">متن پیام:<span class="tx-danger">*</span></p>
                                            <textarea class="form-control" name="message_text"
                                                      placeholder="پیام خود را بنویسید ..." required></textarea>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="text-center mg-t-30">
                                            <button class="btn ripple  btn-success pd-x-20" type="submit"
                                                    name="addmessage">ثبت پیام آماده
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-xl-12">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">لیست پیام‌های آماده</h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش تمامی پیام‌های آماده ثبت‌شده در سامانه نمایش داده می‌شوند. این پیام‌ها برای استفاده در گفتگوی برخط با دانش آموزان طراحی شده‌اند تا کارشناسان و پشتیبانان بتوانند پاسخ‌های متداول را سریع‌تر و به‌صورت یکپارچه ارسال کنند. همچنین از طریق این صفحه امکان مشاهده، ویرایش و حذف پیام‌های آماده نیز وجود دارد."
                                                  aria-label="راهنمای لیست پیام‌های آماده"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                        </div>

                                        <p class="text-primary card-sub-title">
                                            گفتگوی برخط با دانش آموزان
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>متن پیام</th>
                                            <th>تعداد ارسال</th>
                                            <th>ویرایش</th>
                                            <th>حذف</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT *, (SELECT COUNT(*) FROM suport_message WHERE suport_message.message_text = suport_message_constant.content ) AS count FROM suport_message_constant ORDER BY count DESC';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            $rt = 1;
                                            foreach ($result as $message) {
                                                ?>

                                                <tr>
                                                    <td class="wd-15"><?php echo $rt ?></td>
                                                    <td class="wd-15"><?= $message->content ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?= $message->count ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-success btn-icon"
                                                             onclick="window.open('edit-ready-message.php?id='+'<?php echo $message->id ?>','ویرایش پیام آماده'
                                                                     ,'width=1000,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                            <i class="fe fe-edit"></i>
                                                        </div>
                                                    </td>
                                                    <td class="wd-15">
                                                        <!-- <a href="?id=<?php echo $message->id ?>&delete=true"
                                                                    onclick="return confirm('آیا با حذف این پیام آماده  موافقید؟ این اقدام غیر قابل بازگشت است!')"
                                                                    class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                        class="fe fe-x"></i></a> -->
                                                        <a href="#"
                                                           onclick="return deleteWithOptions(<?= $message->id ?>)"
                                                           class="btn btn-sm btn-danger btn-icon">
                                                            <i class="fe fe-x"></i>
                                                        </a>

                                                    </td>
                                                </tr>
                                                <?php
                                                $rt++;
                                            }
                                        } ?>
                                        </tbody>
                                    </table>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->
    <script>
        function deleteWithOptions(id) {
            const answer = confirm("آیا می‌خواهید فقط این پیام آماده حذف شود؟\nاگر می‌خواهید همه پیام های ارسال شده هم حذف شود، روی «cancel» کلیک کنید.");

            if (answer) {

                window.location.href = `?id=${id}&delete=true`;
            } else {

                if (confirm("آیا مطمئن هستید که همه پیام های ارسال شده نیز حذف شوند؟")) {
                    window.location.href = `?id=${id}&delete=true&delete_all_usages=1`;
                }
            }

            return false;
        }
    </script>


</body>

</html>