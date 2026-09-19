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
    <title> دانش آموزان من | <?php echo __TITLE ?></title>
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
                                            <h6 class="main-content-label mb-1">لیست دانش آموزان من </h6>
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
                                            <th>جنسیت</th>
                                            <th>پایه ها</th>
                                            <th>رنگ</th>
                                            <th>آخرین پشتیبانی</th>
                                            <th>پشتیبانی بعدی</th>
                                            <th>نوع</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $managers = ['op201', 'keyvan', 'kazemi', 'belali'];
                                        $admin = $_SESSION['admin']['user'];
                                        if (!in_array($admin, $managers)) {
                                            $sql = "SELECT dbcourse.*, course.coursename, (SELECT COUNT(*) FROM suport_message WHERE sender_username = dbcourse.username AND receiver_username = dbcourse.suporter AND status = 'sent') AS unseen FROM dbcourse INNER JOIN codtbl_course AS course ON dbcourse.codecourse = course.codecourse WHERE course.activecourse = ? AND dbcourse.activityuser = ? AND dbcourse.suporter = ? ORDER BY unseen DESC, dbcourse.username ASC";
                                            $query = $conn->prepare($sql);
                                            $query->execute([1, 1, $_SESSION['admin']['user']]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        } else {
                                            $sql = "SELECT dbcourse.*, course.coursename, (SELECT COUNT(*) FROM suport_message WHERE sender_username = dbcourse.username AND receiver_username = ? AND status = 'sent') AS unseen FROM dbcourse INNER JOIN codtbl_course AS course ON dbcourse.codecourse = course.codecourse WHERE course.activecourse = ? AND dbcourse.activityuser = ? AND dbcourse.suporter IS NULL ORDER BY unseen DESC, dbcourse.username ASC";
                                            $query = $conn->prepare($sql);
                                            $query->execute(['managers', 1, 1]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        }

                                        $user = array();
                                        $doreha = array();
                                        $codecourse = array();
                                        $unseen = array();

                                        if ($query->rowCount() > 0) {
                                            foreach ($result as $res) {
                                                if (!in_array($res->username, $user)) {
                                                    array_push($user, $res->username);
                                                    $dore = getcourses($res->username);
                                                    $dore = implode(' || ', $dore);
                                                    array_push($doreha, $dore);
                                                    $coding = getcourses($res->username, 2);
                                                    $coding = implode(' @ ', $coding);
                                                    array_push($codecourse, $coding);
                                                    array_push($unseen, $res->unseen);
                                                }

                                            }
                                        }
                                        for ($num = 0; $num < sizeof($user); $num++) {

                                            $codings = explode('@', $codecourse[$num]);
                                            $gh = 0;
                                            $hazor = 0;
                                            for ($ff = 0; $ff < sizeof($codings); $ff++) {
                                                $colorq = getTblattendance($codings[$ff], $user[$num]);
                                                $gh = $gh + $colorq[1];
                                                $hazor = $hazor + $colorq[0];

                                            }
                                            $jam = $gh + $hazor;
                                            if ($jam > 0) {
                                                $darsadhozor = $hazor / $jam;
                                                $darsadhozor = $darsadhozor * 100;

                                            } else {
                                                $darsadhozor = 0;
                                            }
                                            if ($darsadhozor < 25) {
                                                $ccc = 'قرمز';
                                                $color='#e86464';
                                            }
                                            if ($darsadhozor >= 25 and $darsadhozor < 50) {
                                                $ccc = 'نارنجی';
                                                $color='orange';
                                            }
                                            if ($darsadhozor >= 50 and $darsadhozor < 75) {
                                                $ccc = 'زرد';
                                                $color='yellow';
                                            }
                                            if ($darsadhozor >= 75) {
                                                $ccc = 'سبز';
                                                $color='#8de360';
                                            }


                                            ?>
                                            <tr style="background-color:<?php echo $color  ?>">
                                                <td class="wd-20"><?php echo $num + 1 ?></td>
                                                <td class="wd-15">
                                                    <div class="position-relative d-inline-block">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                            onclick="window.open('suporting.php?Id=<?php echo $user[$num] ?>','پشتیبانی',
                     'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')"
                                                            style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                            <i class="fa fa-key"></i>
                                                        </div>

                                                        <?php if ($unseen[$num] > 0): ?>
                                                            <span class="badge bg-secondary position-absolute"
                                                                style="top: -6px; right: 30px; font-size: 0.9rem; padding: 3px 7px; border-radius: 50%;">
                                                                <?= $unseen[$num] ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <?php
                                                $person = retPerson($user[$num]);
                                                ?>
                                                <td><?php echo $person->fname . ' ' . $person->lname ?></td>
                                                <td class="wd-20"><?php echo $person->paye; ?></td>
                                                <td><?php echo $person->gender ?></td>
                                                <td><?php echo $doreha[$num] ?></td>


                                                <td class="wd-20"><?php echo $ccc; ?></td>
                                                <?php
                                                $suport = getsuport_user($user[$num]);
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
                                                    $type = $suport['typesupport'];
                                                    if ($type == 1) {
                                                        $type = '<span class="badge badge-success">ویـژه </span>';
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

                                            </tr>
                                        <?php } ?>

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