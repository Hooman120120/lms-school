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
$user = retPerson($_GET['Id']);

if (!$user) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!empty($user->birthdate) && $user->birthdate !== '0000-00-00') {
    try {
        $tz    = new DateTimeZone('Asia/Tehran');
        $birth = new DateTime($user->birthdate, $tz);
        $today = new DateTime('now', $tz);

        // تاریخ امروز به جلالی
        list($jy, $jm, $jd) = gregorian_to_jalali(
            (int)$today->format('Y'),
            (int)$today->format('m'),
            (int)$today->format('d')
        );

        // تعیین سال تحصیلی (شروع از ۱ مهر)
        if ($jm >= 7) {
            // از مهر تا اسفند → سال تحصیلی از ۱ مهر همین سال شروع شده
            $academicYearStartJY = $jy;
        } else {
            // از فروردین تا شهریور → سال تحصیلی جاری از ۱ مهر سال قبل شروع شده
            $academicYearStartJY = $jy - 1;
        }

        // تاریخ ۱ مهر سال تحصیلی به میلادی
        $mehr1G = jalali_to_gregorian($academicYearStartJY, 7, 1); // [Y, M, D]
        $mehr1GDate = new DateTime(
            sprintf('%04d-%02d-%02d', $mehr1G[0], $mehr1G[1], $mehr1G[2]),
            $tz
        );

        // سن در اول مهر همین سال تحصیلی
        $ageOnMehr = $birth->diff($mehr1GDate)->y;

        // پایه تقریبی (اول دبستان ≈ ۷ سالگی)
        $grade = $ageOnMehr - 5;

        // تولد به جلالی برای تشخیص نیمهٔ سال
        list($by, $bm, $bd) = gregorian_to_jalali(
            (int)$birth->format('Y'),
            (int)$birth->format('m'),
            (int)$birth->format('d')
        );

        // 🔥 قاعده‌ی جدید:
        // نیمه دوم سال (۷ تا ۱۲): نرمال
        // نیمه اول سال (۱ تا ۶): یک پایه بالاتر
        if ($bm >= 1 && $bm <= 6) {
            $grade++;    // نیمه اول → پایه بالاتر
        }

        // محدود کردن بین ۱ تا ۱۲
        if ($grade < 1)  $grade = 1;
        if ($grade > 12) $grade = 12;

        $approxGradeLabel = 'پایه ' . $grade;

    } catch (Exception $e) {
        $approxGradeLabel = null; // در صورت خطا چیزی نمایش نده
    }
}
function getAcademicYearBaseJalali(int $jy, int $jm, int $jd): int
{
    // From 1 Tir (month 4, day 1) and later -> academic year starts this year
    if ($jm > 4 || ($jm === 4 && $jd >= 1)) {
        return $jy;
    }
    // Before 1 Tir -> still in previous academic year
    return $jy - 1;
}

date_default_timezone_set('Asia/Tehran');



$username = $_SESSION['admin']['user'] ?? null;

$suport = getsuport_user($user->codemeli);
date_default_timezone_set("Iran");
$datenow = date("Y-m-d");
$datenow = explode('-', $datenow);
$datenow = gregorian_to_jalali($datenow[0], $datenow[1], $datenow[2]);
$datenow = implode('/', $datenow);
//Start Calling
if (isset($_GET['calls']) and $_GET['calls'] == 'true') {

    $semat = 'دانش آموز';
    $tel = $user->tel1;
    $name = $semat . ' ' . $user->fname . ' ' . $user->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
if (isset($_GET['callp']) and $_GET['callp'] == 'true') {

    $semat = 'پدر ';
    $tel = $user->tel3;
    $name = $semat . ' ' . $user->fname . ' ' . $user->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
if (isset($_GET['callm']) and $_GET['callm'] == 'true') {

    $semat = 'مادر';
    $tel = $user->tel2;
    $name = $semat . ' ' . $user->fname . ' ' . $user->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}

if (isset($_GET['callh']) and $_GET['callh'] == 'true') {

    $semat = 'خانه';
    $tel_home = $user->tel_home;
    $name = $semat . ' ' . $user->fname . ' ' . $user->lname;

    if (!empty($tel_home)) {
        Call($tel_home, $_SESSION['admin']['dakheli'], $name);
        $_SESSION['call'] = array("name" => $name);
    } else {
        $_SESSION['call_message'] = 'این دانش‌آموز شماره تلفن ثابت ندارد.';
    }

    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
//end Calling
if (isset($_GET['changepublic'])) {
    if ($suport) {
        $userchange = $_GET['changepublic'];
        $update = 'UPDATE suport_user SET typesupport=? WHERE username=? ';
        $query1 = $conn->prepare($update);
        $query1->execute([0, $userchange]);
        header('location: suporting.php?Id=' . $_GET['changepublic'] . ' ');
        die();
    } else {
        header('location: suporting.php?Id=' . $_GET['changepublic'] . ' ');
        die();
    }

}
if (isset($_GET['changevip'])) {
    if ($suport) {
        $userchange = $_GET['changevip'];
        $update = 'UPDATE suport_user SET typesupport=? WHERE username=? ';
        $query1 = $conn->prepare($update);
        $query1->execute([1, $userchange]);
        header('location: suporting.php?Id=' . $_GET['changevip'] . ' ');
        die();
    } else {
        header('location: suporting.php?Id=' . $_GET['changevip'] . ' ');
        die();
    }

}
if (isset($_POST['addposhtibani'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        if (!$suport) {
            $last = $_POST['lastdate'];
            $last = explode('/', $last);
            $last = jalali_to_gregorian($last[0], $last[1], $last[2]);
            $last = implode('-', $last);
            $after = $_POST['afterdate'];
            $after = explode('/', $after);
            $after = jalali_to_gregorian($after[0], $after[1], $after[2]);
            $after = implode('-', $after);
            $sql = 'INSERT INTO suport_user (username,lastdate,afterdate) VALUES (?,?,?)';
            $query2 = $conn->prepare($sql);
            $query2->execute([$user->codemeli, $last, $after]);
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $type = $_POST['noposhtibani'];
            $status = $_POST['status'];
            $ip = GetIp();
            $os = GetOs();
            $detail = $_POST['detail'];
            $poshtiban = $_SESSION['admin']['user'];
            $sql = 'INSERT INTO suport_report(username,`type`,status,datesupport,detail,poshtiban,ip,os,datenow,timenow) values (?,?,?,?,?,?,?,?,?,?)';
            $qr = $conn->prepare($sql);
            $qr->execute([$user->codemeli, $type, $status, $last, $detail, $poshtiban, $ip, $os, $datenow, $timenow]);
            if (isset($_POST['sendtime']) and $_POST['sendtime'] == 'on') {
                $sender_type = 'admin';
                $sender_username = $_SESSION['admin']['user'];
                $sender_ip = GetIp();
                $receiver_type = 'student';
                $receiver_username = $_GET['Id'];
                $after = $_POST['afterdate'];
                $message_text = "تاریخ پشتیبانی بعدی شما به $after انتقال یافت";
                $status = 'sent';
                date_default_timezone_set('Asia/Tehran');
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');
                $sql = "INSERT INTO suport_message(sender_type, sender_username, sender_ip, receiver_type, receiver_username, message_text, status, message_date, message_time) VALUES (?,?,?,?,?,?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$sender_type, $sender_username, $sender_ip, $receiver_type, $receiver_username, $message_text, $status, $datenow, $timenow]);
            }
            header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
            die();
        } else {
            $last = $_POST['lastdate'];
            $last = explode('/', $last);
            $last = jalali_to_gregorian($last[0], $last[1], $last[2]);
            $last = implode('-', $last);
            $after = $_POST['afterdate'];
            $after = explode('/', $after);
            $after = jalali_to_gregorian($after[0], $after[1], $after[2]);
            $after = implode('-', $after);
            $sql = 'UPDATE suport_user SET lastdate=?,afterdate=? WHERE username=? ';
            $query2 = $conn->prepare($sql);
            $query2->execute([$last, $after, $user->codemeli]);
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $type = $_POST['noposhtibani'];
            $status = $_POST['status'];
            $ip = GetIp();
            $os = GetOs();
            $detail = $_POST['detail'];
            $poshtiban = $_SESSION['admin']['user'];
            $sql = 'INSERT INTO suport_report(username,`type`,status,datesupport,detail,poshtiban,ip,os,datenow,timenow) values (?,?,?,?,?,?,?,?,?,?)';
            $qr = $conn->prepare($sql);
            $qr->execute([$user->codemeli, $type, $status, $last, $detail, $poshtiban, $ip, $os, $datenow, $timenow]);
            if (isset($_POST['sendtime']) and $_POST['sendtime'] == 'on') {
                $sender_type = 'admin';
                $sender_username = $_SESSION['admin']['user'];
                $sender_ip = GetIp();
                $receiver_type = 'student';
                $receiver_username = $_GET['Id'];
                $after = $_POST['afterdate'];
                $message_text = "تاریخ پشتیبانی بعدی شما به $after انتقال یافت";
                $status = 'sent';
                date_default_timezone_set('Asia/Tehran');
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');
                $sql = "INSERT INTO suport_message(sender_type, sender_username, sender_ip, receiver_type, receiver_username, message_text, status, message_date, message_time) VALUES (?,?,?,?,?,?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$sender_type, $sender_username, $sender_ip, $receiver_type, $receiver_username, $message_text, $status, $datenow, $timenow]);
            }
            header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
            die();

        }


    } else {
        echo '<script>window.alert("خطای غیر منتظره رخ داد ") </script>';
    }
}
if (isset($_GET['delsupport'])) {
    $delid = intval($_GET['delsupport']);
    $sql = 'DELETE FROM suport_report WHERE id=? ';
    $del = $conn->prepare($sql);
    $del->execute([$delid]);
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
if (isset($_GET['delhistorysupport']) and $_GET['delhistorysupport'] == 'true') {
    $sql = 'DELETE FROM suport_report WHERE username=? ';
    $del = $conn->prepare($sql);
    $del->execute([$_GET['Id']]);
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
if (isset($_POST['addaboutsuporter'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        $uping = 'UPDATE dbpersonal SET aboutsuporter=? WHERE codemeli=?';
        $quaping = $conn->prepare($uping);
        $quaping->execute([$_POST['about_suporter'], $_GET['Id']]);
        header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
        die();
    } else {
        echo '<script>window.alert("خطای غیر منتظره رخ داد ") </script>';
    }
}
$sql = 'SELECT * FROM suport_message WHERE sender_username = ? AND status = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$_GET['Id'], 'sent']);
if ($stmt->rowCount() > 0) {
    $update = 'UPDATE suport_message SET status = ? WHERE sender_username = ?';
    $stmt = $conn->prepare($update);
    $stmt->execute(['seen', $_GET['Id']]);
}
if (isset($_POST['sub'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $message_text = $_POST['txt'];
        $sender_username = $_SESSION['admin']['user'];
        $sender_type = 'admin';
        $ip = GetIp();
        $receiver_type = 'student';
        $receiver_username = $_GET['Id'];
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
                                $sql = 'UPDATE suport_message SET sender_username = ?, message_text = ?, file_path = ?, edit_message_date = ?, edit_message_time = ? WHERE id = ?';
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$_SESSION['admin']['user'], $message_text, $upload_path_for_db, $datenow, $timenow, $_GET['edit']]);
                                header('Location: suporting.php?Id=' . $_GET['Id']);
                                die();
                            } else {
                                $_SESSION['error_message'] = 'امکان ویرایش پیام بعد از یک ساعت وجود ندارد.';
                                header('Location: suporting.php?Id=' . $_GET['Id']);
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
                    $sql = 'UPDATE suport_message SET sender_username = ?, message_text = ?, edit_message_date = ?, edit_message_time = ? WHERE id = ?';
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$_SESSION['admin']['user'], $message_text, $datenow, $timenow, $_GET['edit']]);
                    header('Location: suporting.php?Id=' . $_GET['Id']);
                    die();
                } else {
                    $_SESSION['error_message'] = 'امکان ویرایش پیام بعد از یک ساعت وجود ندارد.';
                    header('Location: suporting.php?Id=' . $_GET['Id']);
                    die();
                }
            } else {
                $sql = 'INSERT INTO suport_message (sender_type,sender_username,sender_ip,receiver_type,receiver_username,message_text,status,message_date,message_time) VALUES (?,?,?,?,?,?,?,?,?)';
                $stmt = $conn->prepare($sql);
                $stmt->execute([$sender_type, $sender_username, $ip, $receiver_type, $receiver_username, $message_text, $status, $datenow, $timenow]);
            }
        }
    }
}

if (isset($_GET['Id']) and isset($_GET['send'])) {
    $sql = "SELECT * FROM suport_message_constant WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['send']]);
    $message = $stmt->fetch(PDO::FETCH_OBJ);
    if (isset($_SESSION['token'])) {
        $sender_username = $_SESSION['admin']['user'];
        $sender_type = 'admin';
        $ip = GetIp();
        $receiver_type = 'student';
        $receiver_username = $_GET['Id'];
        $status = 'sent';
        date_default_timezone_set('Asia/Tehran');
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $sql = 'INSERT INTO suport_message (sender_type,sender_username,sender_ip,receiver_type,receiver_username,message_text,status,message_date,message_time) VALUES (?,?,?,?,?,?,?,?,?)';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$sender_type, $sender_username, $ip, $receiver_type, $receiver_username, $message->content, $status, $datenow, $timenow]);
    }
}

if (isset($_GET['delm'])) {
    $sql = 'SELECT * FROM suport_message WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['delm']]);
    $message = $stmt->fetch(PDO::FETCH_OBJ);
    $datetime = $message->message_date . ' ' . $message->message_time;
    if (time() - strtotime($datetime) <= 3600 or Permision('permision_manage_chat')) {
        if ($message && !empty($message->file_path)) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sql = 'DELETE FROM suport_message WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['delm']]);
        header('Location: suporting.php?Id=' . $_GET['Id']);
        die();
    } else {
        $_SESSION['error_message'] = 'امکان حذف پیام بعد از یک ساعت وجود ندارد.';
        header('Location: suporting.php?Id=' . $_GET['Id']);
        die();
    }
}

if (isset($_GET['delallmessage'])) {
    $select = "SELECT * FROM suport_message WHERE sender_username = ? OR receiver_username = ?";
    $query = $conn->prepare($select);
    $query->execute([$_GET['Id'], $_GET['Id']]);
    $messages = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($messages as $message) {
            if ($message and !empty($message->file_path)) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
    }

    $sql = "DELETE FROM suport_message WHERE sender_username = ? OR receiver_username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['Id'], $_GET['Id']]);
    header('Location: suporting.php?Id=' . $_GET['Id']);
    die();
}
//here
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course-status'])) {

    // 2. Sanitize and validate inputs
    $student_id = htmlspecialchars(strip_tags(trim($_POST['student_id'] ?? '')), ENT_QUOTES, 'UTF-8');
    $exam_type = htmlspecialchars(strip_tags(trim($_POST['exam_type'] ?? '')), ENT_QUOTES, 'UTF-8');
    $registered = htmlspecialchars(strip_tags(trim($_POST['registered'] ?? '')), ENT_QUOTES, 'UTF-8');
    $attended = htmlspecialchars(strip_tags(trim($_POST['attended'] ?? '')), ENT_QUOTES, 'UTF-8');
    $not_registered_reason = htmlspecialchars(strip_tags(trim($_POST['not_registered_reason'] ?? '')), ENT_QUOTES, 'UTF-8');
    $result = htmlspecialchars(strip_tags(trim($_POST['result'] ?? '')), ENT_QUOTES, 'UTF-8');

    // 3. Validation

    $errors = [];

    if (!$student_id)
        $errors[] = "شناسه دانش آموز الزامی است";
    if (!$exam_type)
        $errors[] = "نوع آزمون الزامی است";
    if (!$registered)
        $errors[] = "وضعیت ثبت نام الزامی است";

    if ($registered === 'ثبت نام کرده') {
        // Only validate attended and result if registered
        if (!$attended)
            $errors[] = "وضعیت آزمون الزامی است";
        if (!$result)
            $errors[] = "نتیجه آزمون الزامی است";
        $not_registered_reason = NULL; // no reason if they registered
    } else {
        // Only validate reason if not registered
        if (!$not_registered_reason)
            $errors[] = "دلیل ثبت نام نکردن الزامی است";
        $attended = NULL; // no exam if not registered
        $result = NULL; // no result if not registered
    }


    // 4. Insert into DB using prepared statements
    $sql = "INSERT INTO course_status 
            (student_id, exam_type, registered, attended,not_registered_reason, result) 
            VALUES 
            (:student_id, :exam_type, :registered, :attended,:not_registered_reason, :result)";

    $stmt = $conn->prepare($sql);

    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':exam_type', $exam_type);
    $stmt->bindValue(':registered', $registered);
    $stmt->bindValue(':attended', $attended);
    $stmt->bindValue(':not_registered_reason', $not_registered_reason);
    $stmt->bindValue(':result', $result);

    if ($stmt->execute()) {
        echo "<p style='color:green;'>اطلاعات با موفقیت ثبت شد.</p>";
    } else {
        echo "<p style='color:red;'>خطا در ثبت اطلاعات!</p>";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student-status'])) {

    // 2. Sanitize and validate inputs
    $student_id = htmlspecialchars(strip_tags(trim($_POST['student_id'] ?? '')), ENT_QUOTES, 'UTF-8');
    $avg_first_semester = htmlspecialchars(strip_tags(trim($_POST['avg_first_semester'] ?? '')), ENT_QUOTES, 'UTF-8');
    $avg_second_semester = htmlspecialchars(strip_tags(trim($_POST['avg_second_semester'] ?? '')), ENT_QUOTES, 'UTF-8');
    $student_comment = htmlspecialchars(strip_tags(trim($_POST['student_comment'] ?? '')), ENT_QUOTES, 'UTF-8');
    $supporter_comment = htmlspecialchars(strip_tags(trim($_POST['supporter_comment'] ?? '')), ENT_QUOTES, 'UTF-8');

    // 3. Validation
    $errors = [];
    if (!$student_id)
        $errors[] = "شناسه دانش آموز الزامی است";

    if (empty($avg_first_semester) || mb_strlen($avg_first_semester) > 50) {
        $errors[] = "میانگین نیمسال اول نباید خالی باشد و باید حداکثر ۵۰ کاراکتر باشد";
    }

    if (empty($avg_second_semester) || mb_strlen($avg_second_semester) > 50) {
        $errors[] = "میانگین نیمسال دوم نباید خالی باشد و باید حداکثر ۵۰ کاراکتر باشد";
    }

    if (count($errors) > 0) {
        foreach ($errors as $error) {
            echo "<p style='color:red;'>$error</p>";
        }
        exit;
    }


    // 4. Insert into DB using prepared statements
    $sql = "INSERT INTO student_status 
            (student_id, avg_first_semester, avg_second_semester, student_comment, supporter_comment) 
            VALUES 
            (:student_id, :avg_first_semester, :avg_second_semester, :student_comment, :supporter_comment)";

    $stmt = $conn->prepare($sql);

    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':avg_first_semester', $avg_first_semester);
    $stmt->bindValue(':avg_second_semester', $avg_second_semester);
    $stmt->bindValue(':student_comment', $student_comment);
    $stmt->bindValue(':supporter_comment', $supporter_comment);

    if ($stmt->execute()) {
        echo "<p style='color:green;'>اطلاعات با موفقیت ثبت شد.</p>";
    } else {
        echo "<p style='color:red;'>خطا در ثبت اطلاعات!</p>";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}


$studentCourseStatusRows = [];

if (isset($_GET['Id'])) {
    $studentId = trim($_GET['Id']);
    $sql = "SELECT * FROM course_status WHERE student_id = :student_id ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':student_id', $studentId, PDO::PARAM_STR);
    $stmt->execute();
    $studentCourseStatusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $studentCourseStatusRows = [];
}

$studentStatusRows = [];

if (isset($_GET['Id'])) {
    $studentId = trim($_GET['Id']);
    $sql = "SELECT * FROM student_status WHERE student_id = :student_id ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':student_id', $studentId, PDO::PARAM_STR);
    $stmt->execute();
    $studentStatusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $studentStatusRows = [];
}

// ---------- Filter both status lists to ONLY current academic year ----------

$nowTs      = time();
$currentJy  = (int) jdate('Y', $nowTs, '', '', 'en');
$currentJm  = (int) jdate('m', $nowTs, '', '', 'en');
$currentJd  = (int) jdate('d', $nowTs, '', '', 'en');

// Base year of the *current* academic year (e.g. 1403 for 1403-1404)
$currentAcademicBase = getAcademicYearBaseJalali($currentJy, $currentJm, $currentJd);

// Helper to keep only rows whose created_at is inside the current academic year
$filterByCurrentAcademicYear = function (array $rows) use ($currentAcademicBase) {
    $filtered = [];

    foreach ($rows as $row) {
        if (empty($row['created_at'])) {
            // No date => treat as old (only visible in history page)
            continue;
        }

        $ts = strtotime($row['created_at']);
        if ($ts <= 0) {
            continue;
        }

        $jy = (int) jdate('Y', $ts, '', '', 'en');
        $jm = (int) jdate('m', $ts, '', '', 'en');
        $jd = (int) jdate('d', $ts, '', '', 'en');

        $rowBase = getAcademicYearBaseJalali($jy, $jm, $jd);

        if ($rowBase === $currentAcademicBase) {
            $filtered[] = $row;
        }
    }

    return $filtered;
};

// Apply filter to both tables used in the “پایان سال” card
$studentCourseStatusRows = $filterByCurrentAcademicYear($studentCourseStatusRows);
$studentStatusRows       = $filterByCurrentAcademicYear($studentStatusRows);

// ---------------------------------------------------------------------------



if (isset($_POST['delete_exam_status_id'])) {
    $idToDelete = (int) $_POST['delete_exam_status_id'];

    $sqlDel = "DELETE FROM course_status WHERE id = ?";
    $stmtDel = $conn->prepare($sqlDel);
    $stmtDel->execute([$idToDelete]);

    // Redirect back to the same page with Id parameter
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?Id=' . urlencode($_GET['Id']));
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student_status'])) {
    $deleteId = htmlspecialchars(strip_tags(trim($_POST['delete_id'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($deleteId) {
        $stmt = $conn->prepare("DELETE FROM student_status WHERE id = :id");
        $stmt->bindValue(':id', $deleteId, PDO::PARAM_INT);
        if ($stmt->execute()) {
            echo "<p style='color:green;'>رکورد با موفقیت حذف شد.</p>";
        } else {
            echo "<p style='color:red;'>خطا در حذف رکورد!</p>";
        }
    }
    header("Location: " . $_SERVER['REQUEST_URI']); // Refresh the page
    exit;
}


if (!function_exists('secToHMS')) {
    function secToHMS(int $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

// safe defaults
if (!isset($dateno)) {
    if (function_exists('gregorian_to_jalali')) {
        list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
        $dateno = "{$jy}/{$jm}/{$jd}";
    } else {
        $dateno = date('Y/m/d');
    }
}

$studentId = isset($_GET['Id']) ? $_GET['Id'] : (isset($user->codemeli) ? $user->codemeli : null);
$talkResult = null;
$errorMsg = '';
$todayJalali = gregorian_to_jalali(date('Y'), date('m'), date('d'));
$todayJalaliStr = implode('/', $todayJalali);

/*************************************************
 * talktime_logic_inline.php  (no HTML echo)
 * Requires:
 *   - $conn (PDO), jdf functions: jdate(), jalali_to_gregorian()
 * Produces:
 *   - $numbers, $ytdSeconds, $ytdHMS, $ytdStartJ, $ytdEndJ
 *   - $talkResult (or null), $errorMsg (or '')
 *************************************************/

// ---- Simotel API config (override if you keep them elsewhere) ----
$apiUrl = $apiUrl ?? 'http://5.202.253.246:9880//api/v4/reports/quick/search';
$apiHeaders = $apiHeaders ?? [
    'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
    'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
    'Content-Type: application/json'
];

// ---------- helpers ----------
if (!function_exists('secToHMS')) {
    function secToHMS(int $sec): string {
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
$personal = retPerson($user->codemeli); // you already use $user->codemeli in the page
$numbers  = array_values(array_unique(array_filter([
    $personal->tel1 ?? '',
    $personal->tel2 ?? '',
    $personal->tel3 ?? '',
    $personal->tel_home ?? '',
], function($v){ return trim($v) !== ''; })));

$hasNumbers = !empty($numbers);

// Your Simotel config (same as before)
$apiUrl = 'http://5.202.253.246:9880//api/v4/reports/quick/search';
$apiHeaders = [
    'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
    'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
    'Content-Type: application/json'
];

// ---------------------------------------------
// 1) CURRENT JALALI YEAR RANGE (YTD) in Gregorian
// ---------------------------------------------
list($jy,$jm,$jd) = gregorian_to_jalali((int)date('Y'), (int)date('m'), (int)date('d'));

// Gregorian start of this Jalali year
$ytdStartGDate = jalali_to_gregorian($jy, 1, 1);
$ytdStartG     = sprintf('%04d-%02d-%02d 00:00:00', $ytdStartGDate[0], $ytdStartGDate[1], $ytdStartGDate[2]);

// Gregorian end: one second before next Jalali year
$nextJy        = $jy + 1;
$nextStartGArr = jalali_to_gregorian($nextJy, 1, 1);
$nextStartG    = sprintf('%04d-%02d-%02d 00:00:00', $nextStartGArr[0], $nextStartGArr[1], $nextStartGArr[2]);
$ytdEndG       = date('Y-m-d H:i:s', strtotime($nextStartG) - 1);

// For display (Jalali)
$ytdStartJ = sprintf('%04d/%02d/%02d 00:00', $jy, 1, 1);
list($gyEnd,$gmEnd,$gdEnd,$H,$i,$s) = sscanf($ytdEndG, '%d-%d-%d %d:%d:%d');
list($jyEnd,$jmEnd,$jdEnd) = gregorian_to_jalali($gyEnd,$gmEnd,$gdEnd);
$ytdEndJ   = sprintf('%04d/%02d/%02d %02d:%02d', $jyEnd, $jmEnd, $jdEnd, $H, $i);

// ---------------------------------------------
// 2) Helper: fetch talk seconds for a list of numbers and range
//    - OUTGOING (dst match) -> only if disposition=ANSWERED => use duration
//    - INCOMING (src match) -> only if disposition=ANSWERED => use billsec
// ---------------------------------------------
function fetchTalkSecondsForRange(array $numbers, string $from, string $to, string $apiUrl, array $apiHeaders): int {
    if (empty($numbers)) return 0;

    $mh  = curl_multi_init();
    $chs = [];

    foreach ($numbers as $num) {
        // OUTGOING
        $postOut = [
            "conditions" => ["dst" => (string)$num, "type" => "outgoing"],
            "date_range" => ["from" => $from, "to" => $to],
            "pagination" => ["start" => 0, "count" => 1000, "sorting" => (object)[]],
            "alike"      => "true"
        ];
        $ch1 = curl_init();
        curl_setopt_array($ch1, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postOut),
            CURLOPT_HTTPHEADER => $apiHeaders,
            CURLOPT_TIMEOUT => 20,
        ]);
        curl_multi_add_handle($mh, $ch1);
        $chs[] = ['h'=>$ch1, 'dir'=>'out'];

        // INCOMING
        $postIn = [
            "conditions" => ["src" => (string)$num, "type" => "incoming"],
            "date_range" => ["from" => $from, "to" => $to],
            "pagination" => ["start" => 0, "count" => 1000, "sorting" => (object)[]],
            "alike"      => "true"
        ];
        $ch2 = curl_init();
        curl_setopt_array($ch2, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postIn),
            CURLOPT_HTTPHEADER => $apiHeaders,
            CURLOPT_TIMEOUT => 20,
        ]);
        curl_multi_add_handle($mh, $ch2);
        $chs[] = ['h'=>$ch2, 'dir'=>'in'];
    }

    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running > 0 && $status == CURLM_OK);

    $total = 0;
    foreach ($chs as $entry) {
        $ch  = $entry['h'];
        $dir = $entry['dir'];
        $resp = curl_multi_getcontent($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if ($http >= 200 && $http < 300 && $resp) {
            $data = json_decode($resp, true);
            $rows = $data['data']['data'] ?? ($data['data'] ?? []);
            foreach ($rows as $r) {
                $disp = strtoupper(trim((string)($r['disposition'] ?? '')));
                if ($disp !== 'ANSWERED') continue;

                if ($dir === 'out') {
                    // OUTGOING answered -> use duration
                    $sec = (int)($r['duration'] ?? 0);
                } else {
                    // INCOMING answered -> use billsec
                    $sec = (int)($r['billsec'] ?? 0);
                }
                $total += max(0, $sec);
            }
        }
    }
    curl_multi_close($mh);
    return $total;
}

// ---------------------------------------------
// 3) Build the YTD totals (for the banner at page load)
// ---------------------------------------------
$ytdSeconds = 0;
$ytdHMS     = '00:00:00';
if ($hasNumbers) {
    $ytdSeconds = fetchTalkSecondsForRange($numbers, $ytdStartG, $ytdEndG, $apiUrl, $apiHeaders);
    // Your existing HMS formatter (assumed available)
    if (!function_exists('secToHMS')) {
        function secToHMS($sec){
            $sec = (int)$sec;
            $h = floor($sec/3600);
            $m = floor(($sec%3600)/60);
            $s = $sec%60;
            return sprintf('%02d:%02d:%02d',$h,$m,$s);
        }
    }
    $ytdHMS = secToHMS($ytdSeconds);
}

// ---------------------------------------------
// 4) Handle the “custom range” form (your existing code, unchanged behavior)
//    - Accept Jalali input, convert to Gregorian, call the API, sum answered only
// ---------------------------------------------
$errorMsg   = '';
$talkResult = null;

if (!empty($_POST['calc_student_talktime'])) {
    $startDateInput = $_POST['startdate'] ?? '';
    $endDateInput   = $_POST['enddate'] ?? '';

    if ($startDateInput && $endDateInput) {
        // Convert J -> G
        list($sy,$sm,$sd) = jalali_to_gregorian(...explode('/', $startDateInput));
        list($ey,$em,$ed) = jalali_to_gregorian(...explode('/',  $endDateInput));
        $startDate = sprintf('%04d-%02d-%02d 00:00:00', $sy, $sm, $sd);
        $endDate   = sprintf('%04d-%02d-%02d 23:59:59', $ey, $em, $ed);

        if ($hasNumbers) {
            $sec = fetchTalkSecondsForRange($numbers, $startDate, $endDate, $apiUrl, $apiHeaders);
            $talkResult = [
                'seconds' => $sec,
                'hms'     => secToHMS($sec),
                'start'   => $startDateInput, // keep Jalali in UI
                'end'     => $endDateInput,
            ];
        } else {
            $errorMsg = 'شماره تلفن معتبر برای این دانش آموز موجود نیست.';
        }
    } else {
        $errorMsg = 'لطفاً هر دو تاریخ شروع و پایان را وارد کنید.';
    }
}


// Fetch payments with overdue status

try {
    $permStmt = $conn->prepare("SELECT installment_permission FROM dbadmin WHERE username = :username LIMIT 1");
    $permStmt->execute([':username' => $username]);
    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
    $canViewAll = ($permRow && (int) $permRow['installment_permission'] === 1);
} catch (PDOException $e) {
    error_log("Permission check error: " . $e->getMessage());
    $canViewAll = false;
}

$overduePayments = [];
$hasOverdue = false;
try {
    $query = "
        SELECT p.id, p.total_amount, p.paid_amount, 
               (p.total_amount - p.paid_amount) AS balance,
               p.status, p.created_at,
               CASE 
                   WHEN EXISTS (
                       SELECT 1 
                       FROM payment_installments i
                       WHERE i.payment_id = p.id
                         AND i.status = 'pending'
                         AND i.due_date < CURDATE()
                   ) THEN 1
                   ELSE 0
               END AS has_overdue
        FROM payments p
        WHERE p.codemeli = :codemeli
    ";
    if (!$canViewAll) {
        $query .= " AND p.operator_username = :username";
    }
    $query .= " ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($query);
    $params = [':codemeli' => $user->codemeli];
    if (!$canViewAll) {
        $params[':username'] = $username;
    }
    $stmt->execute($params);
    $overduePayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if any payment has overdue installments
    $hasOverdue = count(array_filter($overduePayments, fn($payment) => $payment['has_overdue'] == 1)) > 0;
} catch (PDOException $e) {
    error_log("Overdue payments query error: " . $e->getMessage());
    $_SESSION['profile_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در دریافت اطلاعات پرداخت‌ها.</div>";
}
//active or not
$statusAlert = '';
$statusData  = [];

$codemeli = $_GET['Id'] ?? '';
$codemeli = preg_replace('/\D/', '', $codemeli); // digits only

if ($codemeli === '') {
    $statusAlert = '<div class="alert alert-danger text-center">کد ملی نامعتبر است.</div>';
} else {
    // 1) Totals and newest timestamp
    $sqlTotals = "
        SELECT
            COUNT(*)                              AS total,
            SUM(c.activityuser = 1)               AS active_count,
            SUM(c.activityuser = 0)               AS inactive_count,
            MAX(CONCAT(cc.datacreated,' ',cc.timecreated)) AS last_created_at
        FROM dbcourse c
        JOIN codtbl_course cc ON cc.codecourse = c.codecourse
        WHERE c.username = ?
    ";
    $stmt = $conn->prepare($sqlTotals);
    $stmt->execute([$codemeli]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total  = (int)($row['total'] ?? 0);
    $active = (int)($row['active_count'] ?? 0);
    $last   = $row['last_created_at'] ?? null;

    // 2) Newest course name (and its timestamp) — independent small query
    $lastCourseName = null;
    $lastCourseAt   = null;
    if ($total > 0) {
        $sqlLastCourse = "
            SELECT 
                cc.coursename,
                CONCAT(cc.datacreated,' ',cc.timecreated) AS created_at
            FROM dbcourse c
            JOIN codtbl_course cc ON cc.codecourse = c.codecourse
            WHERE c.username = ?
            ORDER BY cc.datacreated DESC, cc.timecreated DESC
            LIMIT 1
        ";
        $stmt2 = $conn->prepare($sqlLastCourse);
        $stmt2->execute([$codemeli]);
        if ($r2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $lastCourseName = $r2['coursename'] ?? null;
            $lastCourseAt   = $r2['created_at'] ?? null;
        }
        if ($lastCourseAt) $last = $lastCourseAt;
    }

    if ($total > 0 && $active === 0) {
        $msg = 'دانش‌آموز <strong>غیرفعال</strong> است. ';
        if ($last) {
            list($gDate,$gTime) = explode(' ', $last);
             list($gy,$gm,$gd) = explode('-', $gDate);
             $j = gregorian_to_jalali((int)$gy,(int)$gm,(int)$gd, '/');
             $msg .= 'آخرین زمان فعالیت این دانش‌آموز: <span dir="ltr">'.$j.'</span>';
            if ($lastCourseName) {
                $msg .= ' — نام پایه: <strong>'.$lastCourseName.'</strong>';
            }
        } else {
            $msg .= 'هیچ تاریخی برای آخرین فعالیت پیدا نشد.';
        }
        $statusAlert = '<div class="alert alert-warning text-center" role="alert" style="direction:rtl">'.$msg.'</div>';
    } else {
        if ($active > 0) {
            $statusAlert = '<div class="alert alert-success text-center" role="alert" style="direction:rtl">این دانش‌آموز <strong>فعال</strong> است.</div>';
        } else {
            $statusAlert = '<div class="alert alert-info text-center" role="alert" style="direction:rtl">هیچ پایه‌ای برای این دانش‌آموز ثبت نشده است.</div>';
        }
    }
}

include_once '../../temp.html';
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
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title>پشتیبانی دانش آموز | <?php echo __TITLE ?></title>
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
                                    <div class="col-xl-11">
                                        <div >
                                            <h6 class="main-content-label mb-1">پشتیبانی دانش آموز : <span
                                                    class="text-primary"><?php echo $user->fname . ' ' . $user->lname ?></span>
                                                | اولین ورود :
                                                <?php
                                                $firstlogin = retdblogin($user->codemeli);
                                                if ($firstlogin->firstlogin == 1) {
                                                    echo '<span class="badge badge-success-light">بله</span>';
                                                } else {
                                                    echo '<span class="badge badge-warning-light">خیــر</span>';
                                                }

                                                ?>
                                                |
                                                پایه تحصیلی و رشته: <span
                                                    class="badge badge-success-light"><?php echo $user->paye . ' - ' . $user->reshtestudent ?></span>
                                                |
                                                همکار آموزشی: <span class="badge badge-warning-light">
                                                    <?php
                                                if ($user->useradder == NULL) {
                                                    echo 'ندارد';
                                                } else {
                                                    $collague = retPerson($user->useradder);
                                                    echo $collague->fname . ' ' . $collague->lname;
                                                }

                                                ?></span>
                                                <?php if (!empty($approxGradeLabel)): ?>
                                                    پایه تحصیلی براساس سن (حدودی):
                                                    <span class="badge badge-info-light">
        <?php echo htmlspecialchars($approxGradeLabel, ENT_QUOTES, 'UTF-8'); ?>
    </span>
                                                    |
                                                <?php endif; ?>

                                                |
                                                استان: <span
                                                    class="badge badge-success-light"><?php echo $user->state ?></span>
                                                |
                                                شهر: <span
                                                    class="badge badge-success-light"><?php echo $user->city ?></span> |
                                                تاریخ تولد: <span class="badge badge-primary-light"><?php
                                                $dateno = $user->birthdate;
                                                $dateno = explode('-', $dateno);
                                                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                $dateno = implode('/', $dateno);
                                                echo $dateno;
                                                ?></span> |
                                                نوع دانش آموز:<?php
                                                $sql = "SELECT * FROM dbpersonal_type_vip INNER JOIN type_vip ON dbpersonal_type_vip.type_vip_id = type_vip.id WHERE dbpersonal_id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$user->idpersonal]);
                                                $typeVips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                if ($stmt->rowCount() > 0) {
                                                    foreach ($typeVips as $typeVip) {
                                                        echo "<span class='badge badge-success'>$typeVip->name</span>";
                                                    }
                                                } else {
                                                    echo '<span class="badge badge-success">تعریف نشده</span>';
                                                }
                                                ?> |
                                                شیفت دانش آموز: <span class="badge badge-danger"><?php
                                                if (!isset($user->shift)) {
                                                    echo 'تعریف نشده';
                                                } else {
                                                    switch ($user->shift) {
                                                        case 0:
                                                            echo 'صبح';
                                                            break;
                                                        case 1:
                                                            echo 'بعدازظهر';
                                                            break;
                                                        case 2:
                                                            echo 'گردشی';
                                                            break;
                                                    }
                                                }
                                                ?></span> |
                                                <?php if ($hasOverdue): ?>
                                                    <div class="alert alert-danger alert-overdue" role="alert">
                                                        <strong>هشدار:</strong> این دانش‌آموز دارای پرداخت‌های معوقه است!
                                                        <ul class="mb-0">
                                                            &nbsp;
                                                            <?php foreach ($overduePayments as $payment): ?>
                                                                <?php if ($payment['has_overdue']): ?>
                                                                    <li>
                                                                        پرداخت شماره: <?= htmlspecialchars($payment['id']) ?>،

                                                                        مبلغ باقی‌مانده: <?= number_format($payment['balance']) ?>
                                                                        تومان،
                                                                    </li>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>


                                            </h6>
                                            <?php

                                            if ($suport) {
                                                $last = $suport['lastdate'];
                                                $last = explode('-', $last);
                                                $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                                                $last = implode('/', $last);
                                                //
                                                $after = $suport['afterdate'];
                                                $after = explode('-', $after);
                                                $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                                                $after = implode('/', $after);
                                                $typeuser = $suport['typesupport'];
                                                if ($typeuser == 1) {
                                                    $type = 'ویــژه';
                                                } else {
                                                    $type = 'عــادی';
                                                }
                                            } else {
                                                $last = 'ندارد';
                                                $after = 'ندارد';
                                                $type = 'عــادی';
                                                $typeuser = 0;
                                            }
                                            ?>
                                            <p class="text-muted card-sub-title"> آخرین پشتیبانی :<span
                                                    class="text-info"><?php echo $last ?></span> | پشتیبانی بعدی :
                                                <span class="text-info"><?php echo $after ?></span>
                                                | نوع پشتیبانی :
                                                <span class="text-info"> <?php echo $type ?> </span>
                                                <?php
                                                $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE  codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                                $wer = $conn->prepare($swr);
                                                $wer->execute([1, 1, $_GET['Id']]);
                                                $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                                                $total = array();
                                                $factorCheck = array();
                                                if ($wer->rowCount() > 0) {
                                                    foreach ($recive as $res) {
                                                        if (!in_array($res->numfactor, $factorCheck)) {
                                                            $factor = queryFactor($res->numfactor);
                                                            $sum = $factor->wallet + $factor->factorfee;
                                                            array_push($total, $sum);
                                                            array_push($factorCheck, $res->numfactor);

                                                        }
                                                    }
                                                }
                                                $sumtoto = 0;
                                                foreach ($total as $sto) {
                                                    $sumtoto = $sto + $sumtoto;
                                                }

                                                ?>
                                                | وضعیت پرداختی :
                                                <span class="text-info"> <?php echo number_format($sumtoto) ?> </span>
                                            </p>

                                        </div>
                                    </div>



                                    <div class="col-lg-1 d-flex flex-column justify-content-center">
                                        <div class="main-img-user my-2 align-self-center">
                                            <img alt="آواتار" src="<?php echo url('assets/img/users/1.jpg') ?>">
                                        </div>
                                        <div class=" d-flex justify-content-center">
                                            <a
                                                    href="#"
                                                    class="btn btn-sm btn-outline-info"
                                                    style="width: 60px; height:30px;"
                                                    onclick="window.open(
                                                            '<?php echo url('admin/Student/student-history.php') . '?Id=' . urlencode($_GET['Id']); ?>',
                                                            'سوابق',
                                                            'width=1000,height=550,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                            ); return false;"
                                            >
                                                سوابق
                                            </a>
                                        </div>
                                                                            <div class=" d-flex justify-content-center">
                                        <a
                                                href="#"
                                                class="btn btn-sm btn-outline-info"
                                                style="width: 60px; height:30px;"
                                                onclick="window.open(
                                                        '<?php echo url('admin/Student/student_test_results.php') . '?username=' . urlencode($_GET['Id']); ?>',
                                                        'آزمون',
                                                        'width=1000,height=550,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                        ); return false;"
                                        >
                                            آزمون های شرکت کرده
                                        </a>
                                    </div>

                                    </div>
                                </div>
                                <p class="text-muted card-sub-title"> شرح حال دانش آموز :<span class="text-info"><?php
                                if ($user->aboutsuporter != NULL) {
                                    echo htmlentities($user->aboutsuporter);
                                } else {
                                    echo 'موردی ثبت نشده است، درصورت نیاز به ثبت شرح حال از پایین صفحه اقدام نمایید';
                                }
                                ?></span>
                                    <?php if (isset($_SESSION['call'])): ?>
                                <div class="alert alert-outline-success text-center" role="alert">
                                    <div class="mg-t-10">
                                        <img style="width:75px; height:75px;" src="../../assets/img/call1.gif" alt="calling">
                                        در حال برقراری ارتباط با <?php echo $_SESSION['call']['name']; ?>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                    </div>
                                </div>
                                <?php unset($_SESSION['call']); ?>

                                <?php elseif (!empty($_SESSION['call_message'])): ?>
                                    <div class="alert alert-outline-warning text-center" role="alert">
                                        <div class="mg-t-10">
                                            <?php echo $_SESSION['call_message']; ?>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                        </div>
                                    </div>
                                    <?php unset($_SESSION['call_message']); ?>

                                <?php /* Optional fallback if you prefer to detect here without session */
                                elseif (isset($_GET['callh']) && $_GET['callh'] === 'true' && isset($user) && empty($user->tel_home)): ?>
                                    <div class="alert alert-outline-warning text-center" role="alert">
                                        <div class="mg-t-10">
                                            این دانش‌آموز شماره تلفن ثابت ندارد.
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?= $statusAlert ?>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row row-sm">

                <div class="col-lg-4 scroll-height" >
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
                    } ?>


                    <!--new form-->
                    <div class="card custom-card" style="direction: rtl; text-align: right;">
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-purple">
                            فرم وضعیت آزمون دانش آموز
                        </div>
                        <div class="card-body">

                            <!-- Exam Status Form -->
                            <form method="post" action="">
                                <input type="hidden" name="student_id"
                                    value="<?php echo htmlspecialchars($_GET['Id'] ?? ''); ?>">
                                <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">

                                <div class="mb-3">
                                    <label for="registered" class="form-label" style="color: #222;">ثبت نام کرده /
                                        نکرده</label>
                                    <select class="form-select" id="registered" name="registered" required
                                        style="color: #000;">
                                        <option value="" selected disabled>انتخاب کنید</option>
                                        <option value="ثبت نام کرده">ثبت نام کرده</option>
                                        <option value="بررسی گردد">بررسی گردد</option>
                                        <option value="ثبت نام نکرده">ثبت نام نکرده</option>
                                    </select>
                                </div>



                                <div class="mb-3" id="examTypeDiv" style="display:none;">
                                    <label for="examType" class="form-label" style="color: #222;">نوع آزمون</label>
                                    <select class="form-select" id="examType" name="exam_type" style="color: #000;">
                                        <option value="" selected disabled>انتخاب کنید</option>
                                        <option value="تیز هوشان">تیز هوشان</option>
                                        <option value="نمونه دولتی">نمونه دولتی</option>
                                        <option value="کنکور سراسری">کنکور سراسری</option>
                                        <option value="موارد دیگر">موارد دیگر</option>
                                    </select>
                                </div>

                                <!-- Reason text box if not registered -->
                                <div class="mb-3" id="reasonDiv" style="display:none;">
                                    <label for="reason" class="form-label" style="color: #222;">چرا ثبت نام
                                        نکرده؟</label>
                                    <input type="text" class="form-control" id="reason" name="not_registered_reason"
                                        placeholder="علت را وارد کنید">
                                </div>

                                <div class="mb-3" id="takenExamDiv" style="display:none;">
                                    <label for="takenExam" class="form-label" style="color: #222;">آزمون داده /
                                        نداده</label>
                                    <select class="form-select" id="takenExam" name="attended" style="color: #000;">
                                        <option value="" selected disabled>انتخاب کنید</option>
                                        <option value="آزمون داده">آزمون داده</option>
                                        <option value="آزمون نداده">آزمون نداده</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="passedDiv" style="display:none;">
                                    <label for="passed" class="form-label" style="color: #222;">قبول شده / نشده</label>
                                    <select class="form-select" id="passed" name="result" style="color: #000;">
                                        <option value="" selected disabled>انتخاب کنید</option>
                                        <option value="قبول شده">قبول شده</option>
                                        <option value="قبول نشده">قبول نشده</option>
                                    </select>
                                </div>

                                <button type="submit" name="course-status" class="btn btn-primary w-100">ثبت</button>
                            </form>

                            <!-- Divider -->
                            <hr class="my-4">

                            <!-- Student Status Form -->
                            <div class="card-header p-3 tx-medium my-auto tx-white bg-purple">
                                فرم وضعیت دانش آموز
                            </div>
                            <br>
                            <form method="post" action="">
                                <input type="hidden" name="student_id"
                                    value="<?php echo htmlspecialchars($_GET['Id'] ?? ''); ?>">
                                <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">

                                <div class="mb-3">
                                    <label for="avgFirst" class="form-label" style="color: #222;">معدل ترم اول</label>
                                    <input type="text" required class="form-control" id="avgFirst"
                                        name="avg_first_semester" placeholder="میانگین نیمسال اول را وارد کنید"
                                        style="color: #000;">
                                </div>

                                <div class="mb-3">
                                    <label for="avgSecond" class="form-label" style="color: #222;">معدل ترم دوم</label>
                                    <input type="text" required class="form-control" id="avgSecond"
                                        name="avg_second_semester" placeholder="میانگین نیمسال دوم را وارد کنید"
                                        style="color: #000;">
                                </div>


                                <div class="mb-3">
                                    <label for="studentComment" class="form-label" style="color: #222;">نظر دانش
                                        آموز</label>
                                    <textarea class="form-control" id="studentComment" required name="student_comment"
                                        rows="3" placeholder="نظر دانش آموز را وارد کنید..."
                                        style="color: #000;"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="supportComment" class="form-label" style="color: #222;">نظر پشتیبان دانش
                                        آموز</label>
                                    <textarea class="form-control" id="supportComment" required name="supporter_comment"
                                        rows="3" placeholder="نظر پشتیبان را وارد کنید..."
                                        style="color: #000;"></textarea>
                                </div>

                                <button type="submit" name="student-status" class="btn btn-primary w-100">ثبت</button>
                            </form>
                        </div>
                    </div>



                    <!--     new form-->
                    <div class="card custom-card">
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-success-gradient">
                            گفتگو با دانش آموز
                        </div>
                        <form method="post" enctype="multipart/form-data">
                            <div class="main-chat-footer">
                                <nav class="nav">

                                    <a class="nav-link" data-bs-toggle="tooltip" href="#"
                                        title="یک فایل را پیوست کنید"></a>
                                    <label class="input-group-btn">

                                        <i class="fe fe-paperclip"></i> <input type="file" name="fileto"
                                            style="display: none;" multiple="">

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
                                <input class="form-control" name="token" type="hidden" value="<?php echo $hash ?>">
                                <button type="submit" name="sub" class="main-msg-send" style="display: contents"><i
                                        class="far fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                        <hr>
                        <div class="card-body">
                            <div class="main-chat-body" id="ChatBody">
                                <?php
                                $sql = 'SELECT * FROM suport_message WHERE sender_username = ? OR receiver_username = ? ORDER BY message_date DESC, message_time DESC';
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$_GET['Id'], $_GET['Id']]);
                                $messages = $stmt->fetchAll(PDO::FETCH_OBJ);
                                foreach ($messages as $message) {
                                    if ($message->sender_type == 'admin') {
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
                                            <div class="main-msg-wrapper ml-3">
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
                                                    <span><?= htmlspecialchars($message->message_text) ?></span>
                                                </div>
                                                <div>
                                                    <?php

                                                    if (!empty($message->edit_message_date)) {
                                                        [$year, $month, $day] = explode('-', $message->edit_message_date);
                                                        [$jy, $jm, $jd] = gregorian_to_jalali($year, $month, $day);
                                                        $jDateTime = $message->edit_message_time . ' | ' . $jy . '/' . $jm . '/' . $jd;
                                                    } else {
                                                        [$year, $month, $day] = explode('-', $message->message_date);
                                                        [$jy, $jm, $jd] = gregorian_to_jalali($year, $month, $day);
                                                        $jDateTime = $message->message_time . ' | ' . $jy . '/' . $jm . '/' . $jd;
                                                    }
                                                    if (!empty($message->file_path)) { ?>
                                                        <a href="<?php echo url('Support/attachments.php') . '?fileChat=' . urlencode($message->id) ?>"
                                                            title="دانلود فایل بارگزاری شده"> <i
                                                                class="fe fe-paperclip"></i></a>
                                                    <?php }
                                                    $datetime = $message->message_date . ' ' . $message->message_time;
                                                    if (Permision('permision_manage_chat')) { ?>
                                                        <a href="?Id=<?= $_GET['Id'] ?>&edit=<?= $message->id ?>"
                                                            title="ویرایش"><i class="fe fe-edit"></i></a>
                                                        <a href="?Id=<?php echo $_GET['Id'] ?>&delm=<?php echo $message->id ?>"
                                                            onclick="return confirm('آیا با حذف این پیام موافقید؟')"
                                                            title="حذف"> <i class="fe fe-trash-2"></i></a>
                                                        <?php if ($message->status == 'sent') { ?>
                                                            <i class=" text-success fa fa-check"></i>
                                                        <?php } elseif ($message->status == 'seen') { ?>
                                                            <i class=" text-success fa fa-check"></i>
                                                            <i class=" text-success fa fa-check"></i>
                                                        <?php } ?>
                                                    <?php }
                                                    if ($message->sender_type == 'admin' and !Permision('permision_manage_chat')) {
                                                        if (time() - strtotime($datetime) <= 3600) { ?>
                                                            <a href="?Id=<?= $_GET['Id'] ?>&edit=<?= $message->id ?>"
                                                                title="ویرایش"><i class="fe fe-edit"></i></a>
                                                            <a href="?Id=<?php echo $_GET['Id'] ?>&delm=<?php echo $message->id ?>"
                                                                onclick="return confirm('آیا با حذف این پیام موافقید؟')"
                                                                title="حذف"> <i class="fe fe-trash-2"></i></a>
                                                        <?php }
                                                        if ($message->status == 'sent') { ?>
                                                            <i class=" text-success fa fa-check"></i>
                                                        <?php } elseif ($message->status == 'seen') { ?>
                                                            <i class=" text-success fa fa-check"></i>
                                                            <i class=" text-success fa fa-check"></i>
                                                        <?php }
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
                        <div class="card custom-card">
                            <div class="card-header p-3 tx-medium my-auto tx-white bg-dark">
                                پیام های آماده
                            </div>
                            <div class="card-body">
                                <?php
                                $sql = "SELECT * FROM suport_message_constant";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute();
                                $constantMessages = $stmt->fetchAll(PDO::FETCH_OBJ);
                                if ($stmt->rowCount() > 0) {
                                    $row = 1;
                                    foreach ($constantMessages as $message) { ?>
                                        <div class="text-muted">پیام شماره <?= $row ?></div>
                                        <p><?= $message->content ?>
                                            <br>
                                        <div class="col-12 text-left ">
                                            <a href="?Id=<?php echo $_GET['Id'] ?>&send=<?= $message->id ?>"
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('آیا با ارسال پیام آماده به این دانش آموز موافقید؟')"><i
                                                        class="fe fe-send"></i> ارسال </a>
                                        </div>

                                        <hr>
                                        <?php
                                        $row++;
                                    }
                                }
                                ?>
                            </div>
                        </div>

                    </div>

                    <div class="col-lg-8">
                        <!--   here   -->
                        <div class="card custom-card" style="direction: rtl; text-align: right;">
                            <div class="card-header p-3 tx-medium my-auto tx-white bg-purple">
                                فرم وضعیت آزمون و شرح حال دانش آموز برای پایان سال
                            </div>
                            <div class="card-body">
                                <!-- Top table: Exam Status -->
                                <div class="col-12 mb-4">
                                    <h5 class="mb-3">وضعیت آزمون‌ها</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr class="bg-light">
                                                    <th>#</th>
                                                    <th>نوع آزمون</th>
                                                    <th>ثبت‌نام</th>
                                                    <th>آزمون</th>
                                                    <th>توضیحات</th>
                                                    <th>نتیجه</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($studentCourseStatusRows)):
                                                    $num = 1;
                                                    foreach ($studentCourseStatusRows as $row):
                                                        $exam_type = htmlspecialchars($row['exam_type'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $registered = htmlspecialchars($row['registered'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $attended = htmlspecialchars($row['attended'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $not_registered_reason = htmlspecialchars($row['not_registered_reason'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $result = htmlspecialchars($row['result'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $row_id = (int) ($row['id'] ?? 0);
                                                        ?>
                                                        <tr>
                                                            <td><?= $num ?></td>
                                                            <td><?= $exam_type ?></td>
                                                            <td><?= $registered ?></td>
                                                            <td><?= $attended ?></td>
                                                            <td><?= $not_registered_reason ?></td>
                                                            <td><?= $result ?></td>

                                                        </tr>
                                                        <?php $num++;
                                                    endforeach;
                                                else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted">هیچ رکوردی یافت نشد.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Bottom table: Semester & Comments -->
                                <div class="col-12">
                                    <h5 class="mb-3">نمرات و نظرات</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr class="bg-light">
                                                    <th>معدل ترم اول</th>
                                                    <th>معدل ترم دوم</th>
                                                    <th>نظر دانش‌آموز</th>
                                                    <th>نظر پشتیبان</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($studentStatusRows)):
                                                    foreach ($studentStatusRows as $row):
                                                        $avg1_display = (!isset($row['avg_first_semester']) || $row['avg_first_semester'] === '') ? '-' : htmlspecialchars($row['avg_first_semester'], ENT_QUOTES, 'UTF-8');
                                                        $avg2_display = (!isset($row['avg_second_semester']) || $row['avg_second_semester'] === '') ? '-' : htmlspecialchars($row['avg_second_semester'], ENT_QUOTES, 'UTF-8');
                                                        $student_comment = nl2br(htmlspecialchars($row['student_comment'] ?? '', ENT_QUOTES, 'UTF-8'));
                                                        $supporter_comment = nl2br(htmlspecialchars($row['supporter_comment'] ?? '', ENT_QUOTES, 'UTF-8'));
                                                        ?>
                                                        <tr>
                                                            <td><?= $avg1_display ?></td>
                                                            <td><?= $avg2_display ?></td>
                                                            <td><?= $student_comment ?></td>
                                                            <td><?= $supporter_comment ?></td>
                                                        </tr>
                                                        <?php
                                                    endforeach;
                                                else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">هیچ رکوردی یافت نشد.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!--to here-->
                        <div class="card custom-card">
                            <div class="card-header p-3 tx-medium my-auto tx-white bg-pink">
                                خلاصه وضعیت دانش آموز
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>#</th>
                                            <th>پایه</th>
                                            <th>کلاس</th>
                                            <th>آزمون</th>
                                            <th>فیلم</th>
                                            <th>جزوات</th>
                                            <th>محاسبه</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php

                                        $totalColor = 0;
                                        $totalRows = 0;

                                        $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE  codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                        $wer = $conn->prepare($swr);
                                        $wer->execute([1, 1, $_GET['Id']]);
                                        $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                                        if ($wer->rowCount() > 0) {
                                            $num = 1;
                                            foreach ($recive as $res) {

                                                ?>
                                                <tr>
                                                    <td class="wd-15"><?php echo $num ?></td>
                                                    <td><?php echo $res->coursename ?></td>

                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-icon btn-danger "
                                                            onclick="window.open('<?php echo url('admin/Student/log-viewattendance.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                         ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                            <i class="fe fe-check-square"></i>
                                                        </div>
                                                        <hr>
                                                        <?php

                                                        $totalColor += $res->color;
                                                        $totalRows++;

                                                        if ($res->color == 0) {
                                                            echo '  <div class="rounded-circle bg-danger" data-placement="top"
                                                         data-bs-toggle="tooltip" title="  حضور زیر 25% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div>';
                                                        }
                                                        if ($res->color == 1) {
                                                            echo ' <div class="rounded-circle bg-warning" data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title="  حضور بین 25% تا 50% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div> ';
                                                        }
                                                        if ($res->color == 2) {
                                                            echo '  <div class="rounded-circle " data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title=" حضور بین 50% تا 75% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px; background-color: #fffb02;"  ></div>';

                                                        }
                                                        if ($res->color == 3) {
                                                            echo '   <div class="rounded-circle bg-success" data-placement="top"
                                                         data-bs-toggle="tooltip" title=" حضور بالای 75% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div>';
                                                        }

                                                        ?>


                                                    </td>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-danger btn-icon"
                                                            onclick="window.open('<?php echo url('admin/Student/log-viewquiz.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                         ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                            <i class="fa fa-question"></i>
                                                        </div>
                                                        <hr>
                                                        <?php
                                                        $colorquiz = getTblQuizColor($res->codecourse, $res->username);
                                                        $totalquiz = $colorquiz[0] + $colorquiz[1];
                                                        if ($totalquiz > 0) {
                                                            $darsadquiz = $colorquiz[0] / $totalquiz;
                                                            $darsadquiz = $darsadquiz * 100;
                                                        } else {
                                                            $darsadquiz = 0;
                                                        }

                                                        if ($darsadquiz < 25) {
                                                            echo '  <div class="rounded-circle bg-danger" data-placement="top"
                                                         data-bs-toggle="tooltip" title="  حضور زیر 25% در  آزمــون"
                                                         style="width: 30px; height: 30px;"></div>';
                                                        }
                                                        if ($darsadquiz >= 25 and $darsadquiz < 50) {
                                                            echo ' <div class="rounded-circle bg-warning" data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title="  حضور بین 25% تا 50% در آزمــون "
                                                         style="width: 30px; height: 30px;"></div> ';
                                                        }
                                                        if ($darsadquiz >= 50 and $darsadquiz < 75) {
                                                            echo '  <div class="rounded-circle " data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title=" حضور بین 50% تا 75% در آزمــون"
                                                         style="width: 30px; height: 30px; background-color: #fffb02"></div>';

                                                        }
                                                        if ($darsadquiz >= 75) {
                                                            echo '   <div class="rounded-circle bg-success" data-placement="top"
                                                         data-bs-toggle="tooltip" title=" حضور بالای 75% در آزمــون"
                                                         style="width: 30px; height: 30px;"></div>';
                                                        }
                                                        ?>


                                                    </td>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-secondary btn-icon"
                                                            onclick="window.open('<?php echo url('admin/Student/log-viewFilm.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                         ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                            <i class="fe fe-video"></i>
                                                        </div>
                                                        <hr>
                                                        <?php
                                                        $logfilm = retLogViewFilm($res->codecourse, $res->username);
                                                        if ($logfilm) {
                                                            echo '  <div class="rounded-circle bg-teal" data-placement="top"
                                                 data-bs-toggle="tooltip" title="دارای گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                        } else {
                                                            echo '  <div class="rounded-circle bg-dark" data-placement="top"
                                                 data-bs-toggle="tooltip" title="فاقد گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                        }
                                                        ?>

                                                    </td>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-primary btn-icon"
                                                            onclick="window.open('<?php echo url('admin/Student/log-viewdoc.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                         ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                            <i class="fe fe-book-open"></i>
                                                        </div>
                                                        <hr>
                                                        <?php
                                                        $logdoc = retLogViewDoc($res->codecourse, $res->username);
                                                        if ($logdoc) {
                                                            echo '  <div class="rounded-circle bg-teal" data-placement="top"
                                                 data-bs-toggle="tooltip" title="دارای گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                        } else {
                                                            echo '  <div class="rounded-circle bg-dark" data-placement="top"
                                                 data-bs-toggle="tooltip" title="فاقد گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="wd-20">
                                                        <?php
                                                        switch ($res->type_process) {
                                                            case 0:
                                                                $typestatus = 'اتوماتیک';
                                                                break;
                                                            case 1:
                                                                $typestatus = 'منتظر بررسی';
                                                                break;
                                                            case 2:
                                                                $typestatus = 'دستی';
                                                                break;
                                                        }
                                                        ?>
                                                        <div class="badge badge-success"
                                                            onclick="window.open('<?php echo url('admin/panel/requestchange.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'تغییر نحوه محاسبه رنگ بندی'
                                                         ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                            <?php echo $typestatus ?></div>
                                                    </td>


                                                </tr>
                                                <?php
                                                $num++;
                                            }
                                        }
                                        ?>
                                        <tr class="bg-light">
                                            <th>#</th>
                                            <th>میانگین</th>
                                            <th><?php
                                            if ($totalRows > 0) {
                                                $avgColor = round($totalColor / $totalRows);
                                                if ($avgColor == 0)
                                                    echo "<div class='rounded-circle bg-danger' style='width:30px;height:30px;'></div>";
                                                if ($avgColor == 1)
                                                    echo "<div class='rounded-circle bg-warning' style='width:30px;height:30px;'></div>";
                                                if ($avgColor == 2)
                                                    echo "<div class='rounded-circle' style='width:30px;height:30px;background-color:#fffb02;'></div>";
                                                if ($avgColor == 3)
                                                    echo "<div class='rounded-circle bg-success' style='width:30px;height:30px;'></div>";
                                            }
                                            ?></th>
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card custom-card">
                                    <div class="card-header p-3 tx-medium my-auto tx-white bg-info">
                                        برقراری تماس و ثبت گزارش پشتیبانی
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-lg-3 mg-b-25 ">
                                                <a href="?Id=<?php echo $_GET['Id'] ?>&calls=true"
                                                    class="btn btn-block btn-outline-success"><i
                                                        class="fe fe-phone-call"></i> تماس با دانش آموز </a>
                                            </div>
                                            <div class="col-lg-3 mg-b-25 ">
                                                <a href="?Id=<?php echo $_GET['Id'] ?>&callp=true"
                                                    class="btn btn-block btn-outline-success"><i
                                                        class="fe fe-phone-call"></i> تماس با پدر </a>
                                            </div>
                                            <div class="col-lg-3 mg-b-25 ">
                                                <a href="?Id=<?php echo $_GET['Id'] ?>&callm=true"
                                                    class="btn btn-block btn-outline-success"><i
                                                        class="fe fe-phone-call"></i> تماس با مادر </a>
                                            </div>
                                            <div class="col-lg-3 mg-b-25 ">
                                                <a href="?Id=<?php echo $_GET['Id'] ?>&callh=true"
                                                   class="btn btn-block btn-outline-success"><i
                                                            class="fe fe-phone-call"></i> تماس با تلفن ثابت </a>
                                            </div>

                                        </div>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-xl-4 mb-2">
                                                    <label for="sens" class="form-label">نوع پشتیبانی<span
                                                            style="color: red">*</span>
                                                    </label>
                                                    <select class="form-control select2 " name="noposhtibani"
                                                        aria-describedby="basic-addon2" required>
                                                        <option label="اقدام لازم">
                                                        </option>
                                                        <option>تماس تلفنی</option>
                                                        <option>پشتیبانی متنی</option>


                                                    </select>
                                                </div>

                                                <input class="form-control" name="token" type="hidden"
                                                    value="<?php echo $hash ?>">
                                                <div class="col-xl-4 mb-2">
                                                    <label for="sens" class="form-label">وضعیت پشتیبانی<span
                                                            style="color: red">*</span>
                                                    </label>
                                                    <select class="form-control select2 " name="status"
                                                        aria-describedby="basic-addon2" required>
                                                        <option label="اقدام لازم">
                                                        </option>
                                                        <option> موفقیت آمیز</option>
                                                        <option>عدم پاسخگویی</option>


                                                    </select>
                                                </div>
                                                <div class="col-xl-4 mb-2">
                                                    <label for="sens" class="form-label">تاریخ پشتیبانی<span
                                                            style="color: red">*</span>
                                                    </label>
                                                    <div class="input-group mb-3">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                <i class="fe fe-calendar lh--9 op-6"></i>
                                                            </div>
                                                        </div>
                                                        <input class="form-control fc-datepicker"
                                                            placeholder="1404/02/12" value="<?php echo $datenow ?>"
                                                            required type="text" name="lastdate">
                                                    </div>
                                                </div>

                                                <div class="col-xl-12 mb-2">
                                                    <p class="mg-b-10 "> توضیحات پشتیبانی <span
                                                            style="color: red">*</span></p>
                                                    <textarea class="form-control" rows="5" name="detail"
                                                        required></textarea>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-xl-4 mb-2">
                                                    <label for="203" class="form-label">تاریخ پشتیبانی بعدی:<span
                                                            style="color: red">*</span>

                                                        <div class="input-group mg-t-10">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text">
                                                                    <i class="fe fe-calendar lh--9 op-6"></i>
                                                                </div>
                                                            </div>
                                                            <input class="form-control fc-datepicker"
                                                                placeholder="1404/02/12" required type="text"
                                                                name="afterdate">
                                                        </div>
                                                </div>
                                                <div class="col-xl-8 mg-t-30">
                                                    <label class="custom-switch">
                                                        <input type="checkbox" name="sendtime"
                                                            class="custom-switch-input" >
                                                        <span class="custom-switch-indicator"></span>
                                                        <span class="custom-switch-description">ارسال تاریخ پشتیبانی
                                                            بعدی به گفتگو دانش آموز</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="text-left mg-10">
                                                <button type="submit" class="btn btn-primary " name="addposhtibani"><i
                                                        class="fe fe-log-in"></i> ثبت گزارش پشتیبانی

                                                </button>
                                            </div>
                                        </form>


                                    </div>
                                </div>
                                <div class="card custom-card mt-3" id="student-talktime">
                                    <div class="card-header p-3 tx-medium my-auto">
                                        محاسبه زمان مکالمه دانش آموز
                                    </div>

                                    <div class="card-body" style="direction: rtl;">

                                        <!-- YTD summary (current Jalali year) -->
                                        <div class="mt-3">

                                            <?php if (!$hasNumbers): ?>
                                                <div class="alert alert-warning mb-2">برای این دانش آموز هیچ شمارهٔ معتبری ثبت نشده است.</div>
                                            <?php else: ?>
                                                <!-- YTD banner -->
                                                <div class="alert alert-secondary mb-2" style="direction:rtl">
                                                    <strong>زمان مکالمه سال جاری</strong>
                                                    <small class="text-muted">
                                                        (<?= htmlspecialchars($ytdStartJ) ?> — <?= htmlspecialchars($ytdEndJ) ?>)
                                                    </small>
                                                    :
                                                    <span class="badge bg-primary"><?= htmlspecialchars($ytdHMS) ?></span>
                                                    <small class="text-muted">(<?= number_format($ytdSeconds) ?> ثانیه)</small>
                                                </div>
                                            <?php endif; ?>

                                        </div>


                                        <!-- Custom range form (Jalali input -> Gregorian API) -->
                                        <form method="post" class="row g-3 align-items-end" id="studentTalktimeForm">
                                            <input type="hidden" name="calc_student_talktime" value="1">

                                            <div class="col-xl-3">
                                                <label class="form-label mb-1">از تاریخ</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text"><i class="fe fe-calendar"></i></div>
                                                    </div>
                                                    <input
                                                            type="text"
                                                            name="startdate"
                                                            class="form-control fc-datepicker"
                                                            placeholder="<?= $todayJalaliStr ?>"
                                                            required
                                                            value="<?= htmlspecialchars($_POST['startdate'] ?? $todayJalaliStr) ?>">
                                                </div>
                                            </div>

                                            <div class="col-xl-3">
                                                <label class="form-label mb-1">تا تاریخ</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text"><i class="fe fe-calendar"></i></div>
                                                    </div>
                                                    <input
                                                            type="text"
                                                            name="enddate"
                                                            class="form-control fc-datepicker"
                                                            placeholder="<?= $todayJalaliStr ?>"
                                                            required
                                                            value="<?= htmlspecialchars($_POST['enddate'] ?? $todayJalaliStr) ?>">
                                                </div>
                                            </div>

                                            <div class="col-xl-3 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary">محاسبه</button>
                                                <a
                                                        href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['Id']) ? '?Id=' . urlencode($_GET['Id']) : '')) ?>"
                                                        class="btn btn-outline-secondary ms-2">بازنشانی</a>
                                            </div>
                                        </form>

                                        <!-- Custom range result -->
                                        <div class="mt-3">
                                            <?php if (!empty($errorMsg)): ?>
                                                <div class="alert alert-danger mb-0"><?= $errorMsg ?></div>

                                            <?php elseif ($talkResult !== null): ?>
                                                <div class="alert alert-info mb-0">
                                                    <strong>
                                                        مجموع زمان مکالمه
                                                        (<?= htmlspecialchars($talkResult['start']) ?> — <?= htmlspecialchars($talkResult['end']) ?>):
                                                    </strong>
                                                    &nbsp;
                                                    <span class="badge bg-success"><?= htmlspecialchars($talkResult['hms']) ?></span>
                                                    &nbsp;
                                                    <small class="text-muted">
                                                        (<?= number_format((int)$talkResult['seconds']) ?> ثانیه)
                                                    </small>
                                                </div>

                                            <?php else: ?>
                                                <div class="text-muted">
                                                    برای نمایش زمان مکالمه در بازه دلخواه، تاریخ‌ها را انتخاب و «محاسبه» را فشار دهید.
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </div>

                                <div class="card custom-card">
                                    <div class="card-header p-3 tx-medium my-auto tx-white bg-black-4">
                                        سوابق گزارشات ثبت شده
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive table-hover">
                                            <table class="table   " id="example1">
                                                <thead>
                                                    <tr>

                                                        <th class="bg-light text-center wd-5"><small>#</small></th>
                                                        <th class="bg-light text-center">تاریخ پشتیبانی</th>
                                                        <th class="bg-light text-center"><small
                                                            class="ml-1">پشتیبان</small></th>
                                                        <th class="bg-light text-center">نوع</th>
                                                        <th class="bg-light text-center">وضعیت</th>
                                                        <th class="bg-light text-center">شرح</th>
                                                        <th class="bg-light text-center">حذف</th>


                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $sql = 'SELECT * FROM suport_report WHERE username=? ORDER BY id DESC ';
                                                    $qrt = $conn->prepare($sql);
                                                    $qrt->execute([$user->codemeli]);
                                                    $result = $qrt->fetchAll(PDO::FETCH_OBJ);
                                                    if ($qrt->rowCount() > 0) {
                                                        $is = 1;
                                                        foreach ($result as $report) {


                                                            ?>
                                                            <tr>
                                                                <td class="wd-15"><?php echo $is ?></td>
                                                                <td class="wd-15">
                                                                    <?php
                                                                    $after = $report->datesupport;
                                                                    $after = explode('-', $after);
                                                                    $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                                                                    $after = implode('/', $after);
                                                                    echo $after;
                                                                    ?>

                                                                </td>
                                                                <td class="wd-15"><?php echo $report->poshtiban ?></td>
                                                                <td class="wd-15"><?php echo $report->type ?></td>
                                                                <td class="wd-15"><?php echo $report->status ?></td>
                                                                <td><?php echo htmlentities($report->detail) ?></td>
                                                                <td>
                                                                    <a href="?Id=<?php echo $_GET['Id'] ?>&delsupport=<?php echo $report->id ?>"
                                                                        onclick="return confirm('آیا با حذف این سابقه پشتیبانی موافقید؟')"
                                                                        class="btn btn-sm btn-danger btn-icon">
                                                                        <i class="fe fe-x"></i></a>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                            $is++;
                                                        }
                                                    }
                                                    ?>


                                                </tbody>
                                            </table>
                                        </div>


                                    </div>
                                </div>

                                <div class="card custom-card">
                                    <div class="card-header p-3 tx-medium my-auto tx-white bg-teal">
                                        ثبت و ویرایش شرح حال دانش آموز موقت
                                    </div>
                                    <div class="card-body">
                                        <form method="post" data-parsley-validate="">
                                            <div class="col-xl-12 mb-2">
                                                <p class="mg-b-10 "> شرح حال دانش آموز از زبان پشتیبان <span
                                                            style="color: red">*</span></p>
                                                <textarea class="form-control" rows="5" name="about_suporter" required><?php if ($user->aboutsuporter != NULL) {
                                                        echo $user->aboutsuporter;
                                                    } ?></textarea>
                                            </div>
                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $hash ?>">
                                            <div class="text-left mg-10">
                                                <button type="submit" class="btn btn-primary "
                                                        name="addaboutsuporter">ثبت شرح حــال <i
                                                            class="fe fe-check-circle"></i>

                                                </button>
                                            </div>

                                        </form>


                                    </div>
                                </div>

                            </div>
                            <div class="col-lg-4">
                                <?php if (Permision('permision_manage_chat')) { ?>
                                    <div class="card custom-card">
                                        <div class="card-header p-3 tx-medium my-auto tx-white bg-warning">
                                            تنظیمات پشتیبانی دانش آموز
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            $sql = "SELECT * FROM suport_report WHERE username = ?";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->execute([$_GET['Id']]);
                                            if ($stmt->rowCount() > 0) {
                                                ?>
                                                <?php if ($typeuser == 0) { ?>
                                                    <div class="col-lg-12 mg-b-25 ">
                                                        <a href="?changevip=<?php echo $_GET['Id'] ?>&Id=<?php echo $_GET['Id'] ?>"
                                                            class="btn btn-block btn-outline-warning"><i
                                                                class="fe fe-check-square"></i>
                                                            تغییر به پشتیبانی ویژه </a>
                                                    </div>
                                                <?php } else { ?>
                                                    <div class="col-lg-12 mg-b-25 ">
                                                        <a href="?changepublic=<?php echo $_GET['Id'] ?>&Id=<?php echo $_GET['Id'] ?>"
                                                            class="btn btn-block btn-outline-warning"><i
                                                                class="fe fe-check-square"></i>
                                                            تغییر به پشتیبانی عادی </a>
                                                    </div>
                                                <?php }
                                            } ?>
                                            <hr>
                                            <div class="col-lg-12 mg-b-25 ">
                                                <a href="?Id=<?php echo $_GET['Id'] ?>&delallmessage=true"
                                                    class="btn btn-block btn-outline-danger"
                                                    onclick="return confirm('آیا با حذف تمامی سابقه گفتگو موافقید؟')"><i
                                                        class="fe fe-trash-2"></i> پاک کردن سابقه گفتگو </a>
                                            </div>
                                            <div class="col-lg-12 mg-b-25 ">
                                                <a href="?Id=<?php echo $_GET['Id'] ?>&delhistorysupport=true"
                                                    class="btn btn-block btn-outline-danger"
                                                    onclick="return confirm('آیا با حذف تمامی سابقه پشتیبانی این دانش آموز موافقید؟')"><i
                                                        class="fe fe-trash"></i> پاک کردن سابقه پشتیبانی </a>
                                            </div>


                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>


                    </div>

                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const registered = document.getElementById('registered');
                    const reasonDiv = document.getElementById('reasonDiv');
                    const examTypeDiv = document.getElementById('examTypeDiv');
                    const takenExamDiv = document.getElementById('takenExamDiv');
                    const passedDiv = document.getElementById('passedDiv');
                    const takenExam = document.getElementById('takenExam');

                    // Initial hide
                    reasonDiv.style.display = 'none';
                    examTypeDiv.style.display = 'none';
                    takenExamDiv.style.display = 'none';
                    passedDiv.style.display = 'none';

                    // On change of registration
                    registered.addEventListener('change', function () {
                        if (this.value === 'ثبت نام نکرده') {
                            reasonDiv.style.display = 'block';
                            examTypeDiv.style.display = 'block';
                            takenExamDiv.style.display = 'none';
                            passedDiv.style.display = 'none';
                        }
                        else if (this.value === 'ثبت نام کرده') {
                            reasonDiv.style.display = 'none';
                            examTypeDiv.style.display = 'block';   // ✅ show exam type here too
                            takenExamDiv.style.display = 'block';
                            passedDiv.style.display = 'none';
                        }
                        else if (this.value === 'بررسی گردد') {
                            reasonDiv.style.display = 'block';
                            examTypeDiv.style.display = 'none';
                            takenExamDiv.style.display = 'none';
                            passedDiv.style.display = 'none';
                        }
                        else {
                            reasonDiv.style.display = 'none';
                            examTypeDiv.style.display = 'none';
                            takenExamDiv.style.display = 'none';
                            passedDiv.style.display = 'none';
                        }
                    });


                    // On change of exam taken
                    takenExam.addEventListener('change', function () {
                        if (this.value === 'آزمون داده') {
                            passedDiv.style.display = 'block';
                        } else {
                            passedDiv.style.display = 'none';
                        }
                    });
                });
            </script>
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
            <?php if (!empty($shouldScrollToCard) || !empty($talkResult) || !empty($errorMsg)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var el = document.getElementById('student-talktime');
                        if (!el) return;
                        // small delay to ensure layout finished (helps in some browsers)
                        setTimeout(function () {
                            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            // optional: give focus to the first date input
                            var input = el.querySelector('input[name="startdate"]');
                            if (input) input.focus({ preventScroll: true });
                        }, 120);
                    });
                </script>
            <?php endif; ?>
            <!-- Main Footer-->
            <?php include '../../footers.php'; ?>
            <!--End Footer-->


</body>

</html>