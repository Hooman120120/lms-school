<?php
require_once 'config.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: " . ALLOWED_ORIGIN);
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

$apiKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';

// An unset key must lock the API, not open it to requests that send none.
if (SECRET_API_KEY === '' || !hash_equals(SECRET_API_KEY, (string) $apiKey)) {
    http_response_code(401); 
    echo json_encode([
        'success' => false,
        'message' => 'Authentication Failed: Invalid or missing API Key.'
    ]);
    exit();
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed.'
    ]);
    exit();
}
?>
