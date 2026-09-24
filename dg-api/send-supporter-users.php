<?php

require_once 'api_bootstrap.php'; 

header("Access-Control-Allow-Methods: GET");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "success" => false, 
        "error" => "Only GET requests are allowed."
    ]);
    exit;
}

if (!isset($_GET['op']) || empty(trim($_GET['op']))) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Operator username (op) parameter is required."
    ]);
    exit;
}

$operator = trim($_GET['op']);

try {
    $sql = "
SELECT 
    c.username AS student_username,
    su.typesupport,
    p.paye,
    GROUP_CONCAT(DISTINCT cc.coursename SEPARATOR ', ') AS courses

FROM dbcourse c

INNER JOIN dbfactor f 
    ON c.numfactor = f.idfactor

INNER JOIN codtbl_course cc 
    ON c.codecourse = cc.codecourse

LEFT JOIN dbpersonal p 
    ON c.username = p.codemeli

LEFT JOIN suport_user su 
    ON c.username = su.username

WHERE 
    c.suporter = :operator
    AND c.activityuser = 1
    AND cc.activecourse = 1
    AND f.status = 'موفق'

GROUP BY 
    c.username, su.typesupport, p.paye

    ";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':operator', $operator, PDO::PARAM_STR);
    $stmt->execute();
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "operator" => $operator,
        "count" => count($students),
        "data" => $students
    ]);

} catch (PDOException $e) {

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "code" => $e->getCode()
    ]);
}
?>
