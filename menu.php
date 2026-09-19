<?php

if (isset($_SESSION['auth']) and $_SESSION['auth']['role'] == 1) {
    $sql = 'SELECT * FROM dbcourse WHERE username=? and activityuser=? ';
    $query = $conn->prepare($sql);
    $query->execute([$_SESSION['auth']['user'], 1]);
    $resy = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        $id = 0;
        foreach ($resy as $tri) {
            if (iscourseActivate($tri->codecourse)) {
                $id++;
            }
        }
        if ($id > 0) {
            include_once 'inc/menu-student.php';
        } else {
            include_once 'inc/menu-bulkt.php';
            $courseregister = 1;
        }
    } else {
        include_once 'inc/menu-bulkt.php';
        $courseregister = 1;
    }
} else {
    if (isset($_SESSION['auth'])) {
        include_once 'inc/menu-collague.php';
    }
}
