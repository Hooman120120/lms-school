<?php
// نقش قدیمی سامانه: پشتیبان 1، مدیرمیانی 2، استاد 3، مدیر 0، فروش 6 و معاون‌ها 51 تا 55.
$ranks = [1 , 55 , 54 , 53 , 52 , 51 , 0 , 6];
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
