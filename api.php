<?php
header('Content-Type: application/json');
$json = file_get_contents('php://input');
// $data = json_decode($json);
echo $json;
