<?php
if (!isset($_SESSION['admin'])) {
header('location: login.php');
die();
}
?>

    <div class="main-header side-header sticky">
        <div class="container-fluid">
            <div class="main-header-right">
                <a class="main-header-menu-icon" href="#" id="mainSidebarToggle"><span></span></a>
            </div>
            <div class="main-header-center">
                <div class="responsive-logo">
                    <a href="<?php echo url('index.php') ?>"><img src="<?php echo url('assets/img/pngs/logo-hr.png') ?>" style="width: 134px; height:37px;"  class="mobile-logo" alt="لوگو"></a>


                </div>

                <?php
                echo jdate('l Y/m/d ');
                ?>

            </div>
            <div class="main-header-right">
                <div class="dropdown main-header-notification">
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
                </div>







                <div class="dropdown main-profile-menu">
                    <a class="d-flex" href="#">
                    <span class="main-img-user"><img alt="آواتار"
                                                     src="<?php echo url('assets/img/users/1.jpg') ?>"></span>
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
                    <div class="dropdown main-header-notification">


                    </div>
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
