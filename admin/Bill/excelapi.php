<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

$sql = "SELECT codecourse FROM codtbl_course WHERE activecourse = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($courses as $row) {
    $result[] = ['codecourse' => (string)$row['codecourse']];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
