<?php
if (!isset($_SESSION['auth'])) {
    header('location: ../login.php');
    die();
}

$suspension_mode = isset($_SESSION['auth']['suspension_mode'])
    ? (int)$_SESSION['auth']['suspension_mode']
    : 0;
?>
<div class="main-sidebar main-sidebar-sticky side-menu">
    <div class="sidemenu-logo">
        <a class="main-logo" href="<?php echo url('index.php') ?>">

        <img src="<?php echo url('assets/img/logo-white.png') ?>" class="header-brand-img desktop-logo" alt="لوگو">
            <img src="<?php echo url('assets/img/logo-white.png') ?>" class="header-brand-img icon-logo" alt="لوگو">


        </a>
    </div>
    <div class="main-sidebar-body">
        <ul class="nav">
            <li class="nav-header"><span class="nav-label">پنل کاربری</span></li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo url('index.php')?>"><span class="shape1"></span><span class="shape2"></span><i
                            class="ti-home sidemenu-icon"></i><span class="sidemenu-label">داشبورد</span></a>
            </li>
            <?php if ($suspension_mode == 1 or strlen($_SESSION['auth']['user']) == 11) : ?>
            <?php else : ?>
            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-tv sidemenu-icon"></i><span class="sidemenu-label">دوره های من</span><i
                            class="angle fe fe-chevron-left"></i></a>

                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Course/allcourse.php') ?>"> مشاهده لیست دوره ها </a>
                    </li>
 <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Course/allhandhugs.php') ?>"> مشاهده لیست "بغل دستی" ها </a>
                    </li>

                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('') ?>">تکالیف </a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('') ?>">انصراف از دوره </a>
                    </li>

                </ul>
            </li>
            <!--<li class="nav-item">-->
            <!--    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i-->
            <!--                class="fe fe-check-square sidemenu-icon"></i><span class="sidemenu-label">آزمون های من</span><i-->
            <!--                class="angle fe fe-chevron-left"></i></a>-->

            <!--    <ul class="nav-sub">-->
            <!--        <li class="nav-sub-item">-->
            <!--            <a class="nav-sub-link" href="<?php echo url('') ?>"> مشاهده لیست آزمون ها </a>-->
            <!--        </li>-->
            <!--        <li class="nav-sub-item">-->
            <!--            <a class="nav-sub-link" href="<?php echo url('') ?>">کارنامه آزمون</a>-->
            <!--        </li>-->


            <!--    </ul>-->
            <!--</li>-->

            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-shopping-bag sidemenu-icon"></i><span class="sidemenu-label">خرید دوره</span><i
                            class="angle fe fe-chevron-left"></i></a>

                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('course.php') ?>"> ثبت نام در دوره </a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('checkout.php') ?>">سبد خرید</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('pay/finance.php') ?>">صورتحساب </a>
                    </li>

                </ul>
            </li>
            <?php
            if(countTicket($_SESSION['auth']['user'],'پاسخ داده شده') ){
                $count=countTicket($_SESSION['auth']['user'],'پاسخ داده شده');
            }
            if (countUnseenMessages($_SESSION['auth']['user'])) {
                $count = countUnseenMessages($_SESSION['auth']['user']);
            }
            ?>
<?php endif; ?>

            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-phone t sidemenu-icon"></i><span class="sidemenu-label">ارتباط با ما</span><i
                            class="angle fe fe-chevron-left"></i><?php if(isset($count['count_Ticket']) and $count['count_Ticket']!=0) { ?> <span class="badge badge-warning side-badge "><?php echo $count['count_Ticket'] ?> </span><?php } ?><?php if (isset($count['count_Message']) and $count['count_Message'] != 0) { ?>
                        <span class="badge badge-warning side-badge "><?php echo $count['count_Message'] ?>
                        </span><?php } ?>
                </a>
                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Support/AllTicket.php') ?>">  مشاهده همه پیام ها  </a>
                    </li>

                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Support/SendTicket.php') ?>">ارسال پیام به موسسه<?php if(isset($count['count_Ticket']) and $count['count_Ticket']!=0) { ?><span class="badge badge-light "><?php echo  $count['count_Ticket'] ?></span><?php } ?></a>
                    </li>
                </ul>
            </li>
            
            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-life-buoy t sidemenu-icon"></i><span class="sidemenu-label">پشتیبانی</span><i
                            class="angle fe fe-chevron-left"></i><?php if(isset($count['count_Ticket']) and $count['count_Ticket']!=0) { ?> <span class="badge badge-warning side-badge "><?php echo $count['count_Ticket'] ?> </span><?php } ?><?php if (isset($count['count_Message']) and $count['count_Message'] != 0) { ?>
                        <span class="badge badge-warning side-badge "><?php echo $count['count_Message'] ?>
                        </span><?php } ?>
                </a>
                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Support/FreeCall.php') ?>">  تماس رایگان با پشتیبان  </a>
                    </li>
                    
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Support/Message.php') ?>"> گفتگوی متنی با پشتیبان
                            <?php if (isset($count['count_Message']) and $count['count_Message'] != 0) { ?><span
                                    class="badge badge-light "><?php echo $count['count_Message'] ?></span><?php } ?>
                        </a>
                    </li>
                </ul>
            </li>


            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-user t sidemenu-icon"></i><span class="sidemenu-label">حساب کاربری</span><i
                            class="angle fe fe-chevron-left"></i>
                </a>
                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/user.php') ?>"> ویرایش اطلاعات کاربری  </a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/documentation.php') ?>">ارسال مدارک</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/alert.php') ?>">اطلاعیه های ارسالی</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/Wallet.php') ?>">کیف پول</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/ranking.php') ?>">امتیاز دهی</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/competition.php') ?>">مسابقه</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/cooperation.php') ?>">همکاری با موسسه</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/policy.php') ?>">قوانین و مقررات</a>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-slack t sidemenu-icon"></i><span class="sidemenu-label">راهنمای استفاده</span><i
                            class="angle fe fe-chevron-left"></i>
                </a>
                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="#">  نحوه شرکت در کلاس </a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="#">نحوه شرکت در آزمون</a>
                    </li>

                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link " href="<?php echo url('about-us.php') ?>"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-info  sidemenu-icon"></i><span class="sidemenu-label">درباره ما</span>
                </a>

            </li>
        </ul>
        <ul class="nav" style="margin-left: 10px;">
            <li class="text-center text-white">
                <hr>
                <?php $wallet=NULL;
                $wallet=totalWallet($_SESSION['auth']['user']);
                if($wallet == NULL){
                    $wallet=0;
                }
                ?>
                <dl class="dlist-align">
                    <dd> موجودی:</dd>
                    <dd class="text-right  mr-auto"> <?php  echo number_format($wallet)?> تومان</dd>
                </dl>
                <div class="dropdown dropup">

                    <button aria-expanded="false" aria-haspopup="true" class="btn ripple btn-block  btn-warning dropdown-toggle" data-bs-toggle="dropdown" type="button" style="font-size: 16px;"><i class="ti-wallet"></i> افزایش موجودی </button>
                                        </a>
                    <div class="dropdown-menu tx-13">

                        <a class="dropdown-item" href="--><?php //echo url('wallet/deposit.php') ?><!-- ">از طریق درگاه پرداخت</a>
                        <a class="dropdown-item" href="<?php echo url('wallet/Cardpal.php') ?>"> از طریق کارت به کارت</a>
                        <a class="dropdown-item" href="<?php echo url('wallet/transactions.php') ?>">تراکنش های کیف پول </a>
                        <div class="dropdown-divider"></div><a class="dropdown-item" href="#">درخواست بازگشت وجه</a>
                    </div>
                </div>
            </li>
        </ul>




        </div>
</div>