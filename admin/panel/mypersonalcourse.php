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
    <title> نفردوره های من | <?php echo __TITLE ?></title>
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
                                    <div class="col-8">
                                        <div>
                                            <h6 class="main-content-label mb-1">لیست دانش آموزان نفر دوره من </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>


                                </div>
                                <div class="table-responsive">
                                    <table class="table" id="example1">
                                        <thead>
                                        <tr class="bg-light">
                                            <th>#</th>
                                            <th>ورود</th>
                                            <th>نام</th>
                                            <th>پایه</th>
                                            <!-- استایل اضافه شده برای هدر ستون دوره -->
                                            <th class="align-middle" style="max-width: 220px;">دوره </th>
                                            <th>رنگ</th>
                                            <th>آزمون </th>
                                            <th>اولین ورود</th>
                                            <th>آخرین پشتیبانی</th>
                                            <th>پشتیبانی بعدی</th>
                                            <th>نوع</th>
                                            <th>نوع دانش آموز</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $managers = ['op201', 'keyvan', 'kazemi', 'belali'];
                                        $admin = $_SESSION['admin']['user'];
                                        if (!in_array($admin, $managers)) {
                                            $sql = "SELECT dbcourse.*, course.*, (SELECT COUNT(*) FROM suport_message WHERE sender_username = dbcourse.username AND receiver_username = dbcourse.suporter AND status = 'sent') AS unseen FROM dbcourse INNER JOIN codtbl_course AS course ON dbcourse.codecourse = course.codecourse WHERE course.activecourse = ? AND dbcourse.activityuser = ? AND dbcourse.suporter = ? ORDER BY unseen DESC, dbcourse.username ASC";
                                            $query = $conn->prepare($sql);
                                            $query->execute([1, 1, $_SESSION['admin']['user']]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        } else {
                                            $sql = "SELECT dbcourse.*, course.*, (SELECT COUNT(*) FROM suport_message WHERE sender_username = dbcourse.username AND receiver_username = ? AND status = 'sent') AS unseen FROM dbcourse INNER JOIN codtbl_course AS course ON dbcourse.codecourse = course.codecourse WHERE course.activecourse = ? AND dbcourse.activityuser = ? AND dbcourse.suporter IS NULL ORDER BY unseen DESC, dbcourse.username ASC";
                                            $query = $conn->prepare($sql);
                                            $query->execute(['managers', 1, 1]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        }

                                        // =========================================================
                                        // NEW EXCLUSION LOGIC ADDED HERE (Untouched original query)
                                        // =========================================================
                                        $exclude_sql = "
            SELECT codemeli AS username 
            FROM dbpersonal 
            WHERE suspension_mode = 1 AND codemeli IS NOT NULL
            UNION
            SELECT username 
            FROM dbcourse 
            GROUP BY username 
            HAVING SUM(COALESCE(has_operator, 0)) = 0
        ";
                                        $exclude_stmt = $conn->prepare($exclude_sql);
                                        $exclude_stmt->execute();
                                        // Fetch as a simple array of usernames
                                        $excluded_users = $exclude_stmt->fetchAll(PDO::FETCH_COLUMN);
                                        // =========================================================

                                        $user = array();
                                        if ($query->rowCount() > 0) {
                                            $gh = 0;
                                            $haazor = 0;
                                            $id=1;

                                            foreach ($result as $res) {

                                                // Check if user is in the blacklist array, if yes, skip them
                                                if (in_array($res->username, $excluded_users)) {
                                                    continue;
                                                }

                                                switch ($res->color){
                                                    case 0:
                                                        $ccc = 'قرمز';
                                                        $color = '#e86464';
                                                        break;
                                                    case 1:
                                                        $ccc = 'نارنجی';
                                                        $color = 'orange';
                                                        break;
                                                    case 2:
                                                        $ccc = 'زرد';
                                                        $color = 'yellow';
                                                        break;
                                                    case 3:
                                                        $ccc = 'سبز';
                                                        $color = '#8de360';
                                                        break;
                                                }


                                                ?>
                                                <tr style="background-color:<?php echo $color ?>">
                                                    <td class="wd-20"><?php echo $id ?></td>
                                                    <td class="wd-15">
                                                        <div class="position-relative d-inline-block">
                                                            <div class="btn btn-sm ripple btn-info btn-icon"
                                                                 onclick="window.open('suporting.php?Id=<?php echo $res->username ?>','پشتیبانی',
                                                                         'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')"
                                                                 style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                                <i class="fa fa-key"></i>
                                                            </div>

                                                            <?php if ($res->unseen > 0): ?>
                                                                <span class="badge bg-secondary position-absolute"
                                                                      style="top: -6px; right: 30px; font-size: 0.9rem; padding: 3px 7px; border-radius: 50%;">
                                <?= $res->unseen ?>
                            </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <?php
                                                    $person = retPerson($res->username);
                                                    ?>
                                                    <td><?php echo $person->fname . ' ' . $person->lname ?></td>
                                                    <td class="wd-20"><?php echo $person->paye; ?></td>

                                                    <!-- سلول دوره با قابلیت برش متن و نمایش کامل در هاور -->
                                                    <td class="align-middle text-right"
                                                        style="max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer;"
                                                        title="<?php echo htmlspecialchars($res->coursename); ?>">
                                                        <?php echo $res->coursename; ?>
                                                    </td>
                                                                                                    <td class="wd-20"><?php echo $ccc; ?></td>


                                                    <?php
                                                    $quiz=getTblQuizColor($res->codecourse,$res->username);
                                                    ?>
                                                    <td class="wd-15"><?php echo $quiz[0] ;  ?></td>
                                                    <td class="wd-15">
                                                        <?php
                                                        $firstlogin = retdblogin($res->username);
                                                        if ($firstlogin->firstlogin == 1) {
                                                            echo 'بله';
                                                        } else {
                                                            echo 'خیــر';
                                                        }

                                                        ?>
                                                    </td>


                                                    <?php
                                                    $suport = getsuport_user($res->username);
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
                                                        if ($suport['typesupport'] == 1) {
                                                            $type = '<span class="badge badge-success">ویـژه</span>';
                                                        } elseif ($suport['typesupport'] == 2) {
                                                            $type = '<span class="badge badge-primary">ویژه فعال</span>';
                                                        } else {
                                                            $type = 'عــادی';
                                                        }
                                                    } else {
                                                        $last = 'ندارد';
                                                        $after = 'ندارد';
                                                        $type = 'عادی';
                                                    }
                                                    ?>
                                                    <td><?php echo $last ?> </td>
                                                    <td><?php echo $after ?></td>
                                                    <td><?php echo $type ?></td>
                                                    <td>
                                                        <?php
                                                        $sql = "SELECT * FROM dbpersonal_type_vip INNER JOIN type_vip ON dbpersonal_type_vip.type_vip_id = type_vip.id WHERE dbpersonal_id = ?";
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$person->idpersonal]);
                                                        $typeVips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                        if ($stmt->rowCount() > 0) {
                                                            foreach ($typeVips as $typeVip) {
                                                                echo "$typeVip->name "." ";
                                                            }
                                                        } else {
                                                            echo '<span class="badge badge-success">تعریف نشده</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
                                                $id++;
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
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
