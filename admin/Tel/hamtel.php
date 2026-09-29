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
if (!AdminCanSee('public-relations.safe-call.exchange')) {
    header('location: ' . url('admin/index.php'));
    die();
}

$verify = null;

/* =========================
   جابجایی خطوط اپراتور به اپراتور
   ========================= */
if (isset($_POST['save'])) {
    AdminRequire('public-relations.safe-call.exchange.move');
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $newgroup = intval($_POST['group-new']);
        $oldgroup = intval($_POST['group-old']);
        $actold   = intval($_POST['act-old']);
        $actnew   = intval($_POST['act-new']);
        $fer      = intval($_POST['ferestande']);

        // ✅ NEW: multi-reciver list from custom dropdown (comma-separated dakheli values)
        $reciveRaw = $_POST['recive_list'] ?? '';
        $reciveIds = array_filter(array_map('intval', array_filter(explode(',', $reciveRaw))));
        $reciveIds = array_values(array_unique($reciveIds));

        if (!empty($reciveIds)) {

            // 🔍 same filters as old code, but now we first SELECT the matching rows
            if ($actnew != 6 && $actold != 6) {
                // old: UPDATE ... WHERE dakheli=? AND `group`=? AND act=?
                $sqlSelect = 'SELECT id, act FROM tel_db WHERE dakheli=? AND `group`=? AND act=?';
                $params    = [$fer, $oldgroup, $actold];
            } elseif ($actnew != 6 && $actold == 6) {
                // old: UPDATE ... WHERE dakheli=? AND `group`=?
                $sqlSelect = 'SELECT id, act FROM tel_db WHERE dakheli=? AND `group`=?';
                $params    = [$fer, $oldgroup];
            } elseif ($actnew == 6 && $actold == 6) {
                // old: UPDATE ... WHERE dakheli=? AND `group`=?
                $sqlSelect = 'SELECT id, act FROM tel_db WHERE dakheli=? AND `group`=?';
                $params    = [$fer, $oldgroup];
            } else { // $actnew == 6 && $actold != 6
                // old: UPDATE ... WHERE dakheli=? AND `group`=? AND act=?
                $sqlSelect = 'SELECT id, act FROM tel_db WHERE dakheli=? AND `group`=? AND act=?';
                $params    = [$fer, $oldgroup, $actold];
            }

            $stmt = $conn->prepare($sqlSelect);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rows) {
                // 🔀 random + تقریبا مساوی بین اپراتورها
                shuffle($rows);
                shuffle($reciveIds);
                $targetCount = count($reciveIds);

                // اگر actnew = 6 باشد، وضعیت را دست نمی‌زنیم
                if ($actnew == 6) {
                    $sqlUpdate = 'UPDATE tel_db SET `group`=?, dakheli=? WHERE id=?';
                } else {
                    $sqlUpdate = 'UPDATE tel_db SET `group`=?, dakheli=?, act=? WHERE id=?';
                }
                $u = $conn->prepare($sqlUpdate);

                $i = 0;
                foreach ($rows as $row) {
                    $targetDakheli = $reciveIds[$i % $targetCount]; // پخش تقریبا برابر
                    $i++;

                    if ($actnew == 6) {
                        // act قبلی حفظ می‌شود
                        $u->execute([$newgroup, $targetDakheli, $row['id']]);
                    } else {
                        // act جدید برای همه
                        $u->execute([$newgroup, $targetDakheli, $actnew, $row['id']]);
                    }
                }

                $verify = 100;
            } else {
                $verify = 0;
            }
        } else {
            $verify = 0;
        }
    }
}

/* =========================
   تخصیص کل گروه یه یک نفر (درون گروهی) – group to operators
   ========================= */
if (isset($_POST['group-to-operator'])) {
    AdminRequire('public-relations.safe-call.exchange.group-to-operator');
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $ferestande = intval($_POST['group-fer']); // گروه فرستنده
        $actfer     = intval($_POST['act-fer']);   // وضعیت فعلی گروه
        $actrec     = intval($_POST['act-rec']);   // وضعیت گیرنده / مطابق فرستنده

        // ✅ NEW: multi-operator list (comma-separated dakheli values)
        $oprecRaw = $_POST['operator_rec_list'] ?? '';
        $oprecIds = array_filter(array_map('intval', array_filter(explode(',', $oprecRaw))));
        $oprecIds = array_values(array_unique($oprecIds));

        if (!empty($oprecIds)) {

            // 🔍 همان منطق فیلتر قبلی، فقط به صورت SELECT
            if ($actfer != 6 && $actrec != 6) {
                // old: UPDATE ... WHERE `group`=? AND act=?
                $sqlSelect = 'SELECT id, act FROM tel_db WHERE `group`=? AND act=?';
                $params    = [$ferestande, $actfer];
            } elseif ($actfer != 6 && $actrec == 6) {
                // old: UPDATE ... WHERE `group`=? AND act=?  (act دست نمی‌خورد)
                $sqlSelect = 'SELECT id, act FROM tel_db WHERE `group`=? AND act=?';
                $params    = [$ferestande, $actfer];
            } elseif ($actfer == 6 && $actrec != 6) {
                // old: UPDATE ... WHERE `group`=?  (act برای همه جدید می‌شود)
                $sqlSelect = 'SELECT id, act FROM tel_db WHERE `group`=?';
                $params    = [$ferestande];
            } else { // $actfer == 6 && $actrec == 6
                // old: UPDATE ... WHERE `group`=?  (فقط اپراتور عوض می‌شود)
                $sqlSelect = 'SELECT id, act FROM tel_db WHERE `group`=?';
                $params    = [$ferestande];
            }

            $stmt = $conn->prepare($sqlSelect);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rows) {
                shuffle($rows);
                shuffle($oprecIds);
                $targetCount = count($oprecIds);

                // این فرم group را دست نمی‌زند، فقط dakheli و شاید act
                if ($actrec == 6) {
                    $sqlUpdate = 'UPDATE tel_db SET dakheli=? WHERE id=?';
                } else {
                    $sqlUpdate = 'UPDATE tel_db SET dakheli=?, act=? WHERE id=?';
                }
                $u = $conn->prepare($sqlUpdate);

                $i = 0;
                foreach ($rows as $row) {
                    $targetDakheli = $oprecIds[$i % $targetCount];
                    $i++;

                    if ($actrec == 6) {
                        // act قبلی حفظ می‌شود
                        $u->execute([$targetDakheli, $row['id']]);
                    } else {
                        // act جدید برای همه
                        $u->execute([$targetDakheli, $actrec, $row['id']]);
                    }
                }

                $verify = 100;
            } else {
                $verify = 0;
            }
        } else {
            $verify = 0;
        }
    }
}

/* =========================
   ادغام یک گروه با گروه دیگر – ORIGINAL LOGIC (UNTOUCHED)
   ========================= */
if (isset($_POST['group-to-group'])) {
    AdminRequire('public-relations.safe-call.exchange.group-to-group');
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $ggroup = intval($_POST['ggroup']);
        $gact   = intval($_POST['gact']);
        $rgroup = intval($_POST['rgroup']);
        $ract   = intval($_POST['ract']);
        if ($gact != 6 and $ract != 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?,act=? WHERE  `group`=? AND act=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$rgroup, $ract, $ggroup, $gact]);
            $verify = 100;
        }
        if ($gact != 6 and $ract == 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?  WHERE  `group`=? AND act=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$rgroup, $ggroup, $gact]);
            $verify = 100;
        }
        if ($gact == 6 and $ract != 6) {
            $sql1 = 'UPDATE tel_db SET `group`=? ,act=? WHERE  `group`=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$rgroup, $ract, $ggroup]);
            $verify = 100;
        }
        if ($gact == 6 and $ract == 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?  WHERE  `group`=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$rgroup, $ggroup]);
            $verify = 100;
        }

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
    <title> مرکز تبادل تلفن |<?php echo __TITLE ?></title>
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
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1"
                                            style="display:inline-flex;
                                               align-items:center;
                                               gap:7px;">
                                            <span><?= inst_is('school') ? 'جابجایی خطوط بین مشاورین' : 'جابجایی خطوط اپراتور به اپراتور' ?></span>
                                            <span class="menu-info-trigger"
                                                  data-tooltip="<?= htmlspecialchars(inst_is('school') ? 'در این بخش می‌توانید شماره‌های تماس یک مشاور را بر اساس وضعیت تماس و گروه انتخاب کرده و به یک یا چند مشاور دیگر منتقل کنید.&#10;&#10;هنگام انتقال، امکان تعیین گروه و وضعیت جدید تماس‌ها نیز وجود دارد.' : 'در این بخش می‌توانید شماره‌های تماس یک اپراتور را بر اساس وضعیت تماس و گروه انتخاب کرده و به یک یا چند اپراتور دیگر منتقل کنید.&#10;&#10;هنگام انتقال، امکان تعیین گروه و وضعیت جدید تماس‌ها نیز وجود دارد.', ENT_QUOTES, 'UTF-8', false) ?>"
                                                  aria-label="<?= htmlspecialchars(inst_is('school') ? 'راهنمای جابجایی خطوط مشاوران' : 'راهنمای جابجایی خطوط اپراتورها', ENT_QUOTES, 'UTF-8', false) ?>"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </h6>
                                        <p class="text-muted card-sub-title">
                                        </p>
                                    </div>
                                </div>
                                <?php if (isset($verify) && $verify == 100) { ?>
                                    <div class="alert alert-success" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        انتقال با موفقیت انجام شد
                                    </div>
                                <?php } ?>
                                <?php if (isset($verify) && $verify === 0) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        خطا ! انتقال انجام نشد
                                    </div>
                                <?php } ?>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتقالی از<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="ferestande"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                //                                                $sql = 'SELECT * FROM dbadmin WHERE permision_suporter = 1 OR adminrole=2 ';
                                                $sql = 'SELECT * FROM dbadmin';
                                                $query = $conn->prepare($sql);
                                                $query->execute();
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result as $res) {
                                                        ?>
                                                        <option value="<?php echo $res->dakheli ?>"><?php echo $res->fname . ' ' . $res->lname . ' (' . $res->dakheli . ')' ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> وضعیت تماس فرستنده<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="act-old"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">همه تماس ها</option>
                                            </select>
                                        </div>
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب گروه تماس<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="group-old"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {
                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="text-center">
                                            <hr>
                                        </div>

                                        <!-- انتقالی به: MULTI-SELECT DROPDOWN -->
                                        <div class="col-xl-4 mb-2">
                                            <label class="form-label mg-t-15">انتقالی به<span
                                                        style="color: red">*</span></label>

                                            <div class="dropdown w-100" id="operatorTransferWrapper">
                                                <button class="btn btn-outline-info w-100 d-flex justify-content-between align-items-center"
                                                        type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                        data-role="trigger">
                                                    <span data-role="label"><?= inst_is('school') ? 'انتخاب مشاور' : 'انتخاب اپراتورها' ?></span>
                                                    <span class="ms-2 small text-muted">▼</span>
                                                </button>

                                                <div class="dropdown-menu p-3 shadow w-100"
                                                     style="max-height:400px; overflow:auto;">

                                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                                        <input type="text"
                                                               class="form-control form-control-sm flex-grow-1"
                                                               placeholder="جستجو..."
                                                               data-role="search">

                                                        <button type="button"
                                                                class="btn btn-outline-info btn-sm flex-shrink-0"
                                                                data-role="select-all">
                                                            انتخاب همه
                                                        </button>

                                                        <button type="button"
                                                                class="btn btn-outline-secondary btn-sm flex-shrink-0"
                                                                data-role="clear-all">
                                                            حذف انتخاب‌ها
                                                        </button>
                                                    </div>

                                                    <div class="d-flex flex-wrap gap-2" data-role="pill-list">
                                                        <?php
                                                        $sql = 'SELECT * FROM dbadmin WHERE permision_suporter = 1 OR adminrole=2 ';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute();
                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                        if ($query->rowCount() > 0) {
                                                            foreach ($result as $res) {
                                                                $val = (int)$res->dakheli;
                                                                $txt = htmlspecialchars(
                                                                    $res->fname . ' ' . $res->lname . ' (' . $res->dakheli . ')',
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                                echo '<button type="button" class="operator-pill btn btn-outline-primary btn-sm" data-value="'.$val.'">'.$txt.'</button>';
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <input type="hidden" name="recive_list" id="recive_list">
                                        </div>

                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15">وضعیت تماس گیرنده<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="act-new"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">مطابق وضعیت فرستنده</option>
                                            </select>
                                        </div>
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب گروه تماس<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="group-new"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {
                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">

                                        <div class="text-center mg-10">
                                            <button type="submit" class="btn btn-success "
                                                    name="save"><i
                                                        class="fa fa-save"></i>
                                                تایید و انتقال خطوط
                                            </button>
                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- تخصیص کل گروه یه یک نفر (درون گروهی) -->
                <div class="row row-sm">
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1"
                                            style="display:inline-flex;
                                            align-items:center;
                                            gap:7px;">
                                            <span>تخصیص کل گروه به یک نفر (درون‌گروهی)</span>
                                            <span class="menu-info-trigger"
                                                  data-tooltip="<?= htmlspecialchars(inst_is('school') ? 'مخاطبان گروه انتخاب‌شده، بر اساس وضعیت تعیین‌شده، از مشاوران قبلی جدا شده و به مشاور یا مشاوران گیرنده تخصیص داده می‌شوند.&#10;&#10;وضعیت تماس مخاطبان پس از انتقال نیز قابل تعیین است.' : 'مخاطبان گروه انتخاب‌شده، بر اساس وضعیت تعیین‌شده، از اپراتورهای قبلی جدا شده و به اپراتور یا اپراتورهای گیرنده تخصیص داده می‌شوند.&#10;&#10;وضعیت تماس مخاطبان پس از انتقال نیز قابل تعیین است.', ENT_QUOTES, 'UTF-8', false) ?>"
                                                  aria-label="<?= htmlspecialchars(inst_is('school') ? 'راهنمای تخصیص گروه به مشاور' : 'راهنمای تخصیص گروه به اپراتور', ENT_QUOTES, 'UTF-8', false) ?>"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </h6>
                                        <p class="text-muted card-sub-title"><?= inst_is('school') ? 'کل مخاطبان یک گروه از مشاوران قبلی جدا و به مشاور جدید متصل می‌شوند' : 'کل مخاطبان یک گروه از اپراتورهای قبلی جدا و به اپراتور جدید متصل می‌شوند' ?>
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتخاب گروه فرستنده:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="group-fer"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {
                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب وضعیت گروه<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="act-fer"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">همه مخاطبین</option>
                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <hr>
                                        </div>
                                        <!-- انتخاب اپراتور گیرنده: MULTI-SELECT -->
                                        <div class="col-xl-6 mb-2">
                                            <label class="form-label mg-t-15"> <?= inst_is('school') ? 'انتخاب مشاور گیرنده:' : 'انتخاب اپراتور گیرنده:' ?><span
                                                        style="color: red">*</span></label>

                                            <div class="dropdown w-100" id="groupOperatorWrapper">
                                                <button class="btn btn-outline-info w-100 d-flex justify-content-between align-items-center"
                                                        type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                        data-role="trigger">
                                                    <span data-role="label"><?= inst_is('school') ? 'انتخاب مشاور' : 'انتخاب اپراتورها' ?></span>
                                                    <span class="ms-2 small text-muted">▼</span>
                                                </button>

                                                <div class="dropdown-menu p-3 shadow w-100"
                                                     style="max-height:400px; overflow:auto;">

                                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                                        <input type="text"
                                                               class="form-control form-control-sm flex-grow-1"
                                                               placeholder="جستجو..."
                                                               data-role="search">

                                                        <button type="button"
                                                                class="btn btn-outline-info btn-sm flex-shrink-0"
                                                                data-role="select-all">
                                                            انتخاب همه
                                                        </button>

                                                        <button type="button"
                                                                class="btn btn-outline-secondary btn-sm flex-shrink-0"
                                                                data-role="clear-all">
                                                            حذف انتخاب‌ها
                                                        </button>
                                                    </div>

                                                    <div class="d-flex flex-wrap gap-2" data-role="pill-list">
                                                        <?php
                                                        $sql = 'SELECT * FROM dbadmin WHERE permision_suporter = 1 OR adminrole=2 ';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute();
                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                        if ($query->rowCount() > 0) {
                                                            foreach ($result as $res) {
                                                                $val = (int)$res->dakheli;
                                                                $txt = htmlspecialchars(
                                                                    $res->fname . ' ' . $res->lname . ' (' . $res->dakheli . ')',
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                                echo '<button type="button" class="operator-pill btn btn-outline-primary btn-sm" data-value="'.$val.'">'.$txt.'</button>';
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <input type="hidden" name="operator_rec_list" id="operator_rec_list">
                                        </div>

                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب وضعیت تماس<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="act-rec"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">مطابق فرستنده</option>
                                            </select>
                                        </div>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">

                                        <div class="text-center mg-10">
                                            <button type="submit" class="btn btn-outline-info "
                                                    name="group-to-operator"><i
                                                        class="fa fa-save"></i>
                                                تایید و انتقال خطوط
                                            </button>
                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>

                    <!-- ادغام یک گروه با گروهی دیگر (بدون تغییر در منطق) -->
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1"
                                            style="display:inline-flex;
                                                align-items:center;
                                                gap:7px;">
                                            <span>ادغام یک گروه با گروهی دیگر</span>
                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید مخاطبان گروه فرستنده را بر اساس وضعیت تماس انتخاب کرده و به گروه گیرنده منتقل کنید.&#10;&#10;پس از انتقال، مخاطبان از گروه قبلی حذف می‌شوند و می‌توانید وضعیت تماس جدید آن‌ها را تعیین کرده یا وضعیت قبلی را حفظ کنید."
                                                  aria-label="راهنمای ادغام گروه‌های تماس"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </h6>
                                        <p class="text-muted card-sub-title"> مخاطبان از گروه حذف و به گروهی دیگر متصل
                                            می
                                            شوند
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتخاب گروه فرستنده:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="ggroup"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {
                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب وضعیت گروه<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="gact"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">همه مخاطبین</option>
                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <hr>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتخاب گروه گیرنده:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="rgroup"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {
                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> تعیین وضعیت گروه<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="ract"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">مطابق فرستنده</option>
                                            </select>
                                        </div>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">

                                        <div class="text-center mg-10">
                                            <button type="submit" class="btn btn-outline-primary "
                                                    name="group-to-group"><i
                                                        class="fa fa-save"></i>
                                                تایید و انتقال خطوط
                                            </button>
                                        </div>
                                    </div>
                                </form>

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
        function setupMultiSelectDropdown(wrapperId, hiddenInputId, defaultLabel) {
            const wrapper = document.getElementById(wrapperId);
            const hidden  = document.getElementById(hiddenInputId);
            if (!wrapper || !hidden) return;

            const button      = wrapper.querySelector('button[data-role="trigger"]');
            const list        = wrapper.querySelector('[data-role="pill-list"]');
            const searchInput = wrapper.querySelector('input[data-role="search"]');
            const selectAll   = wrapper.querySelector('button[data-role="select-all"]');
            const clearAll    = wrapper.querySelector('button[data-role="clear-all"]');

            if (!button || !list) return;

            const pills = Array.from(list.querySelectorAll('[data-value]'));

            function applySearch() {
                if (!searchInput) return;
                const term = searchInput.value.trim().toLowerCase();
                pills.forEach(p => {
                    const txt = p.textContent.toLowerCase();
                    p.style.display = (!term || txt.includes(term)) ? '' : 'none';
                });
            }

            function sync() {
                const selected = pills
                    .filter(p => p.classList.contains('active'))
                    .map(p => p.getAttribute('data-value'));

                hidden.value = selected.join(',');

                const labelSpan = button.querySelector('span[data-role="label"]');
                if (labelSpan) {
                    if (selected.length === 0) {
                        labelSpan.textContent = defaultLabel;
                    } else {
                        labelSpan.textContent = defaultLabel + ' (' + selected.length + ')';
                    }
                }
            }

            pills.forEach(p => {
                p.addEventListener('click', function () {
                    this.classList.toggle('active');
                    sync();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applySearch);
            }

            if (selectAll) {
                selectAll.addEventListener('click', function () {
                    pills.forEach(p => {
                        if (p.style.display !== 'none') {
                            p.classList.add('active');
                        }
                    });
                    sync();
                });
            }

            if (clearAll) {
                clearAll.addEventListener('click', function () {
                    pills.forEach(p => p.classList.remove('active'));
                    if (searchInput) searchInput.value = '';
                    applySearch();
                    sync();
                });
            }

            // init
            applySearch();
            sync();
        }

        document.addEventListener('DOMContentLoaded', function () {
            // فرم ۱: جابجایی خطوط اپراتور به اپراتور
            setupMultiSelectDropdown('operatorTransferWrapper', 'recive_list', <?= json_encode(inst_is('school') ? 'انتخاب مشاور' : 'انتخاب اپراتورها', JSON_UNESCAPED_UNICODE) ?>);

            // فرم ۲: تخصیص کل گروه یه یک نفر
            setupMultiSelectDropdown('groupOperatorWrapper', 'operator_rec_list', <?= json_encode(inst_is('school') ? 'انتخاب مشاور' : 'انتخاب اپراتورها', JSON_UNESCAPED_UNICODE) ?>);
        });
    </script>

</div>
</body>
</html>
