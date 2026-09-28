<?php
require_once __DIR__ . '/../inc/jdf.php';

if (!isset($_SESSION['admin'])) {
    header('location: login.php');
    die();
}

// New tree-based grants are enforced for every registered admin page here.
// Accounts that have not yet been migrated keep their existing legacy checks.
if (function_exists('EnforceAdminPageTreePermission')) {
    EnforceAdminPageTreePermission();
}

$canImpersonate = !empty($_SESSION['admin']['can_impersonate']) && (int)$_SESSION['admin']['can_impersonate'] === 1;

// CSRF token for impersonation
if (empty($_SESSION['admin']['impersonate_token'])) {
    $_SESSION['admin']['impersonate_token'] = bin2hex(random_bytes(16));
}

// Auto-expire impersonation (optional)
if (!empty($_SESSION['admin']['acting_until']) && time() > (int)$_SESSION['admin']['acting_until']) {
    if (!empty($_SESSION['admin']['real_user'])) {
        $_SESSION['admin']['user'] = $_SESSION['admin']['real_user'];
        foreach (['adminrole', 'role', 'name', 'famili'] as $key) {
            if (array_key_exists('real_' . $key, $_SESSION['admin'])) $_SESSION['admin'][$key] = $_SESSION['admin']['real_' . $key];
        }
    }
    unset($_SESSION['admin']['real_user'], $_SESSION['admin']['real_adminrole'], $_SESSION['admin']['real_role'],
        $_SESSION['admin']['real_name'], $_SESSION['admin']['real_famili'], $_SESSION['admin']['acting_until']);
}

$isImpersonating = !empty($_SESSION['admin']['real_user']);
$realUser = $_SESSION['admin']['real_user'] ?? $_SESSION['admin']['user'];
$viewingUser = $_SESSION['admin']['user'] ?? '';

// Load & cache user list (so we don't query DB on every page)
$impersonateUsers = [];
if ($canImpersonate) {
    if (empty($_SESSION['admin']['impersonate_user_list']) || !is_array($_SESSION['admin']['impersonate_user_list'])) {
        $stmt = $conn->prepare("SELECT username FROM dbadmin ORDER BY username ASC");
        $stmt->execute();
        $_SESSION['admin']['impersonate_user_list'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    $impersonateUsers = $_SESSION['admin']['impersonate_user_list'];
}
?>

<style>
    /* نمایش نام مدرسه در حالت دسکتاپ - سر جای خودش */
    .school-name-desktop {
        display: block;
    }

    /* در حالت دسکتاپ، نسخه موبایل مخفی باشه */
    .school-name-mobile {
        display: none;
    }

    /* حالت موبایل */
    @media (max-width: 991px) {
        /* نام مدرسه دسکتاپ مخفی بشه */
        .school-name-desktop {
            display: none !important;
        }

        /* نام مدرسه موبایل نمایش داده بشه */
        .school-name-mobile {
            display: inline-block !important;
            font-size: 13px;
            font-weight: 700;
            color: #1a3b4c;
            margin-left: 8px;
            white-space: nowrap;
            max-width: 150px;
            /*overflow: hidden;*/
            /*text-overflow: ellipsis;*/
            vertical-align: middle;
        }

        /* آواتار و متن کنار هم */
        .mobile-avatar-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
    }
</style>

<div class="main-header side-header sticky">
        <div class="container-fluid">
            <div class="main-header-right">
                <a class="main-header-menu-icon" href="#" id="mainSidebarToggle"><span></span></a>
            </div>
            <div class="main-header-center school-name-desktop">
                <div class="responsive-logo">
                    <a href="<?php echo url('index.php') ?>"><img src="<?php echo url('assets/img/pngs/logo-hr.png') ?>" style="width: 134px; height:37px;"  class="mobile-logo" alt="لوگو"></a>


                </div>

                <?php
                echo jdate('l Y/m/d ') . ' - ' . __SCHOOL_NAME;
                $selectedAcademicYear = filter_var($_SESSION['academic_year_base'] ?? null, FILTER_VALIDATE_INT);
                if ($selectedAcademicYear !== false && $selectedAcademicYear >= 1300 && $selectedAcademicYear <= 1600) {
                    echo ' <span class="badge bg-warning text-dark">نمای بایگانی ' . (int)$selectedAcademicYear . '–' . ((int)$selectedAcademicYear + 1) . '</span>';
                }
                ?>
                
                

            </div>
            <div class="main-header-right">
                <?php if ($canImpersonate): ?>
                    <div class="container-fluid mt-1">
                        <div class="d-flex align-items-center justify-content-between flex-wrap py-1 px-2 border rounded bg-light" style="gap:10px;">
                            <div class="small">
                                <?php if (!empty($_SESSION['admin']['real_user'])): ?>
                                    <span class="badge bg-warning text-dark">Viewing</span>
                                    <b><?= htmlentities($_SESSION['admin']['user']) ?></b>
                                    <span class="text-muted"> (real: <?= htmlentities($_SESSION['admin']['real_user']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-muted">Real: <b><?= htmlentities($_SESSION['admin']['user']) ?></b></span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex align-items-center" style="gap:8px;">
                                <form method="post" action="<?= url('admin/inc/impersonate_start.php') ?>" class="d-flex align-items-center" style="gap:8px;">
                                    <input type="hidden" name="csrf" value="<?= htmlentities($_SESSION['admin']['impersonate_token']) ?>">
                                    <select class="form-select form-select-sm" name="user" required style="width:180px;">
                                        <option value="" disabled selected>View as…</option>
                                        <?php foreach ($impersonateUsers as $u): ?>
                                            <option value="<?= htmlentities($u) ?>"><?= htmlentities($u) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-primary" type="submit">Start</button>
                                </form>

                                <?php if (!empty($_SESSION['admin']['real_user'])): ?>
                                    <form method="post" action="<?= url('admin/inc/impersonate_stop.php') ?>" class="m-0">
                                        <input type="hidden" name="csrf" value="<?= htmlentities($_SESSION['admin']['impersonate_token']) ?>">
                                        <button class="btn btn-sm btn-outline-dark" type="submit">Stop</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>



                <!-- <div class="dropdown main-header-notification">
                    <a class="nav-link icon" href="#">
                        <i class="fe fe-bell header-icons"></i>
                        <span class="badge badge-warning nav-link-badge">1</span>
                    </a>

                    <div class="dropdown-menu">
                        <div class="header-navheading">
                            <p class="main-notification-text">شما 1 اعلان خوانده نشده <span class="badge badge-pill badge-primary mr-3">مشاهده همه</span></p>
                        </div>
                        <div class="main-notification-list">
                            <div class="media new">
                                <div class="main-img-user online"><img alt="آواتار" src="<?php echo url('assets/img/pngs/work2.png') ?>"></div>
                                <div class="media-body">
                                    <p>به <strong>موسسه هنرآموزش</strong>خوش آمدید</p><span>همین الان</span>
                                </div>
                            </div>

                        </div>
                        <div class="dropdown-footer">
                            <a href="#">مشاهده همه اعلان ها</a>
                        </div>
                    </div>
                </div> -->







                <div class="dropdown main-profile-menu">
                    <a class="d-flex" href="#">
                                       <span class="main-img-user">
                        <?php if ($_SESSION['admin']['adminrole'] == 3 ){  ?>
                            <img alt="آواتار" src="<?php echo url('assets/img/users/ostad.png') ?>">
                        <?php    } elseif ($_SESSION['admin']['adminrole'] == 1 ){ ?>
                            <img alt="آواتار" src="<?php echo url('assets/img/users/poshtiban.png') ?>">
                        <?php } else { ?>
                            <img alt="آواتار" src="<?php echo url('assets/img/users/admin.png') ?>">
                        <?php } ?>
                    </span>

                    </a>
                    <div class="dropdown-menu">
                        <div class="header-navheading">
                            <h6 class="main-notification-title"><?php echo $_SESSION['admin']['name']." " .$_SESSION['admin']['famili'] ?></h6>
                            <p class="main-notification-text">داخلی: <?php echo $_SESSION['admin']['dakheli'] ?>  </p>
                        </div>
                        <a class="dropdown-item border-top" href="<?php echo url('') ?>">
                            <i class="fe fe-user"></i>ویرایش کاربری
                        </a>


                        <a class="dropdown-item" href="?logoutadmin">
                            <i class="fe fe-power"></i>خارج شدن
                        </a>
                    </div>
                </div>
                <button class="navbar-toggler navresponsive-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarSupportedContent-4" aria-controls="navbarSupportedContent-4"
                        aria-expanded="false" aria-label="تغییر پیمایش">
                    <i class="fe fe-more-vertical header-icons navbar-toggler-icon"></i>
                </button><!-- Navresponsive closed -->

            </div>
        </div>
    </div>
    <!-- End Main Header-->        <!-- Mobile-header -->

    <div class="mobile-main-header">

        <div class="mb-1 navbar navbar-expand-lg  nav nav-item  navbar-nav-right responsive-navbar navbar-dark  ">
            <div class="collapse navbar-collapse" id="navbarSupportedContent-4">
                <div class="d-flex order-lg-2 mr-auto">
                    <!-- <div class="dropdown main-header-notification">


                    </div> -->
                    <div class="main-header-notification">
                        <a class="nav-link icon" href="tel:+982191098185">
                            <i class="fe fe-phone-call header-icons"></i>

                        </a>
                    </div>
                    <div class="dropdown main-profile-menu">

                        <a class="d-flex" href="#">
                            <span class="main-img-user"><img alt="avatar"
                                                             src="<?php echo url('assets/img/users/1.jpg') ?>"></span>
                        </a>
                        <span class="school-name-mobile">
    <?php echo __SCHOOL_NAME ?>
</span>
                        <div class="dropdown-menu">
                            <div class="header-navheading">
                                <h6 class="main-notification-title"><?php echo $_SESSION['admin']['name']." " .$_SESSION['admin']['famili'] ?></h6>
                                <p class="main-notification-text"></p>
                            </div>
                            <a class="dropdown-item border-top" href="<?php echo url('') ?>">
                                <i class="fe fe-user"></i> پروفایل من
                            </a>

                            <a class="dropdown-item" href="?logoutadmin">
                                <i class="fe fe-power"></i> خروج از سیستم
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
