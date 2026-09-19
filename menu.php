<?php

if (isset($_SESSION['auth']) and $_SESSION['auth']['role'] == 1) {
    // Student sidebar colors from the gender-based theme specification.
    // Other account roles intentionally keep their existing menu colors.
    $studentMenuColor = null;
    $studentGenderStmt = $conn->prepare('SELECT gender FROM dbpersonal WHERE codemeli = ? LIMIT 1');
    $studentGenderStmt->execute([(string)$_SESSION['auth']['user']]);
    $studentGender = trim((string)$studentGenderStmt->fetchColumn());

    if (in_array($studentGender, ['خانم', 'دختر', 'زن', 'female', 'Female', '1'], true)) {
        $studentMenuColor = '#A100DD';
    } elseif (in_array($studentGender, ['آقا', 'پسر', 'مرد', 'male', 'Male', '0'], true)) {
        $studentMenuColor = '#000BDB';
    }

    if ($studentMenuColor !== null) {
        ?>
        <style id="student-gender-sidebar-theme">
            .main-sidebar.main-sidebar-sticky.side-menu,
            .main-sidebar.main-sidebar-sticky.side-menu .sidemenu-logo,
            .main-sidebar.main-sidebar-sticky.side-menu .main-sidebar-body {
                background-color: <?= $studentMenuColor ?> !important;
            }

            .main-sidebar.main-sidebar-sticky.side-menu .main-sidebar-body .nav-item.active > .nav-link::before,
            .main-sidebar.main-sidebar-sticky.side-menu .main-sidebar-body .nav-item.active > .nav-link::after {
                border-left-color: <?= $studentMenuColor ?> !important;
                border-right-color: <?= $studentMenuColor ?> !important;
            }
        </style>
        <?php
    }

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
