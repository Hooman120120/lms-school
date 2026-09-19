<?php
if (!isset($_SESSION['auth'])) {
    header('location: ../login.php');
    die();
}
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
            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="si si-graduation sidemenu-icon"></i><span class="sidemenu-label">دانش آموزان من</span><i
                            class="angle fe fe-chevron-left"></i></a>

                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Collague/all-student.php') ?>">  لیست دانش آموزها </a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Collague/addstudent.php') ?>">ثبت دانش آموز</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Collague/activate-student.php') ?>">فعالسازی دانش آموز</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Collague/sales-course.php') ?>">خرید دوره برای دانش آموز</a>
                    </li>


                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-percent sidemenu-icon"></i><span class="sidemenu-label">صورتحساب </span><i
                            class="angle fe fe-chevron-left"></i></a>

                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Collague/activate-financial.php') ?>">   موفق </a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Collague/away-financial.php') ?>"> پرداخت نشده</a>
                    </li>




                </ul>
            </li>



            <?php
            if(countTicket($_SESSION['auth']['user'],'پاسخ داده شده') ){
                $count=countTicket($_SESSION['auth']['user'],'پاسخ داده شده');
            }

            ?>

            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-life-buoy t sidemenu-icon"></i><span class="sidemenu-label">پشتیبانی</span><i
                            class="angle fe fe-chevron-left"></i><?php if($count['count_Ticket']!=0) { ?> <span class="badge badge-warning side-badge "><?php echo $count['count_Ticket'] ?> </span><?php } ?>
                </a>
                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Support/AllTicket.php') ?>">  مشاهده همه پیام ها  </a>
                    </li>

                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Support/SendTicket.php') ?>">ارسال پیام به پشتیبان <?php if($count['count_Ticket']!=0) { ?><span class="badge badge-light "><?php echo  $count['count_Ticket'] ?></span><?php } ?></a>
                    </li>

                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Support/FreeCall.php') ?>">  تماس رایگان با پشتیبان  </a>
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
                        <a class="nav-sub-link" href="<?php echo url('Profile/editinformation-collague.php') ?>"> ویرایش اطلاعات کاربری  </a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('Profile/documentation.php') ?>">ارسال مدارک</a>
                    </li>

                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('') ?>">کیف پول</a>
                    </li>



                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('') ?>">قوانین و مقررات</a>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link " href="<?php echo url('guidance.php') ?>"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-slack  sidemenu-icon"></i><span class="sidemenu-label">راهنمای ثبت نام</span>
                </a>

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
                    <!--                    </a>-->
                    <div class="dropdown-menu tx-13">

                        <a class="dropdown-item" href="<?php echo url('wallet/deposit.php') ?> ">از طریق درگاه پرداخت</a>
                        <a class="dropdown-item" href="<?php echo url('wallet/Cardpal.php') ?>"> از طریق کارت به کارت</a>
                        <a class="dropdown-item" href="<?php echo url('wallet/shebapay.php') ?>"> از طریق واریز به شبا</a>
                        <a class="dropdown-item" href="<?php echo url('wallet/transactions.php') ?>">تراکنش های کیف پول </a>
<!--                        <div class="dropdown-divider"></div><a class="dropdown-item" href="#">درخواست بازگشت وجه</a>-->
                    </div>
                </div>
            </li>
        </ul>




    </div>
</div>