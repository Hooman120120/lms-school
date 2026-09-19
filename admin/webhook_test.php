<?php
date_default_timezone_set('Asia/Tehran');

$logFile = __DIR__ . '/webhook_test_log.txt';

function logToFile($text) {
    global $logFile;
    file_put_contents($logFile, $text . "\n", FILE_APPEND | LOCK_EX);
}

// Extract the real URL path without query
$requestUri = explode('?', $_SERVER['REQUEST_URI'])[0];

// Detect REST4 style paths
// Example: /admin/webhook_test.php/NewState
$parts = explode('/', trim($requestUri, '/'));
$eventPath = end($parts); // NewState or Cdr

$timestamp = date('Y-m-d H:i:s');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$rawBody = file_get_contents('php://input');
$parsedJson = json_decode($rawBody, true);

$output = "\n=============================\n";
$output .= "TIME: $timestamp\n";
$output .= "METHOD: $method\n";
$output .= "URI: $requestUri\n";
$output .= "EVENT PATH: $eventPath\n";
$output .= "IP: $ip\n\n";

$output .= "---- RAW BODY ----\n";
$output .= ($rawBody ?: "(empty)") . "\n";

$output .= "\n---- PARSED JSON ----\n";
$output .= print_r($parsedJson, true);

$output .= "\n=============================\n";

logToFile($output);

header('Content-Type: text/plain; charset=utf-8');
echo $output;
