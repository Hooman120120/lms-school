<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
include_once '../temp.html';


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
$dbcourse = 'SELECT * FROM `dbcourse` INNER JOIN `codtbl_course` ON dbcourse.codecourse = codtbl_course.codecourse WHERE username = ? AND codtbl_course.activecourse = 1';
$query = $conn->prepare($dbcourse);
$query->execute([$_SESSION['auth']['user']]);
$userCourses = $query->fetchAll(PDO::FETCH_OBJ);
$admin = null;
if ($query->rowCount() > 0) {
    foreach ($userCourses as $userCourse) {
        if (!empty($userCourse->suporter)) {
            $admin = $userCourse->suporter;
            break;
        }
    }
} else { 
    $noSupporter = 1;
    $message = 'شما دوره خریداری شده فعال ندارید . ';
}
if (is_null($admin)) { 
    $admin = 'managers';
}
if (!(isset($noSupporter) and $noSupporter == 1)) {
    $sql = 'SELECT * FROM suport_message WHERE receiver_username = ? AND status = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_SESSION['auth']['user'], 'sent']);
    if ($stmt->rowCount() > 0) {
        $update = 'UPDATE suport_message SET status = ? WHERE receiver_username = ?';
        $stmt = $conn->prepare($update);
        $stmt->execute(['seen', $_SESSION['auth']['user']]);
    }
}

if (isset($_POST['sub'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {

        $message_text = $_POST['txt'];
        $sender_username = $_SESSION['auth']['user'];
        $sender_type = $_SESSION['auth']['role'] == 1 ? 'student' : 'colleague';
        $ip = GetIp();
        $receiver_type = 'admin';

        $dbcourse = 'SELECT * FROM `dbcourse` WHERE username=?';
        $query = $conn->prepare($dbcourse);
        $query->execute([$_SESSION['auth']['user']]);
        $userCourses = $query->fetchAll(PDO::FETCH_OBJ);
        $suporter = null;
        if ($query->rowCount() > 0) {
            foreach ($userCourses as $userCourse) {
                if (!empty($userCourse->suporter)) {
                    $suporter = $userCourse->suporter;
                    break;
                }
            }
            if (is_null($suporter)) {
                $suporter = 'managers';
            }
        }
        $receiver_username = $suporter;
        $status = 'sent';
        if ($_FILES['fileto'] and $_FILES['fileto']['error'] === 0 and $_FILES['fileto']['size'] > 0) {
            $maxSize = 3 * 1024 * 1024;
            $file = $_FILES['fileto'];
            if ($_FILES['fileto']['size'] > $maxSize) {
                $error = 1;
                $errorMessage = 'حجم فایل نباید بیشتر از 3 مگابایت باشد.';
            } else {
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                $fileType = mime_content_type($file['tmp_name']);
                if (!in_array($fileType, $allowedTypes)) {
                    $error = 1;
                    $errorMessage = 'فرمت فایل بارگزاری شده مجاز نیست. فرمت های مجاز : png,jpg,jpeg,pdf';
                } else {
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/public';
                    $fileName = uniqid() . '_' . basename($file['name']);
                    $uploadPath = $uploadDir . '/' . $fileName;
                    $upload_path_for_db = '/assets/public/' . $fileName;

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        date_default_timezone_set('Asia/Tehran');
                        $datenow = date("Y-m-d");
                        $timenow = date('H:i:s');
                        if (isset($_GET['edit'])) {
                            $sql = 'SELECT * FROM suport_message WHERE id = ?';
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$_GET['edit']]);
                            $message = $stmt->fetch(PDO::FETCH_OBJ);
                            $datetime = $message->message_date . ' ' . $message->message_time;
                            if (time() - strtotime($datetime) <= 3600 or Permision('permision_manage_chat')) {
                                if (!empty($message->file_path)) {
                                    $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
                                    if (file_exists($file_path)) {
                                        unlink($file_path);
                                    }
                                }
                                $sql = 'UPDATE suport_message SET message_text = ?, file_path = ?, edit_message_date = ?, edit_message_time = ? WHERE id = ?';
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$message_text, $upload_path_for_db, $datenow, $timenow, $_GET['edit']]);
                                header('Location: Message.php');
                                die();
                            } else {
                                $_SESSION['error_message'] = 'امکان ویرایش پیام بعد از یک ساعت وجود ندارد.';
                                header('Location: Message.php');
                                die();
                            }
                        } else {
                            $sql = 'INSERT INTO suport_message (sender_type,sender_username,sender_ip,receiver_type,receiver_username,message_text,status,file_path,message_date,message_time) VALUES (?,?,?,?,?,?,?,?,?,?)';
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$sender_type, $sender_username, $ip, $receiver_type, $receiver_username, $message_text, $status, $upload_path_for_db, $datenow, $timenow]);
                        }
                    }
                }
            }
        } elseif (!empty($_FILES['fileto']['name']) and $_FILES['fileto']['error']) {
            $error = 1;
            $errorMessage = 'خطای غیرمنتظره در بارگزاری فایل ! حجم و فرمت فایل را چک نمایید';
        } else {
            date_default_timezone_set('Asia/Tehran');
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            if (isset($_GET['edit'])) {
                $sql = 'SELECT * FROM suport_message WHERE id = ?';
                $stmt = $conn->prepare($sql);
                $stmt->execute([$_GET['edit']]);
                $message = $stmt->fetch(PDO::FETCH_OBJ);
                $datetime = $message->message_date . ' ' . $message->message_time;
                if (time() - strtotime($datetime) <= 3600 or Permision('permision_manage_chat')) {
                    $sql = 'UPDATE suport_message SET message_text = ?, edit_message_date = ?, edit_message_time = ? WHERE id = ?';
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$message_text, $datenow, $timenow, $_GET['edit']]);
                    header('Location: Message.php');
                    die();
                } else {
                    $_SESSION['error_message'] = 'امکان ویرایش پیام بعد از یک ساعت وجود ندارد.';
                    header('Location: Message.php');
                    die();
                }
            } else {
                $sql = 'INSERT INTO suport_message (sender_type,sender_username,sender_ip,receiver_type,receiver_username,message_text,status, message_date, message_time) VALUES (?,?,?,?,?,?,?,?,?)';
                $stmt = $conn->prepare($sql);
                $stmt->execute([$sender_type, $sender_username, $ip, $receiver_type, $receiver_username, $message_text, $status, $datenow, $timenow]);
            }
        }
    }
}

if (isset($_GET['delm'])) {
    $sql = 'SELECT * FROM suport_message WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['delm']]);
    $message = $stmt->fetch(PDO::FETCH_OBJ);
    $datetime = $message->message_date . ' ' . $message->message_time;
    if (time() - strtotime($datetime) <= 3600) {
        if ($message && !empty($message->file_path)) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sql = 'DELETE FROM suport_message WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['delm']]);
        header('Location: Message.php');
        die();
    } else {
        $_SESSION['error_message'] = 'امکان حذف پیام بعد از یک ساعت وجود ندارد.';
        header('Location: Message.php');
        die();
    }

}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<style>
    #ChatBody {
        height: 400px !important;
        overflow-y: scroll !important;
        direction: rtl !important;
        text-align: right !important;
        padding: 10px !important;
        box-sizing: border-box !important;
        unicode-bidi: plaintext !important;
    }
</style>

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> گفتگو با پشتیبان | <?php echo __TITLE ?></title>
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
            <div class="container-fluid px-2">
                <div class="inner-body">
                    <div class="row no-gutters">
                    <div class="col-lg-12 col-xl-11 col-md-10 col-sm-12 mx-auto">
                        <div class="scroll-height">
                            <?php if (isset($error) and $error == 1) { ?>
                                <div class="alert alert-danger" role="alert">
                                    <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                    <?= $errorMessage ?>
                                </div>
                            <?php } elseif (isset($_SESSION['error_message'])) { ?>
                                <div class="alert alert-danger" role="alert">
                                    <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                    <?= $_SESSION['error_message'] ?>
                                </div>
                                <?php unset($_SESSION['error_message']);
                            }
                            if (isset($noSupporter) and $noSupporter == 1) { ?>
                                <div class="alert alert-danger" role="alert">
                                    <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                    <?= $message ?>
                                </div>
                            <?php } elseif (isset($_SESSION['no_message'])) { ?>
                                <div class="alert alert-danger" role="alert">
                                    <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                    <?= $_SESSION['no_message'] ?>
                                </div>
                                <?php unset($_SESSION['no_message']);
                            } else { ?>
                                <div class="card custom-card">
                                    <div class="card-header p-3 tx-medium my-auto tx-white bg-success-gradient">
                                        گفتگو با پشتیبان
                                    </div>
                                    <form method="post" enctype="multipart/form-data">
                                        <div class="main-chat-footer">
                                            <nav class="nav">

                                                <a class="nav-link" data-bs-toggle="tooltip" href="#"
                                                    title="یک فایل را پیوست کنید"></a>
                                                <label class="input-group-btn">

                                                    <i class="fe fe-paperclip"></i> <input type="file" name="fileto"
                                                        accept=".pdf,.jpg,.jpeg,.png" style="display: none;" multiple="">

                                                </label>
                                            </nav>
                                            <?php
                                            if (isset($_GET['edit'])) {
                                                $sql = "SELECT * FROM suport_message WHERE id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['edit']]);
                                                $message = $stmt->fetch(PDO::FETCH_OBJ);

                                            }
                                            ?>

                                            <input class="form-control" name="txt" <?php if (isset($_GET['edit'])) { ?>
                                                    value="<?= $message->message_text ?>" <?php } ?>
                                                placeholder="پیام خود را اینجا بنویسید ..." type="text" required>
                                            <?php 
                                            $hash = bin2hex(random_bytes(32));
                                            $_SESSION['token'] = $hash;
                                            ?>
                                            <input class="form-control" name="token" type="hidden"
                                                value="<?php echo $hash ?>">
                                            <button type="submit" name="sub" class="main-msg-send"
                                                style="display: contents"><i class="far fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </form>
                                    <hr>

                                    <div class="card-body">
                                        <div class="main-chat-body" id="ChatBody">
                                            <?php 
                                            $sql = 'SELECT * FROM suport_message WHERE sender_username = ? OR receiver_username = ? ORDER BY message_date DESC, message_time DESC';
                                            $stmt = $conn->prepare($sql);
                                            $stmt->execute([$_SESSION['auth']['user'], $_SESSION['auth']['user']]);
                                            $messages = $stmt->fetchAll(PDO::FETCH_OBJ);
                                            foreach ($messages as $message) {
                                                if ($message->sender_username == $_SESSION['auth']['user']) {
                                                    ?>
                                                    <div class="media">
                                                    <?php } else { ?>
                                                        <div class="media flex-row-reverse">
                                                        <?php } ?>
                                                        <div class="main-img-user online">
                                                            <?php if ($message->sender_type == 'student') { ?>
                                                                <img alt="پروفایل" src="<?= assest('/assets/img/users/1.jpg') ?>"
                                                                    class="my-2">
                                                            <?php } ?>
                                                        </div>
                                                        <div class="main-msg-wrapper mr-3">
                                                            <?php
                                                            if ($message->sender_type == 'admin') {
                                                                $admin = retAdmin($message->sender_username);
                                                                switch ($admin->dakheli) {
                                                                    case '201':
                                                                        $sender = 'اپراتور ارشد 1';
                                                                        break;
                                                                    case '500':
                                                                        $sender = 'مدیریت موسسه';
                                                                        break;
                                                                    case '220':
                                                                        $sender = 'اپراتور ارشد 2';
                                                                    case '501':
                                                                        $sender = 'مهندس بلالی';
                                                                        break;
                                                                    default:
                                                                        $sender = "اپراتور $admin->dakheli";
                                                                        break;
                                                                }
                                                                echo '<div class="text-muted small mb-1">' . $sender . '</div>';
                                                            }
                                                            ?>
                                                            <span class="tx-12"></span>
                                                            <div class="media-body mr-3">
                                                                <span><?= $message->message_text ?></span>
                                                            </div>
                                                            <div>

                                                                <?php if (!empty($message->file_path)) { ?>
                                                                    <a href="<?php echo url('Support/attachments.php') . '?fileChat=' . urlencode($message->id) ?>"
                                                                        target="_blank" title="دانلود فایل بارگزاری شده"> <i
                                                                            class="fe fe-paperclip"></i></a>
                                                                <?php }
                                                                $datetime = $message->message_date . ' ' . $message->message_time;
                                                                if (($message->sender_username == $_SESSION['auth']['user']) and (time()
                                                                 - strtotime($datetime) <= 3600)) { ?>
                                                                    <a href="?edit=<?= $message->id ?>" title="ویرایش"><i
                                                                            class="fe fe-edit"></i></a>
                                                                    <a href="?delm=<?php echo $message->id ?>"
                                                                        onclick="return confirm('آیا با حذف این پیام موافقید؟')">
                                                                        <i class="fe fe-trash-2"></i></a>
                                                                <?php }
                                                                if (($message->sender_username == $_SESSION['auth']['user']) and ($message->status == 'sent')) { ?>
                                                                    <i class=" text-success fa fa-check"></i>
                                                                <?php } elseif (($message->sender_username == $_SESSION['auth']['user']) and ($message->status == 'seen')) { ?>
                                                                    <i class=" text-success fa fa-check"></i>
                                                                    <i class=" text-success fa fa-check"></i>
                                                                <?php }


                                                                if (!empty($message->edit_message_date)) {
                                                                    [$year, $month, $day] = explode('-', $message->edit_message_date);
                                                                    [$jy, $jm, $jd] = gregorian_to_jalali($year, $month, $day);
                                                                    $jDateTime = $message->edit_message_time . ' | ' . $jy . '/' . $jm . '/' . $jd;
                                                                } else {
                                                                    [$year, $month, $day] = explode('-', $message->message_date);
                                                                    [$jy, $jm, $jd] = gregorian_to_jalali($year, $month, $day);
                                                                    $jDateTime = $message->message_time . ' | ' . $jy . '/' . $jm . '/' . $jd;
                                                                }
                                                                ?>
                                                                <span class="text-muted">
                                                                    <?php if (!empty($message->edit_message_date)) { ?>
                                                                        تاریخ <strong>ویرایش</strong>
                                                                        <?= $jDateTime ?>
                                                                    <?php } else { ?>
                                                                        تاریخ ارسال
                                                                        <?= $jDateTime ?>
                                                                    <?php } ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function (e) {
            const fileInput = document.querySelector('input[type="file"]');
            const textInput = document.querySelector('input[name="txt"]');
            const files = fileInput.files;
            const maxSize = 3 * 1024 * 1024;

            for (let i = 0; i < files.length; i++) {
                if (files[i].size > maxSize) {
                    alert('حجم فایل باید کمتر از 3 مگابایت باشد.');

                    fileInput.value = '';
                    textInput.value = '';

                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>



    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>

</html>