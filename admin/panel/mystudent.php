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
$managers = ['op201', 'keyvan', 'kazemi', 'belali','pouya'];
$admin = $_SESSION['admin']['user'];
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
                                            <?php  if (in_array($admin, $managers)) {  ?>
                                                <th>پشتیبان</th>
                                            <?php } ?>
                                            <th>ورود</th>
                                            <th>نام</th>
                                            <th>پایه</th>
                                            <th>جنسیت</th>
                                            <th>وضعیت</th>
                                            <th class="align-middle" style="max-width: 220px;">دوره ها</th>
                                                                                    <th>رنگ</th>
                                            <th>اولین ورود</th>
                                            <th>آخرین پشتیبانی</th>
                                            <th>پشتیبانی بعدی</th>
                                            <th>نوع</th>
                                            <th>نوع دانش آموز</th>
                                            <th>پرداختی</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php

                                        if (!in_array($admin, $managers)) {

                                            $sql = "
SELECT
dbcourse.*,
course.coursename,
(
SELECT COUNT(*)
FROM suport_message
WHERE sender_username = dbcourse.username
AND receiver_username = dbcourse.suporter
AND status = 'sent'
) AS unseen,
CASE
WHEN dl.username IS NULL AND dp.codemeli IS NULL THEN 'نامشخص'
WHEN dl.activity = 0 THEN 'غیرفعال'
WHEN dp.suspension_mode = 1 THEN 'تعلیق'
ELSE 'فعال'
END AS user_status
FROM dbcourse
INNER JOIN codtbl_course AS course
ON dbcourse.codecourse = course.codecourse
LEFT JOIN dblogin AS dl
ON dl.username = dbcourse.username
LEFT JOIN dbpersonal AS dp
ON dp.codemeli = dbcourse.username
WHERE course.activecourse = ?
AND dbcourse.activityuser = ?
AND dbcourse.suporter = ?
ORDER BY unseen DESC, dbcourse.username ASC
";

                                            $query = $conn->prepare($sql);
                                            $query->execute([1, 1, $_SESSION['admin']['user']]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);

                                        } else {

                                            $sql = "
SELECT
dbcourse.*,
course.coursename,
(
SELECT COUNT(*)
FROM suport_message
WHERE sender_username = dbcourse.username
AND receiver_username = ?
AND status = 'sent'
) AS unseen,
CASE
WHEN dl.username IS NULL AND dp.codemeli IS NULL THEN 'نامشخص'
WHEN dl.activity = 0 THEN 'غیرفعال'
WHEN dp.suspension_mode = 1 THEN 'تعلیق'
ELSE 'فعال'
END AS user_status,
COALESCE(dbcourse.suporter, 'بدون پشتیبان') AS suporter_label
FROM dbcourse
INNER JOIN codtbl_course AS course
ON dbcourse.codecourse = course.codecourse
LEFT JOIN dblogin AS dl
ON dl.username = dbcourse.username
LEFT JOIN dbpersonal AS dp
ON dp.codemeli = dbcourse.username
WHERE course.activecourse = ?
AND dbcourse.activityuser = ?
ORDER BY unseen DESC, dbcourse.username ASC
";

                                            $query = $conn->prepare($sql);
                                            $query->execute(['managers', 1, 1]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        }

                                        // NEW EXCLUSION LOGIC ADDED HERE (Untouched original query)
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
                                        $excluded_users = $exclude_stmt->fetchAll(PDO::FETCH_COLUMN);


                                        $user = array();
                                        $doreha = array();
                                        $codecourse = array();
                                        $unseen = array();
                                        $user_status = array();
                                        if (in_array($admin, $managers)) {
                                            $suporter_label = [];
                                        }

                                        if ($query->rowCount() > 0) {
                                            foreach ($result as $res) {

                                                // Check if user is in the blacklist array, if yes, skip them
                                                if (in_array($res->username, $excluded_users)) {
                                                    continue;
                                                }

                                                if (!in_array($res->username, $user)) {
                                                    array_push($user, $res->username);
                                                    $dore = getcourses($res->username);
                                                    $dore = implode(' || ', $dore);
                                                    array_push($doreha, $dore);
                                                    $coding = getcourses($res->username, 2);
                                                    $coding = implode(' @ ', $coding);
                                                    array_push($codecourse, $coding);
                                                    array_push($unseen, $res->unseen);
                                                    array_push($user_status, $res->user_status ?? '');
                                                    if (in_array($admin, $managers)) {
                                                        $suporter_label[] = $res->suporter_label ?? '';
                                                    }

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
                                                <?php  if (in_array($admin, $managers)) {  ?>
                                                    <td><?= htmlspecialchars($suporter_label[$num] ?? '') ?></td>
                                                <?php } ?>
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
                                                <td><?php echo $user_status[$num] ?? '' ?></td>

                                                <!-- سلول دوره‌ها با قابلیت برش متن و هاور -->
                                                <td class="align-middle text-right"
                                                    style="max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer;"
                                                    title="<?php echo htmlspecialchars($doreha[$num]); ?>">
                                                    <?php echo $doreha[$num]; ?>
                                                </td>
                                                                                                <td class="wd-20"><?php echo $ccc; ?></td>


                                                <td class="wd-15">
                                                    <?php
                                                    $firstlogin = retdblogin($person->codemeli);
                                                    if ($firstlogin->firstlogin == 1) {
                                                        echo 'بله';
                                                    } else {
                                                        echo 'خیــر';
                                                    }

                                                    ?>
                                                </td>
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
                                                            echo "$typeVip->name  "."  ";
                                                        }
                                                    } else {
                                                        echo 'تعریف نشده';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="wd-20">
                                                    <?php
                                                    $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE  codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                                    $wer = $conn->prepare($swr);
                                                    $wer->execute([1, 1, $person->codemeli]);
                                                    $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                                                    $total = array();
                                                    $factorCheck = array();
                                                    if ($wer->rowCount() > 0) {
                                                        foreach ($recive as $rey) {
                                                            if (!in_array($rey->numfactor, $factorCheck)) {
                                                                $factor = queryFactor($rey->numfactor);
                                                                $sum = $factor->wallet + $factor->factorfee;
                                                                array_push($total, $sum);
                                                                array_push($factorCheck, $rey->numfactor);

                                                            }
                                                        }
                                                    }
                                                    $sumtoto = 0;
                                                    foreach ($total as $sto) {
                                                        $sumtoto = $sto + $sumtoto;
                                                    }

                                                    // اصلاح باگ ساختاری اینجا اعمال شد
                                                    echo number_format($sumtoto);
                                                    ?>
                                                </td>
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