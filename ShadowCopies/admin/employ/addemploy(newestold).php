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
    header('location: ' . url('admin/login.php'));
    die();
}
//Protecting against SESSION HIJACKING
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_viewemploy')) {
    header('location:' . url("admin/index.php"));
    die();
}
if (isset($_POST['esave'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = trim($_POST['ename']);
        $famili = trim($_POST['efamili']);
        $gender = $_POST['egender'];
        $password = $_POST['epassword'];
        if (isset($_POST['emob'])) {
            $mob = $_POST['emob'];
        } else {
            $mob = NULL;
        }
        if (isset($_POST['epic'])) {
            $pic = $_POST['epic'];
        } else {
            $pic = NULL;
        }
        if (isset($_POST['eactivate'])) {
            $activate = 1;
        } else {
            $activate = 0;
        }
        if (isset($_POST['emenu-call'])) {
            $menucall = 1;
        } else {
            $menucall = 0;
        }
        if (isset($_POST['eadd-call'])) {
            $addcall = 1;
        } else {
            $addcall = 0;
        }
        if (isset($_POST['emanage-call'])) {
            $managecall = 1;
        } else {
            $managecall = 0;
        }
        if (isset($_POST['emenu-student'])) {
            $menustudent = 1;
        } else {
            $menustudent = 0;
        }
        if (isset($_POST['eadd-student'])) {
            $addstudent = 1;
        } else {
            $addstudent = 0;
        }
        if (isset($_POST['eedit-student'])) {
            $editstudent = 1;
        } else {
            $editstudent = 0;
        }
        if (isset($_POST['ecourse-student'])) {
            $coursestudent = 1;
        } else {
            $coursestudent = 0;
        }
        if (isset($_POST['ereport-student'])) {
            $reportstudent = 1;
        } else {
            $reportstudent = 0;
        }
        if (isset($_POST['emenu-collague'])) {
            $menucollague = 1;
        } else {
            $menucollague = 0;
        }
        if (isset($_POST['ereport-collague'])) {
            $reportcollague = 1;
        } else {
            $reportcollague = 0;
        }
        if (isset($_POST['emenu-course'])) {
            $menucourse = 1;
        } else {
            $menucourse = 0;
        }
        if (isset($_POST['edel-course'])) {
            $delcourse = 1;
        } else {
            $delcourse = 0;
        }
        if (isset($_POST['ediscount'])) {
            $discount = 1;
        } else {
            $discount = 0;
        }
        if (isset($_POST['emenu-bill'])) {
            $menubill = 1;
        } else {
            $menubill = 0;
        }
        if (isset($_POST['eactbill'])) {
            $actbill = 1;
        } else {
            $actbill = 0;
        }
        if (isset($_POST['enotpay'])) {
            $notpay = 1;
        } else {
            $notpay = 0;
        }
        if (isset($_POST['esuporter'])) {
            $suporter = 1;
        } else {
            $suporter = 0;
        }
        if (isset($_POST['manage_suporter'])) {
            $manage_suporter = 1;
        } else {
            $manage_suporter = 0;
        }
        if (isset($_POST['eactwallet'])) {
            $actwallet = 1;
        } else {
            $actwallet = 0;
        }
        if (isset($_POST['ebill'])) {
            $bill = 1;
        } else {
            $bill = 0;
        }
        if (isset($_POST['ereportbill'])) {
            $reportbill = 1;
        } else {
            $reportbill = 0;
        }
        if (isset($_POST['emenu-wallet'])) {
            $menuwallet = 1;
        } else {
            $menuwallet = 0;
        }
        if (isset($_POST['emenu-allert'])) {
            $menuallert = 1;
        } else {
            $menuallert = 0;
        }
        if (isset($_POST['emenu-support'])) {
            $menusupport = 1;
        } else {
            $menusupport = 0;
        }
        if (isset($_POST['esupport-refer'])) {
            $supportrefer = 1;
        } else {
            $supportrefer = 0;
        }
        if (isset($_POST['esupport-admin'])) {
            $supportadmin = 1;
        } else {
            $supportadmin = 0;
        }
        if (isset($_POST['emenu-doc'])) {
            $menudoc = 1;
        } else {
            $menudoc = 0;
        }
        if (isset($_POST['emenu-employ'])) {
            $menuemploy = 1;
        } else {
            $menuemploy = 0;
        }
        if (isset($_POST['emenu-setting'])) {
            $menusetting = 1;
        } else {
            $menusetting = 0;
        }
        if (isset($_POST['emenu-quiz'])) {
            $menuquiz = 1;
        } else {
            $menuquiz = 0;
        }
        if (isset($_POST['manage-excel'])) {
            $manageexcel = 1;
        } else {
            $manageexcel = 0;
        }
        if (isset($_POST['manage-skill'])) {
            $manage_skill = 1;
        } else {
            $manage_skill = 0;
        }
        if (isset($_POST['manage-reportcall'])) {
            $manage_reportcall = 1;
        } else {
            $manage_reportcall = 0;
        }
        if (isset($_POST['manage-installment'])) {
            $manage_installment = 1;
        } else {
            $manage_installment = 0;
        }
        if (isset($_POST['manage-olympiad'])) {
            $manage_olympiad = 1;
        } else {
            $manage_olympiad = 0;
        }
        if (isset($_POST['administrator'])) {
            $administrator = 1;
        } else {
            $administrator = 0;
        }

        $skyroom = intval($_POST['skyroom']);

        $adminrole = (isset($_POST['eadminrole']) && in_array((int)$_POST['eadminrole'], [1,2,3], true))
            ? (int)$_POST['eadminrole'] : NULL;

        $uptv = 'UPDATE dbadmin SET password=?,fname=?,lname=?,adminactive=?,mobile=?,adminpic=?,gender=?,per_skyroom=?,permision_actfactor=?,permision_notpay=?,permision_actwallet=?,permision_bill=?,permision_financial_export=?,permision_viewcall=?,permision_viewstudent=?,permision_viewcollague=?,permision_reportcollague=?,permision_viewcourse=?,permision_viewbill=?,permision_viewwallet=?,permision_viewalert=?,permision_viewsupport=?,permision_viewdocument=?,permision_viewemploy=?,permision_viewsetting=?,permision_addstudent=?,permision_editstudent=?,permision_addcourse_student=?,permision_reportstudent=?,permision_refer=?,permision_allticket=?,permision_deletcourse=?,permision_discount=?,permision_addcall=?,permision_managecall=?,permision_viewtest=?,permision_suporter=?,permision_manage_suporter=?,excel_permission=?,skill_permission=?,reportcall_permission=?,installment_permission=?,olympiad_permission=?, adminrole=? ,administrator=? WHERE username=?';
        $exup = $conn->prepare($uptv);
        $exup->execute([$password, $name, $famili, $activate, $mob, $pic, $gender, $skyroom, $actbill, $notpay, $actwallet, $bill, $reportbill, $menucall, $menustudent, $menucollague, $reportcollague, $menucourse, $menubill, $menuwallet, $menuallert, $menusupport, $menudoc, $menuemploy, $menusetting, $addstudent, $editstudent, $coursestudent, $reportstudent, $supportrefer, $supportadmin, $delcourse, $discount, $addcall, $managecall, $menuquiz, $suporter,$manage_suporter,$manageexcel,$manage_skill,$manage_reportcall,$manage_installment,$manage_olympiad,$adminrole,$administrator,  $_GET['edit']]);
        $accept = 'بروز رسانی با موفقیت انجام شد';
    }
}
if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (GetAdminUsername($_POST['username'])) {
            $eror = 'نام کاربری از قبل وجود دارد';
        } else {
            if (checkDakheli($_POST['dakheli'])) {
                $name = trim($_POST['name']);
                $famili = trim($_POST['famili']);
                $dakheli = $_POST['dakheli'];
                $gender = $_POST['gender'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                if (isset($_POST['mob'])) {
                    $mob = $_POST['mob'];
                } else {
                    $mob = NULL;
                }
                if (isset($_POST['pic'])) {
                    $pic = $_POST['pic'];
                } else {
                    $pic = NULL;
                }
                if (isset($_POST['activate'])) {
                    $activate = 1;
                } else {
                    $activate = 0;
                }
                if (isset($_POST['menu-call'])) {
                    $menucall = 1;
                } else {
                    $menucall = 0;
                }
                if (isset($_POST['add-call'])) {
                    $addcall = 1;
                } else {
                    $addcall = 0;
                }
                if (isset($_POST['manage-call'])) {
                    $managecall = 1;
                } else {
                    $managecall = 0;
                }
                if (isset($_POST['menu-student'])) {
                    $menustudent = 1;
                } else {
                    $menustudent = 0;
                }
                if (isset($_POST['add-student'])) {
                    $addstudent = 1;
                } else {
                    $addstudent = 0;
                }
                if (isset($_POST['edit-student'])) {
                    $editstudent = 1;
                } else {
                    $editstudent = 0;
                }
                if (isset($_POST['course-student'])) {
                    $coursestudent = 1;
                } else {
                    $coursestudent = 0;
                }
                if (isset($_POST['report-student'])) {
                    $reportstudent = 1;
                } else {
                    $reportstudent = 0;
                }
                if (isset($_POST['menu-collague'])) {
                    $menucollague = 1;
                } else {
                    $menucollague = 0;
                }
                if (isset($_POST['report-collague'])) {
                    $reportcollague = 1;
                } else {
                    $reportcollague = 0;
                }
                if (isset($_POST['menu-course'])) {
                    $menucourse = 1;
                } else {
                    $menucourse = 0;
                }
                if (isset($_POST['del-course'])) {
                    $delcourse = 1;
                } else {
                    $delcourse = 0;
                }
                if (isset($_POST['discount'])) {
                    $discount = 1;
                } else {
                    $discount = 0;
                }
                if (isset($_POST['menu-bill'])) {
                    $menubill = 1;
                } else {
                    $menubill = 0;
                }
                if (isset($_POST['actbill'])) {
                    $actbill = 1;
                } else {
                    $actbill = 0;
                }
                if (isset($_POST['notpay'])) {
                    $notpay = 1;
                } else {
                    $notpay = 0;
                }
                if (isset($_POST['actwallet'])) {
                    $actwallet = 1;
                } else {
                    $actwallet = 0;
                }
                if (isset($_POST['bill'])) {
                    $bill = 1;
                } else {
                    $bill = 0;
                }
                if (isset($_POST['reportbill'])) {
                    $reportbill = 1;
                } else {
                    $reportbill = 0;
                }
                if (isset($_POST['suporter'])) {
                    $suporter = 1;
                } else {
                    $suporter = 0;
                }
                if (isset($_POST['manage_suporter'])) {
                    $manage_suporter = 1;
                } else {
                    $manage_suporter = 0;
                }
                if (isset($_POST['menu-wallet'])) {
                    $menuwallet = 1;
                } else {
                    $menuwallet = 0;
                }
                if (isset($_POST['menu-allert'])) {
                    $menuallert = 1;
                } else {
                    $menuallert = 0;
                }
                if (isset($_POST['menu-support'])) {
                    $menusupport = 1;
                } else {
                    $menusupport = 0;
                }
                if (isset($_POST['support-refer'])) {
                    $supportrefer = 1;
                } else {
                    $supportrefer = 0;
                }
                if (isset($_POST['support-admin'])) {
                    $supportadmin = 1;
                } else {
                    $supportadmin = 0;
                }
                if (isset($_POST['menu-doc'])) {
                    $menudoc = 1;
                } else {
                    $menudoc = 0;
                }
                if (isset($_POST['menu-employ'])) {
                    $menuemploy = 1;
                } else {
                    $menuemploy = 0;
                }
                if (isset($_POST['menu-setting'])) {
                    $menusetting = 1;
                } else {
                    $menusetting = 0;
                }
                if (isset($_POST['menu-quiz'])) {
                    $menuquiz = 1;
                } else {
                    $menuquiz = 0;
                }
                if (isset($_POST['manage-excel'])) {
                    $manageexcel = 1;
                } else {
                    $manageexcel = 0;
                }
                if (isset($_POST['manage-skill'])) {
                    $manage_skill = 1;
                } else {
                    $manage_skill = 0;
                }
                if (isset($_POST['manage-reportcall'])) {
                    $manage_reportcall = 1;
                } else {
                    $manage_reportcall = 0;
                }
                if (isset($_POST['manage-installment'])) {
                    $manage_installment = 1;
                } else {
                    $manage_installment = 0;
                }
                if (isset($_POST['manage-olympiad'])) {
                    $manage_olympiad = 1;
                } else {
                    $manage_olympiad = 0;
                }
                if (isset($_POST['administrator'])) {
                    $administrator = 1;
                } else {
                    $administrator = 0;
                }
                $skyroom = intval($_POST['skyroom']);

                $adminrole = (isset($_POST['adminrole']) && in_array((int)$_POST['adminrole'], [1,2,3], true))
                    ? (int)$_POST['adminrole'] : NULL;

                $addmin = 'INSERT INTO dbadmin (username,password,fname,lname,adminactive,mobile,dakheli,adminpic,gender,per_skyroom,permision_actfactor,permision_notpay,permision_actwallet,permision_bill,permision_financial_export,permision_viewcall,permision_viewstudent,permision_viewcollague,permision_reportcollague,permision_viewcourse,permision_viewbill,permision_viewwallet,permision_viewalert,permision_viewsupport,permision_viewdocument,permision_viewemploy,permision_viewsetting,permision_addstudent,permision_editstudent,permision_addcourse_student,permision_reportstudent,permision_refer,permision_allticket,permision_deletcourse,permision_discount,permision_addcall,permision_managecall,permision_viewtest,permision_suporter,permision_manage_suporter,excel_permission,skill_permission,reportcall_permission,installment_permission,olympiad_permission,adminrole,administrator)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $ins = $conn->prepare($addmin);
                $ins->execute([$username, $password, $name, $famili, $activate, $mob, $dakheli, $pic, $gender, $skyroom, $actbill, $notpay, $actwallet, $bill, $reportbill, $menucall, $menustudent, $menucollague, $reportcollague, $menucourse, $menubill, $menuwallet, $menuallert, $menusupport, $menudoc, $menuemploy, $menusetting, $addstudent, $editstudent, $coursestudent, $reportstudent, $supportrefer, $supportadmin, $delcourse, $discount, $addcall, $managecall, $menuquiz, $suporter,$manage_suporter,$manageexcel,$manage_skill,$manage_reportcall,$manage_installment,$manage_olympiad,$adminrole,$administrator]);
                header('location: listadmin.php');

            } else {
                $eror = 'داخلی وارد شده از قبل وجود دارد';
            }
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
    <title> افزودن کارمند جدید | <?php echo __TITLE ?></title>
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
    <?php if (!isset($_GET['edit'])) { ?>
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="row row-sm">
                        <?php if (isset($eror)) { ?>
                            <div class="alert alert-danger" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                <?php echo $eror ?>
                            </div>
                        <?php } ?>
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <h6 class="main-content-label mb-1">افزودن کارمند جدید </h6>
                                                <p class="text-muted card-sub-title">ایجاد یک کارمند جدید
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام:
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="name"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                    >
                                                </div>
                                            </div>

                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام خانوادگی:
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="famili"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                    >
                                                </div>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                            </div>
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            داخلی
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="dakheli"
                                                           aria-describedby="basic-addon2"
                                                           type="number" required
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row ">
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> جنسیت <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="gender"
                                                        required>

                                                    <option value="خانم">
                                                        خانم
                                                    </option>
                                                    <option value="آقای">
                                                        آقا
                                                    </option>

                                                </select>
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> شماره موبایل </p>
                                                <input class="form-control" name="mob"
                                                       aria-describedby="basic-addon2"
                                                       type="text">
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> آدرس عکس </p>
                                                <input class="form-control" name="pic"
                                                       aria-describedby="basic-addon2"
                                                       type="text"
                                                       placeholder="http://yourdomain.ir/wp-content/uploads/3.jpg">
                                            </div>
                                        </div>
                                        <hr>
                                        <h6 class="main-content-label mb-1">اطلاعات ورود </h6>
                                        <p class="text-muted card-sub-title">اطلاعات لازم برای ورود به حساب کاربری
                                        </p>
                                        <div class="row ">
                                            <div class="col-lg-3">
                                                <p class="mg-b-10 mg-t-10"> نام کاربری <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="username"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required>
                                            </div>
                                            <div class="col-lg-3">
                                                <p class="mg-b-10 mg-t-10"> رمزعبور <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="password"
                                                       aria-describedby="basic-addon2"
                                                       type="password" required>
                                            </div>
                                            <div class="col-lg-3">
                                                <p class="mg-b-10 mg-t-10"> نوع دسترسی اسکای روم <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="skyroom"
                                                        required>

                                                    <option value="1">
                                                        شرکت کننده
                                                    </option>
                                                    <option value="2">
                                                        ارائه دهنده
                                                    </option>
                                                    <option value="3">
                                                        اپراتور
                                                    </option>

                                                </select>
                                            </div>
                                            <div class="col-lg-3">
                                                <p class="mg-b-10 mg-t-10">نقش ادمین</p>
                                                <select class="form-control select2" name="adminrole">
                                                    <option value="">— بدون نقش —</option>
                                                    <option value="1">داخلی</option>
                                                    <option value="2">ادمین میانی</option>
                                                    <option value="3">استاد</option>
                                                </select>
                                            </div>

                                            <div class="col-lg-3 mg-t-50">

                                                <input name="activate" type="checkbox" checked> فعالسازی
                                            </div>
                                        </div>
                                        <hr>
                                        <h6 class="main-content-label mb-1">مجوزات </h6>
                                        <p class="text-muted card-sub-title">دسترسی های این کاربر
                                        <div class="row ">
                                            <div class="col-lg-2">
                                                <input name="menu-call" type="checkbox" checked> منو تماس امن
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="add-call" type="checkbox"> ثبت تماس
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="manage-call" type="checkbox"> مدیریت تماس
                                            </div>

                                            <div class="col-lg-2">
                                                <input name="manage-excel" type="checkbox"> دسترسی لیست excel
                                            </div>

                                            <div class="col-lg-2">
                                                <input name="manage-skill" type="checkbox"> دسترسی مهارتی
                                            </div>

                                            <div class="col-lg-2">
                                                <input name="manage-reportcall" type="checkbox"> دسترسی گزارش تماس و فایل صوتی
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="manage-olympiad" type="checkbox">       دسترسی المپیاد
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="administrator" type="checkbox">      سرپرست
                                            </div>


                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با دانش
                                                آموزان</p>
                                            <div class="col-lg-2">
                                                <input name="menu-student" type="checkbox"> منو دانش آموزان
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="add-student" type="checkbox"> ثبت دانش آموز
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="edit-student" type="checkbox"> ویرایش دانش آموز
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="course-student" type="checkbox"> ثبت پایه دانش آموز
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="report-student" type="checkbox"> گزارش دانش آموزان
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                همکاران</p>
                                            <div class="col-lg-2">
                                                <input name="menu-collague" type="checkbox"> منو همکاران
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="report-collague" type="checkbox"> گزارش همکاران
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با پایه
                                                ها</p>
                                            <div class="col-lg-2">

                                                <input name="menu-course" type="checkbox"> منو پایه ها
                                            </div>
                                            <div class="col-lg-2">

                                                <input name="del-course" type="checkbox"> حذف پایه ها
                                            </div>
                                            <div class="col-lg-3">
                                                <input name="discount" type="checkbox"> ثبت کد تخفیف
                                            </div>
                                            <div class="col-lg-2">

                                                <input name="menu-quiz" type="checkbox"> منو آزمون
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با پنل
                                                پشتیبانی
                                                ها</p>
                                            <div class="col-lg-2">
                                                <input name="suporter" type="checkbox"> پشتیبانی کننده
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="manage_suporter" type="checkbox"> مدیریت پشتیبان ها
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                حسابداری</p>
                                            <div class="col-lg-2">

                                                <input name="menu-bill" type="checkbox"> منو حسابداری
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="actbill" type="checkbox"> تایید فاکتور
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="notpay" type="checkbox"> فاکتور پرداخت نشده
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="actwallet" type="checkbox"> تایید کیف پول
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="bill" type="checkbox"> صورتحساب
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="reportbill" type="checkbox"> گزارش پیشرفته
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="manage-installment" type="checkbox"> کنترل اقساط
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با کیف
                                                پول</p>
                                            <div class="col-lg-2">

                                                <input name="menu-wallet" type="checkbox"> منو کیف پول
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با اعلان
                                                ها</p>
                                            <div class="col-lg-2">

                                                <input name="menu-allert" type="checkbox"> منو اعلان
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با تیکت
                                                ها</p>
                                            <div class="col-lg-2">
                                                <input name="menu-support" type="checkbox"> منو تیکت ها
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="support-refer" type="checkbox"> ارجاع دهنده
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="support-admin" type="checkbox"> مدیریت تیکت
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                مستندات </p>
                                            <div class="col-lg-2">
                                                <input name="menu-doc" type="checkbox"> منو مستندات
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                کارمندان </p>
                                            <div class="col-lg-2">
                                                <input name="menu-employ" type="checkbox"> منو کارمندان
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                تنظیمات </p>
                                            <div class="col-lg-2">
                                                <input name="menu-setting" type="checkbox"> منو تنظیمات
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="text-left mg-10">
                                            <button type="submit" class="btn btn-primary "
                                                    name="save"><i
                                                        class="fa fa-save"></i>
                                                ذخیره تنظیمات و ایجاد کارمند
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } else {
        $user = $_GET['edit'];
        $retadmin = retAdmin($user);
        if ($retadmin == NULL) {
            echo '<script>window.location.href="listadmin.php" </script>';
        }
        ?>

        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="row row-sm">
                        <?php if (isset($accept)) { ?>
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                <?php echo $accept ?>
                            </div>
                        <?php } ?>
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <h6 class="main-content-label mb-1">ویرایش کارمند <span
                                                            class="text-warning">  <?php echo $retadmin->fname . ' ' . $retadmin->lname ?> </span>
                                                </h6>
                                                <p class="text-muted card-sub-title">ویرایش اطلاعات و تغییر سطح دسترسی
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام:
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="ename"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required value="<?php echo $retadmin->fname ?>"
                                                    >
                                                </div>
                                            </div>

                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام خانوادگی:
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="efamili"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required value="<?php echo $retadmin->lname ?>"
                                                    >
                                                </div>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                            </div>
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            داخلی
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="edakheli"
                                                           aria-describedby="basic-addon2"
                                                           type="number" required disabled
                                                           value="<?php echo $retadmin->dakheli ?>"
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row ">
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> جنسیت <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="egender"
                                                        required>

                                                    <option <?php if ($retadmin->gender == 'خانم') {
                                                        echo 'selected';
                                                    } ?> value="خانم">
                                                        خانم
                                                    </option>
                                                    <option <?php if ($retadmin->gender == 'آقای') {
                                                        echo 'selected';
                                                    } ?> value="آقای">
                                                        آقا
                                                    </option>

                                                </select>
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> شماره موبایل </p>
                                                <input class="form-control" name="emob"
                                                       aria-describedby="basic-addon2"
                                                       type="text" value="<?php echo $retadmin->mobile ?>">
                                            </div>
                                            <div class="col-lg-4">
                                                <p class="mg-b-10 mg-t-10"> آدرس عکس </p>
                                                <input class="form-control" name="epic"
                                                       aria-describedby="basic-addon2"
                                                       type="text" value="<?php echo $retadmin->adminpic ?>"
                                                       placeholder="http://yourdomain.ir/wp-content/uploads/3.jpg">
                                            </div>
                                        </div>
                                        <hr>
                                        <h6 class="main-content-label mb-1">اطلاعات ورود </h6>
                                        <p class="text-muted card-sub-title">اطلاعات لازم برای ورود به حساب کاربری
                                        </p>
                                        <div class="row ">
                                            <div class="col-lg-3">
                                                <p class="mg-b-10 mg-t-10"> نام کاربری <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="eusername"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required disabled
                                                       value="<?php echo $retadmin->username ?>">
                                            </div>
                                            <div class="col-lg-3">
                                                <p class="mg-b-10 mg-t-10"> رمزعبور <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="epassword"
                                                       aria-describedby="basic-addon2"
                                                       type="password" required
                                                       value="<?php echo $retadmin->password ?>">
                                            </div>
                                            <div class="col-lg-3">
                                                <p class="mg-b-10 mg-t-10"> نوع دسترسی اسکای روم <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="skyroom"
                                                        required>

                                                    <option value="1" <?php if ($retadmin->per_skyroom == '1') {
                                                        echo 'selected';
                                                    } ?>>
                                                        شرکت کننده
                                                    </option>
                                                    <option value="2" <?php if ($retadmin->per_skyroom == '2') {
                                                        echo 'selected';
                                                    } ?>>
                                                        ارائه دهنده
                                                    </option>
                                                    <option value="3" <?php if ($retadmin->per_skyroom == '3') {
                                                        echo 'selected';
                                                    } ?>>
                                                        اپراتور
                                                    </option>

                                                </select>
                                            </div>

                                            <div class="col-lg-3">
                                                <p class="mg-b-10 mg-t-10">نقش ادمین </p>
                                                <select class="form-control select2" name="eadminrole">
                                                    <option value="" <?php if (empty($retadmin->adminrole)) { echo 'selected'; } ?>>— بدون نقش —</option>
                                                    <option value="1" <?php if ($retadmin->adminrole == '1') { echo 'selected'; } ?>>داخلی</option>
                                                    <option value="2" <?php if ($retadmin->adminrole == '2') { echo 'selected'; } ?>>ادمین میانی</option>
                                                    <option value="3" <?php if ($retadmin->adminrole == '3') { echo 'selected'; } ?>>استاد</option>
                                                </select>
                                            </div>

                                            <div class="col-lg-3 mg-t-50">
                                                <input name="eactivate"
                                                       type="checkbox" <?php if ($retadmin->adminactive == 1) {
                                                    echo 'checked';
                                                } ?> > فعالسازی
                                            </div>
                                        </div>
                                        <hr>
                                        <h6 class="main-content-label mb-1">مجوزات </h6>
                                        <p class="text-muted card-sub-title">دسترسی های این کاربر
                                        <div class="row ">
                                            <div class="col-lg-2">
                                                <input name="emenu-call"
                                                       type="checkbox" <?php if ($retadmin->permision_viewcall == 1) {
                                                    echo 'checked';
                                                } ?> > منو تماس امن
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="eadd-call"
                                                       type="checkbox" <?php if ($retadmin->permision_addcall == 1) {
                                                    echo 'checked';
                                                } ?> > ثبت تماس
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="emanage-call"
                                                       type="checkbox" <?php if ($retadmin->permision_managecall == 1) {
                                                    echo 'checked';
                                                } ?>> مدیریت تماس
                                            </div>

                                            <div class="col-lg-2">
                                                <input name="manage-excel"
                                                       type="checkbox" <?php if ($retadmin->excel_permission == 1) {
                                                    echo 'checked';
                                                } ?>> دسترسی excel
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="manage-skill"
                                                       type="checkbox" <?php if ($retadmin->skill_permission == 1) {
                                                    echo 'checked';
                                                } ?>> دسترسی پایه های مهارتی
                                            </div>

                                            <div class="col-lg-2">
                                                <input name="manage-reportcall"
                                                       type="checkbox" <?php if ($retadmin->reportcall_permission == 1) {
                                                    echo 'checked';
                                                } ?>> دسترسی گزارش تماس و فایل صوتی
                                            </div>

                                            <div class="col-lg-2">
                                                <input name="manage-olympiad"
                                                       type="checkbox" <?php if ($retadmin->olympiad_permission == 1) {
                                                    echo 'checked';
                                                } ?>> دسترسی المپیاد
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="administrator"
                                                       type="checkbox" <?php if ($retadmin->administrator == 1) {
                                                    echo 'checked';
                                                } ?>> سرپرست
                                            </div>


                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با دانش
                                                آموزان</p>
                                            <div class="col-lg-2">
                                                <input name="emenu-student"
                                                       type="checkbox" <?php if ($retadmin->permision_viewstudent == 1) {
                                                    echo 'checked';
                                                } ?>> منو دانش آموزان
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="eadd-student"
                                                       type="checkbox" <?php if ($retadmin->permision_addstudent == 1) {
                                                    echo 'checked';
                                                } ?> > ثبت دانش آموز
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="eedit-student"
                                                       type="checkbox"<?php if ($retadmin->permision_editstudent == 1) {
                                                    echo 'checked';
                                                } ?> > ویرایش دانش آموز
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="ecourse-student"
                                                       type="checkbox"<?php if ($retadmin->permision_addcourse_student == 1) {
                                                    echo 'checked';
                                                } ?> > ثبت پایه دانش آموز
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="ereport-student"
                                                       type="checkbox"<?php if ($retadmin->permision_reportstudent == 1) {
                                                    echo 'checked';
                                                } ?>> گزارش دانش آموزان
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                همکاران</p>
                                            <div class="col-lg-2">
                                                <input name="emenu-collague"
                                                       type="checkbox" <?php if ($retadmin->permision_viewcollague == 1) {
                                                    echo 'checked';
                                                } ?>> منو همکاران
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="ereport-collague"
                                                       type="checkbox" <?php if ($retadmin->permision_reportcollague == 1) {
                                                    echo 'checked';
                                                } ?>> گزارش همکاران
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با پایه
                                                ها</p>
                                            <div class="col-lg-2">

                                                <input name="emenu-course"
                                                       type="checkbox"<?php if ($retadmin->permision_viewcourse == 1) {
                                                    echo 'checked';
                                                } ?>> منو پایه ها
                                            </div>
                                            <div class="col-lg-2">

                                                <input name="edel-course"
                                                       type="checkbox"<?php if ($retadmin->permision_deletcourse == 1) {
                                                    echo 'checked';
                                                } ?>> حذف پایه ها
                                            </div>
                                            <div class="col-lg-3">
                                                <input name="ediscount"
                                                       type="checkbox"<?php if ($retadmin->permision_discount == 1) {
                                                    echo 'checked';
                                                } ?>> ثبت کد تخفیف
                                            </div>
                                            <div class="col-lg-2">

                                                <input name="emenu-quiz"
                                                       type="checkbox"<?php if ($retadmin->permision_viewtest == 1) {
                                                    echo 'checked';
                                                } ?>> منو آزمون
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با پنل
                                                پشتیبانی
                                                ها</p>
                                            <div class="col-lg-2">
                                                <input name="esuporter"
                                                       type="checkbox"<?php if ($retadmin->permision_suporter == 1) {
                                                    echo 'checked';
                                                } ?>> پشتیبانی کننده
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="manage_suporter" type="checkbox" <?php if ($retadmin->permision_manage_suporter == 1) {
                                                    echo 'checked';
                                                } ?>> مدیریت پشتیبان ها
                                            </div>


                                        </div>
                                        <hr>
                                        <div class="row lh-lg">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                حسابداری</p>
                                            <div class="col-lg-2">

                                                <input name="emenu-bill"
                                                       type="checkbox" <?php if ($retadmin->permision_viewbill == 1) {
                                                    echo 'checked';
                                                } ?>> منو حسابداری
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="eactbill"
                                                       type="checkbox" <?php if ($retadmin->permision_actfactor == 1) {
                                                    echo 'checked';
                                                } ?>> تایید فاکتور
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="enotpay"
                                                       type="checkbox" <?php if ($retadmin->permision_notpay == 1) {
                                                    echo 'checked';
                                                } ?>> فاکتور پرداخت نشده
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="eactwallet"
                                                       type="checkbox" <?php if ($retadmin->permision_actwallet == 1) {
                                                    echo 'checked';
                                                } ?>> تایید کیف پول
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="ebill"
                                                       type="checkbox" <?php if ($retadmin->permision_bill == 1) {
                                                    echo 'checked';
                                                } ?>> صورتحساب
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="ereportbill"
                                                       type="checkbox" <?php if ($retadmin->permision_financial_export == 1) {
                                                    echo 'checked';
                                                } ?>> گزارش پیشرفته
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="manage-installment"
                                                       type="checkbox" <?php if ($retadmin->installment_permission == 1) {
                                                    echo 'checked';
                                                } ?>> کنترل اقساط
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با کیف
                                                پول</p>
                                            <div class="col-lg-2">

                                                <input name="emenu-wallet"
                                                       type="checkbox" <?php if ($retadmin->permision_viewwallet == 1) {
                                                    echo 'checked';
                                                } ?>> منو کیف پول
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با اعلان
                                                ها</p>
                                            <div class="col-lg-2">

                                                <input name="emenu-allert"
                                                       type="checkbox" <?php if ($retadmin->permision_viewalert == 1) {
                                                    echo 'checked';
                                                } ?>> منو اعلان
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با تیکت
                                                ها</p>
                                            <div class="col-lg-2">
                                                <input name="emenu-support"
                                                       type="checkbox" <?php if ($retadmin->permision_viewsupport == 1) {
                                                    echo 'checked';
                                                } ?>> منو تیکت ها
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="esupport-refer"
                                                       type="checkbox"<?php if ($retadmin->permision_refer == 1) {
                                                    echo 'checked';
                                                } ?>> ارجاع دهنده
                                            </div>
                                            <div class="col-lg-2">
                                                <input name="esupport-admin"
                                                       type="checkbox"<?php if ($retadmin->permision_allticket == 1) {
                                                    echo 'checked';
                                                } ?>> مدیریت تیکت
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                مستندات </p>
                                            <div class="col-lg-2">
                                                <input name="emenu-doc"
                                                       type="checkbox"<?php if ($retadmin->permision_viewdocument == 1) {
                                                    echo 'checked';
                                                } ?>> منو مستندات
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                کارمندان </p>
                                            <div class="col-lg-2">
                                                <input name="emenu-employ"
                                                       type="checkbox"<?php if ($retadmin->permision_viewemploy == 1) {
                                                    echo 'checked';
                                                } ?>> منو کارمندان
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="row ">
                                            <p class="text-muted card-sub-title">مجوزات و دسترسی های مرتبط با
                                                تنظیمات </p>
                                            <div class="col-lg-2">
                                                <input name="emenu-setting"
                                                       type="checkbox"<?php if ($retadmin->permision_viewsetting == 1) {
                                                    echo 'checked';
                                                } ?>> منو تنظیمات
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="text-left mg-10">
                                            <button type="submit" class="btn btn-warning "
                                                    name="esave"><i
                                                        class="fa fa-save"></i>
                                                ذخیره تنظیمات و ویرایش کارمند
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php } ?>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

