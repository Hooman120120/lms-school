<?php
//نقش کاربری | 1 پشتیبان,2مدیرمیانی,3استاد, مدیریت0 ,کارشناس فروش6, معاونت اجرایی 51, معاونت آموزشی52, معاونت فروش53 ,معاونت فنی54 ,
$ranks = [1 , 54 , 53 , 52 , 51 , 0 , 6];
    if (in_array($_SESSION['admin']['adminrole'] , $ranks)  ){
        include_once "admin-menu.php";
    }
    elseif ($_SESSION['admin']['adminrole'] == 3){
        include_once "ostad-menu.php";
    }
    elseif ($_SESSION['admin']['adminrole'] == 2){
        include_once "admin-menu.php";
    }
    else {
        echo '<div class="d-flex justify-content-center align-content-center fs-4">Not found contact the administrator.</div>';
        exit();
    }
?>
