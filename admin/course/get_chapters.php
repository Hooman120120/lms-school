<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if ($_GET['lesson_id']) {
    $sql = 'SELECT * FROM subchapters WHERE lesson_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([intval($_GET['lesson_id'])]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($chapters);
}


