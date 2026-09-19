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
$sessionUser = $_SESSION['admin']['user'];
$urlType = trim($_GET['type'] ?? '');
$isImpersonate = ($_SESSION['admin']['can_impersonate']) == 1;
$type = (int)($_GET['type'] ?? 0);
$allowedTypes = [2,1];
if (!in_array($type, $allowedTypes, true)) {
    echo 'دسترسی ندارید';
    exit;
}
$check = $conn->query("SELECT CURDATE() AS today")->fetch(PDO::FETCH_ASSOC);
$users = [];
$isadmin=0;
if ($_SESSION['admin']['adminrole'] == 53 or $_SESSION['admin']['adminrole'] == 0 or $_SESSION['admin']['adminrole'] == 52 or $_SESSION['admin']['adminrole'] == 51){
    $isadmin=1;
}
//dd($_SESSION['admin']['adminrole']);
if ($type === 2) {
    if ($isadmin==1) {
        $listSql = "
                    SELECT
                        p.codemeli,
                        CONCAT(p.fname, ' ', p.lname) AS full_name,
                        p.datecreated,
                    
                        COALESCE(dc.course_count, 0) AS course_count,
                        COALESCE(dc.course_codes, '') AS course_codes,
                        COALESCE(dc.course_names, '') AS course_names,
                    
                        (
                            SELECT sr.priority
                            FROM suport_report sr
                            WHERE sr.username = p.codemeli
                            ORDER BY sr.datesupport DESC, sr.timenow DESC
                            LIMIT 1
                        ) AS priority,
                    
                        (
                            SELECT sr.datesupport
                            FROM suport_report sr
                            WHERE sr.username = p.codemeli
                            ORDER BY sr.datesupport DESC, sr.timenow DESC
                            LIMIT 1
                        ) AS datesupport,
                    
                        p.creator AS creator_id,
                        CONCAT(c.fname, ' ', c.lname) AS creator_name
                    
                    FROM dbpersonal p
                    
                    LEFT JOIN dbpersonal c
                        ON c.codemeli = p.creator
                    
                    LEFT JOIN (
                        SELECT
                            d.username,
                            COUNT(*) AS course_count,
                            GROUP_CONCAT(DISTINCT d.codecourse ORDER BY d.codecourse SEPARATOR ',') AS course_codes,
                            GROUP_CONCAT(DISTINCT cc.coursename ORDER BY cc.coursename SEPARATOR '، ') AS course_names
                        FROM dbcourse d
                        INNER JOIN codtbl_course cc
                            ON cc.codecourse = d.codecourse
                        WHERE d.activityuser = 1
                          AND cc.activecourse = 1
                        GROUP BY d.username
                    ) dc ON dc.username = p.codemeli
                    
                    WHERE CHAR_LENGTH(p.codemeli) = 11
                      AND p.datecreated = CURDATE()
                    ORDER BY full_name;
        ";

        $stmt = $conn->prepare($listSql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);


    } else {
        $listSql = "
                SELECT
                    p.codemeli,
                    CONCAT(p.fname, ' ', p.lname) AS full_name,
                    p.datecreated,
                
                    COALESCE(dc.course_count, 0) AS course_count,
                    COALESCE(dc.course_codes, '') AS course_codes,
                    COALESCE(dc.course_names, '') AS course_names,
                
                    (
                        SELECT sr.priority
                        FROM suport_report sr
                        WHERE sr.username = p.codemeli
                        ORDER BY sr.datesupport DESC, sr.timenow DESC
                        LIMIT 1
                    ) AS priority,
                
                    (
                        SELECT sr.datesupport
                        FROM suport_report sr
                        WHERE sr.username = p.codemeli
                        ORDER BY sr.datesupport DESC, sr.timenow DESC
                        LIMIT 1
                    ) AS datesupport
                
                FROM dbpersonal p
                
                LEFT JOIN (
                    SELECT
                        d.username,
                        COUNT(*) AS course_count,
                        GROUP_CONCAT(DISTINCT d.codecourse ORDER BY d.codecourse SEPARATOR ',') AS course_codes,
                        GROUP_CONCAT(DISTINCT cc.coursename ORDER BY cc.coursename SEPARATOR '، ') AS course_names
                    FROM dbcourse d
                    INNER JOIN codtbl_course cc
                        ON cc.codecourse = d.codecourse
                    WHERE d.activityuser = 1
                      AND cc.activecourse = 1
                    GROUP BY d.username
                ) dc ON dc.username = p.codemeli
                
                WHERE p.creator = ?
                  AND CHAR_LENGTH(p.codemeli) = 11
                  AND p.datecreated = CURDATE()
                ORDER BY full_name;
                ";

        $stmt = $conn->prepare($listSql);
        $stmt->execute([$sessionUser]);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);

    }
}
if ($type === 1) {
    if ($isadmin==1) {
        $listSql = "
            SELECT
                p.codemeli,
                CONCAT(p.fname, ' ', p.lname) AS full_name,
                p.datecreated,
            
                COALESCE(dc.course_count, 0) AS course_count,
                COALESCE(dc.course_codes, '') AS course_codes,
                COALESCE(dc.course_names, '') AS course_names,
            
                (
                    SELECT sr.priority
                    FROM suport_report sr
                    WHERE sr.username = p.codemeli
                    ORDER BY sr.datesupport DESC, sr.timenow DESC
                    LIMIT 1
                ) AS priority,
            
                (
                    SELECT sr.datesupport
                    FROM suport_report sr
                    WHERE sr.username = p.codemeli
                    ORDER BY sr.datesupport DESC, sr.timenow DESC
                    LIMIT 1
                ) AS datesupport,
            
                p.creator AS creator_id,
                CONCAT(c.fname, ' ', c.lname) AS creator_name
            
            FROM dbpersonal p
            
            LEFT JOIN dbpersonal c
                ON c.codemeli = p.creator
            
            LEFT JOIN (
                SELECT
                    d.username,
                    COUNT(*) AS course_count,
                    GROUP_CONCAT(DISTINCT d.codecourse ORDER BY d.codecourse SEPARATOR ',') AS course_codes,
                    GROUP_CONCAT(DISTINCT cc.coursename ORDER BY cc.coursename SEPARATOR '، ') AS course_names
                FROM dbcourse d
                INNER JOIN codtbl_course cc
                    ON cc.codecourse = d.codecourse
                WHERE d.activityuser = 1
                  AND cc.activecourse = 1
                GROUP BY d.username
            ) dc ON dc.username = p.codemeli
            
            WHERE CHAR_LENGTH(p.codemeli) = 11
            ORDER BY full_name;
";

        $stmt = $conn->prepare($listSql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);


    } else {
        $listSql = "
            SELECT
                p.codemeli,
                CONCAT(p.fname, ' ', p.lname) AS full_name,
                p.datecreated,
            
                COALESCE(dc.course_count, 0) AS course_count,
                COALESCE(dc.course_codes, '') AS course_codes,
                COALESCE(dc.course_names, '') AS course_names,
            
                (
                    SELECT sr.priority
                    FROM suport_report sr
                    WHERE sr.username = p.codemeli
                    ORDER BY sr.datesupport DESC, sr.timenow DESC
                    LIMIT 1
                ) AS priority,
            
                (
                    SELECT sr.datesupport
                    FROM suport_report sr
                    WHERE sr.username = p.codemeli
                    ORDER BY sr.datesupport DESC, sr.timenow DESC
                    LIMIT 1
                ) AS datesupport
            
            FROM dbpersonal p
            
            LEFT JOIN (
                SELECT
                    d.username,
                    COUNT(*) AS course_count,
                    GROUP_CONCAT(DISTINCT d.codecourse ORDER BY d.codecourse SEPARATOR ',') AS course_codes,
                    GROUP_CONCAT(DISTINCT cc.coursename ORDER BY cc.coursename SEPARATOR '، ') AS course_names
                FROM dbcourse d
                INNER JOIN codtbl_course cc
                    ON cc.codecourse = d.codecourse
                WHERE d.activityuser = 1
                  AND cc.activecourse = 1
                GROUP BY d.username
            ) dc ON dc.username = p.codemeli
            
            WHERE p.creator = ?
              AND CHAR_LENGTH(p.codemeli) = 11
            ORDER BY full_name;
            ";

        $stmt = $conn->prepare($listSql);
        $stmt->execute([$sessionUser]);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);

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
    <title> دوره های فعال | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<!-- Loader -->
<!-- <div id="global-loader">
<img src="<//?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div> -->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php include_once '../menu.php';
    include_once '../header.php';
    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div style="padding:10px;">
                    <style>
                        .custom-tabs .nav-link {
                            border: none;
                            border-radius: 50px;
                            margin-right: 10px;
                            padding: 10px 25px;
                            font-weight: bold;
                            color: #555;
                            background: linear-gradient(145deg, #f0f0f0, #e0e0e0);
                            transition: all 0.3s ease;
                            box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.1);
                        }
                        .custom-tabs .nav-link:hover {
                            background: linear-gradient(145deg, #4e73df, #1cc88a);
                            color: #fff;
                            transform: translateY(-3px);
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2);
                        }
                        .custom-tabs .nav-link.active {
                            background: linear-gradient(145deg, #1cc88a, #36b9cc);
                            color: #fff;
                            font-size: 1.05rem;
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.25);
                        }
                    </style>
                </div>
                <div class="row row-sm">
                    <?php if (isset($_SESSION['message'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']);
                    } ?>
                    <div class="tab-content custom-tab-pane">
                        <!-- Type 1 tab -->
                        <div class="tab-pane fade show active" id="type1" role="tabpanel">
                            <?php if ($type==1) { ?>
                                <p class="fs-6 font-weight-bold"> لیست نفرات موقت </p>
                            <?php } ?>
                            <?php if ($type==2) { ?>
                                <p class="fs-6 font-weight-bold"> لیست نفرات موقت امروز </p>
                            <?php } ?>
                            <div class="col-lg-12">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="table">
                                            <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                                <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <?php if ($isadmin==1) { ?>
                                                        <th>کارشناس</th>
                                                    <?php } ?>
                                                    <th>نام و نام خانوادگی</th>
                                                    <th>شماره تلفن</th>
                                                    <th>تاریخ ثبت نام</th>
                                                    <th>اولویت</th>
                                                    <th> تاریخ</th>
                                                    <th>اقدامات</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($users)) { ?>
                                                    <tr class="align-middle text-center">
                                                        <td colspan="8">موردی یافت نشد</td>
                                                    </tr>
                                                <?php } else { ?>
                                                    <?php foreach ($users as $u) {

                                                        $priorityText = 'مشخص نشده';
                                                        $dateText = 'مشخص نشده';

                                                        if ($u->priority !== null) {
                                                            if ((int)$u->priority === 0) $priorityText = 'معمولی';
                                                            elseif ((int)$u->priority === 1) $priorityText = 'مهم';
                                                            elseif ((int)$u->priority === 2) $priorityText = 'خیلی مهم';
                                                        }

                                                        if (!empty($u->datesupport)) {
                                                            $dateText = $u->datesupport; // expected YYYY-MM-DD
                                                        }

                                                        // datecreated -> jalali
                                                        $jalaliCreated = '';
                                                        if (!empty($u->datecreated) && strpos($u->datecreated, '-') !== false) {
                                                            list($gy, $gm, $gd) = explode('-', $u->datecreated);
                                                            $jalaliCreated = implode('/', gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd));
                                                        }

                                                        // datesupport -> jalali (if valid)
                                                        $jalaliSupport = $dateText;
                                                        if ($dateText !== 'مشخص نشده' && strpos($dateText, '-') !== false) {
                                                            list($gy2, $gm2, $gd2) = explode('-', $dateText);
                                                            $jalaliSupport = implode('/', gregorian_to_jalali((int)$gy2, (int)$gm2, (int)$gd2));
                                                        }
                                                        ?>
                                                        <tr class="align-middle text-center">
                                                            <td>
                                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                                     onclick="window.open('suporting.php?Id=<?= htmlspecialchars($u->codemeli) ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                                    <i class="fa fa-key"></i>
                                                                </div>
                                                            </td>
                                                            <?php if ($isadmin==1) { ?>
                                                                <td><?= htmlspecialchars($u->creator_name ?: ($u->creator_id ?? '-')) ?></td>
                                                            <?php } ?>
                                                            <td><?= htmlspecialchars($u->full_name) ?></td>
                                                            <td><?= htmlspecialchars($u->codemeli) ?></td>
                                                            <td><?= htmlspecialchars($jalaliCreated) ?></td>
                                                            <td><?= htmlspecialchars($priorityText) ?></td>
                                                            <td><?= htmlspecialchars($jalaliSupport) ?></td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                        اقدام <i class="bi bi-caret-down-fill ms-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-end text-start">
                                                                        <a class="dropdown-item" href="user.php?codemeli=<?= urlencode($u->codemeli) ?>">مشاهده</a>
                                                                        <hr class="dropdown-divider">
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
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
        </div>
    </div>
</div>
<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->
</body>
</html>
