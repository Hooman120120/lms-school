<?php
if(isset($_SESSION['auth'])){

$sqq='SELECT * FROM checkout WHERE username=?';
$quu=$conn->prepare($sqq);
$quu->execute([$_SESSION['auth']['user']]);
$rey=$quu->fetchAll();
if($quu->rowCount()>0){
    foreach ($rey as $sync){
       if(Delsync($_SESSION['auth']['user'],$sync['coursecode'])){
           $del='DELETE FROM checkout WHERE username=? AND coursecode=? ';
           $qurt=$conn->prepare($del);
           $qurt->execute([$_SESSION['auth']['user'],$sync['coursecode']]);
       }
    }
}
$sql='SELECT COUNT(id) as sumcheck FROM checkout INNER JOIN codtbl_course ON `checkout`.`coursecode`= `codtbl_course`.`codecourse` WHERE `checkout`.`username`=? AND `codtbl_course`.courseactive=?';
$query=$conn->prepare($sql);
$query->execute([$_SESSION['auth']['user'],1]);
$result=$query->fetch();
?>

<?php /* واژه‌نامهٔ سامانه برای استفادهٔ اسکریپت‌های سمت کاربر */ ?>
<script>
    window.INST_TYPE = <?php echo json_encode(inst_type()) ?>;
    window.TERMS     = <?php echo inst_terms_json() ?>;
</script>

<<div class="main-header side-header sticky">
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


<?php if($_SESSION['auth']['role']==1){?>
            <div class="dropdown main-header-notification">
                <a class="nav-link icon" href="#">
                    <i class="ti ti-shopping-cart header-icons"></i>
                    <span class="badge badge-danger nav-link-badge"><?php if ($result['sumcheck']>0){ echo $result['sumcheck'];} ?></span>
                </a>
                <div class="dropdown-menu">
                    <div class="main-notification-list">
                    <?php
                    $checkout='SELECT * FROM checkout INNER JOIN codtbl_course ON `checkout`.`coursecode`= `codtbl_course`.`codecourse` WHERE `checkout`.`username`=? AND `codtbl_course`.courseactive=?';
                    $query1=$conn->prepare($checkout);
                    $query1->execute([$_SESSION['auth']['user'],1]);
                    $result1=$query1->fetchAll();
                    if($query1->rowCount()>0){
                        foreach ($result1 as $res){



                    ?>

                        <a href="<?php echo url('course.php?IdCourse='). $res['codecourse'] ?>">
                        <div class="media">

                            <div class="main-img-user online"><img alt="پایه" src="<?php echo $res['piclink'] ?>"></div>
                            <div class="media-body">
                                <p><?php echo htmlentities($res['coursename']) ?></p><span><?php echo number_format($res['fee'])  ?>  تومان </span>
                            </div>

                        </div>
                        </a>

                        <?php
                        }
                        } else{
                        echo '<div class="text-center"> سبد خرید شما خالی است </div>';

                    }?>

                    </div>
                    <div class="dropdown-footer">
                        <a href="<?php echo url('checkout.php') ?>">مشاهده سبد خرید</a>
                    </div>
                </div>
            </div>
        <?php } ?>

            <div class="main-header-notification">
                <a class="nav-link icon" href="tel:+982191098185">
                    <i class="fe fe-phone-call header-icons"></i>

                </a>
            </div>

            <div class="dropdown main-profile-menu">
                <a class="d-flex" href="#">
                    <span class="main-img-user"><img alt="آواتار"
                                                     src="<?php echo url('assets/img/users/1.jpg') ?>"></span>
                </a>
                <div class="dropdown-menu">
                    <div class="header-navheading">
                        <h6 class="main-notification-title"><?php echo $_SESSION['auth']['name']." " .$_SESSION['auth']['famili'] ?></h6>
                        <p class="main-notification-text"><?php if($_SESSION['auth']['role']==1){ echo m('grade.with_number', [':n' => $_SESSION['auth']['paye']]);} else{ echo 'همکار آموزشی';} ?></p>
                    </div>
                    <a class="dropdown-item border-top" href="<?php echo url('Profile/user.php') ?>">
                        <i class="fe fe-user"></i>ویرایش کاربری
                    </a>


                    <a class="dropdown-item" href="?logout">
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
    <?php if($_SESSION['auth']['role']==1){?>
                <div class="dropdown main-header-notification">
                    <a class="nav-link icon" href="#">
                        <i class="ti ti-shopping-cart header-icons"></i>
                        <span class="badge badge-danger nav-link-badge"><?php if ($result['sumcheck']>0){ echo $result['sumcheck'];} ?></span>
                    </a>
                    <div class="dropdown-menu">

                        <div class="main-notification-list">
                            <?php
                            $checkout='SELECT * FROM checkout INNER JOIN codtbl_course ON `checkout`.`coursecode`= `codtbl_course`.`codecourse` WHERE `checkout`.`username`=? AND `codtbl_course`.courseactive=?';
                            $query1=$conn->prepare($checkout);
                            $query1->execute([$_SESSION['auth']['user'],1]);
                            $result1=$query1->fetchAll();
                            if($query1->rowCount()>0){
                                foreach ($result1 as $res){



                                    ?>

                            <a href="<?php echo url('course.php?IdCourse='). $res['codecourse'] ?>">
                                    <div class="media">
                                        <div class="main-img-user online"><img alt="پایه" src="<?php echo $res['piclink'] ?>"></div>
                                        <div class="media-body">
                                            <p><?php echo htmlentities($res['coursename']) ?></p><span><?php echo number_format($res['fee'])  ?>  تومان </span>
                                        </div>
                                    </div>
                            </a>
                                    <?php
                                }
                            }else{
                                echo '<div class="text-center"> سبد خرید شما خالی است </div>';

                            } ?>


                        </div>
                        <div class="dropdown-footer">
                            <a href="<?php echo url('checkout.php') ?>">مشاهده سبد خرید</a>
                        </div>
                    </div>
                </div>
        <?php } ?>
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
                            <h6 class="main-notification-title"><?php echo $_SESSION['auth']['name']." " .$_SESSION['auth']['famili'] ?></h6>
                            <p class="main-notification-text"><?php if($_SESSION['auth']['role']==1){ echo m('grade.with_number', [':n' => $_SESSION['auth']['paye']]);} else{ echo 'همکار آموزشی';} ?></p>
                        </div>
                        <a class="dropdown-item border-top" href="<?php echo url('Users/profile.php') ?>">
                            <i class="fe fe-user"></i> پروفایل من
                        </a>

                        <a class="dropdown-item" href="?logout">
                            <i class="fe fe-power"></i> خروج از سیستم
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php } ?>