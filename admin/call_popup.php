<?php
// --- 0. SETUP & DEBUG FUNCTION ---
include_once '../dbconfig.php';
date_default_timezone_set('Asia/Tehran');

// Tell MySQL to use Iran Standard Time (+03:30) for this session
$conn->exec("SET time_zone = '+03:30';");

// -------------------------------
// Debug settings
// -------------------------------
define('DEBUG_VIEW_TOKEN', '12345');
define('RAW_WEBHOOK_LOG_FILE', __DIR__ . '/webhook_raw_log.jsonl');

// A simple function to write to a log file with timestamps.
function writeToLog($message) {
    $logFile = __DIR__ . '/call_debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] " . $message . "\n", FILE_APPEND);
}
// Clean remote number: if it starts with '80', remove the first '8'
function cleanRemoteNumber($number) {
    if (empty($number)) return '';
    if (preg_match('/^80\d+$/', $number)) {
        return substr($number, 1);  // removes the leading '8'
    }
    return $number;
}

// Store raw webhook requests as JSON Lines, one record per line
function writeRawWebhookLog($rawInput) {
    $entry = [
        'timestamp'      => date('Y-m-d H:i:s'),
        'ip'             => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method'         => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'request_uri'    => $_SERVER['REQUEST_URI'] ?? '',
        'content_type'   => $_SERVER['CONTENT_TYPE'] ?? '',
        'raw_body'       => $rawInput
    ];

    file_put_contents(
        RAW_WEBHOOK_LOG_FILE,
        json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

// Read last N lines from log file efficiently enough for moderate logs
function readLastLines($file, $lines = 50) {
    if (!file_exists($file)) {
        return [];
    }

    $data = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($data === false) {
        return [];
    }

    return array_slice($data, -$lines);
}

// =====================================================================
// --- 1. ROUTING & API LOGIC ---
// =====================================================================

$action = 'unknown';

// Check for the JavaScript polling request (?action=poll)
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    $action = 'poll';
    try {
        $cleanStmt = $conn->prepare("DELETE FROM active_calls WHERE created_at < NOW() - INTERVAL 2 HOUR");
        $cleanStmt->execute();
    } catch (PDOException $e) {
    }
}
// Check status action
elseif (isset($_GET['action']) && $_GET['action'] === 'check_status' && isset($_GET['cuid'])) {
    $action = 'check_status';
}
// View raw webhook logs in browser
elseif (isset($_GET['action']) && $_GET['action'] === 'view_raw') {
    $action = 'view_raw';
}
// Check for Simotel's "Rest" style webhook URL (e.g., /call_popup.php/NewState)
else if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (strpos($_SERVER['REQUEST_URI'], '/NewState') !== false || strpos($_SERVER['REQUEST_URI'], '/Cdr') !== false)
) {
    $action = 'webhook';
}

// =============================
// VIEW RAW WEBHOOKS IN BROWSER
// =============================
if ($action === 'view_raw') {
    $token = $_GET['token'] ?? '';
    if ($token !== DEBUG_VIEW_TOKEN) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');

    $lines = isset($_GET['lines']) ? max(1, min(500, (int)$_GET['lines'])) : 50;
    $entries = readLastLines(RAW_WEBHOOK_LOG_FILE, $lines);

    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Webhook Raw Log</title>";
    echo "<style>
            body { font-family: monospace; background:#111; color:#eee; padding:20px; }
            h1 { color:#7dd3fc; }
            .entry { border:1px solid #333; padding:12px; margin-bottom:12px; background:#1a1a1a; }
            .meta { color:#93c5fd; margin-bottom:8px; }
            pre { white-space: pre-wrap; word-break: break-word; background:#000; padding:10px; border-radius:6px; color:#d1fae5; }
            a { color:#facc15; }
          </style>";
    echo "</head><body>";
    echo "<h1>Recent Raw Webhook Payloads</h1>";
    echo "<p>Showing last {$lines} entries</p>";

    if (empty($entries)) {
        echo "<p>No webhook data logged yet.</p>";
    } else {
        foreach ($entries as $line) {
            $row = json_decode($line, true);

            if (!$row) {
                echo "<div class='entry'><div class='meta'>Malformed log line</div><pre>" . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "</pre></div>";
                continue;
            }

            echo "<div class='entry'>";
            echo "<div class='meta'>"
                . "Time: " . htmlspecialchars($row['timestamp'] ?? '', ENT_QUOTES, 'UTF-8')
                . " | IP: " . htmlspecialchars($row['ip'] ?? '', ENT_QUOTES, 'UTF-8')
                . " | Method: " . htmlspecialchars($row['method'] ?? '', ENT_QUOTES, 'UTF-8')
                . " | URI: " . htmlspecialchars($row['request_uri'] ?? '', ENT_QUOTES, 'UTF-8')
                . "</div>";

            echo "<pre>" . htmlspecialchars($row['raw_body'] ?? '', ENT_QUOTES, 'UTF-8') . "</pre>";
            echo "</div>";
        }
    }

    echo "</body></html>";
    exit;
}

// =============================
// B. WEBHOOK ENDPOINT (Simotel talks to this)
// =============================
if ($action === 'webhook') {
    header('Content-Type: application/json');

    $rawInput = file_get_contents('php://input');
    writeRawWebhookLog($rawInput);

    $payload = json_decode($rawInput, true);

    if (!$payload || !isset($payload['event_name'])) {
        writeToLog("Invalid JSON or missing event_name. Raw: " . $rawInput);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON.']);
        exit;
    }

    $eventName = $payload['event_name'];

    try {
        switch ($eventName) {
            case 'NewState':
                $state = $payload['state'] ?? 'N/A';
                $cuid  = $payload['cuid'] ?? null;

                if (!$cuid) {
                    writeToLog("NewState skipped: missing cuid. Payload: " . $rawInput);
                    break;
                }

                // 1. INBOUND CALL POPUP (Ringing)
                if ($state === 'Ringing') {
                    $operator_exten = $payload['exten'] ?? null;
                    $rawCaller      = $payload['participant'] ?? $payload['src'] ?? null;
                    $caller_number  = cleanRemoteNumber($rawCaller);

                    $stmt = $conn->prepare(
                        "INSERT INTO active_calls (cuid, exten, caller_number, status)
                         VALUES (?, ?, ?, 'ringing')
                         ON DUPLICATE KEY UPDATE status='ringing', caller_number = ?, exten = ?"
                    );
                    $stmt->execute([$cuid, $operator_exten, $caller_number, $caller_number, $operator_exten]);
                }

                // 2. OUTBOUND CALL POPUP (Dialing out)
                elseif ($state === 'InUse' && isset($payload['dialing']) && $payload['dialing'] === 'yes') {
                    $operator_exten = $payload['exten'] ?? null;
                    $rawDialed      = $payload['participant'] ?? $payload['dst'] ?? null;
                    $dialed_number  = cleanRemoteNumber($rawDialed);

                    $stmt = $conn->prepare(
                        "INSERT INTO active_calls (cuid, exten, caller_number, status)
                         VALUES (?, ?, ?, 'dialing')
                         ON DUPLICATE KEY UPDATE status='dialing', caller_number = ?, exten = ?"
                    );
                    $stmt->execute([$cuid, $operator_exten, $dialed_number, $dialed_number, $operator_exten]);
                }

                // 3. FORCE CLEANUP ON HANG UP (Handles cases where a CDR payload is omitted)
                elseif ($state === 'Idle') {
                    $stmtDel = $conn->prepare("DELETE FROM active_calls WHERE cuid = ?");
                    $stmtDel->execute([$cuid]);
                }
                break;

            case 'Cdr':
                if (isset($payload['cuid']) && $payload['cuid'] !== null && $payload['cuid'] !== '') {
                    try {
                        $cuid = $payload['cuid'];

                        // Map direction by checking BOTH 'direction' and 'type'
                        $rawDir = strtolower($payload['direction'] ?? $payload['type'] ?? 'in');
                        $direction = (strpos($rawDir, 'out') !== false) ? 'out' : 'in';

                        $src = $payload['src'] ?? '';
                        $dst = $payload['dst'] ?? '';

                        // Determine extension and remote number based on direction
                        if ($direction === 'in') {
                            $remote_number = cleanRemoteNumber($src);
                            $exten = (is_numeric($dst) && strlen($dst) <= 4) ? (int)$dst : 0;
                        } else {
                            $remote_number = cleanRemoteNumber($dst);
                            $exten = (is_numeric($src) && strlen($src) <= 4) ? (int)$src : 0;
                        }

                        $codemeli = null; // Default to null if not found

                        if (!empty($remote_number)) {
                            try {
                                $stmtLookup = $conn->prepare(
                                    "SELECT codemeli FROM dbpersonal
                                     WHERE ? IN (tel1, tel2, tel3, tel_home)"
                                );
                                $stmtLookup->execute([$remote_number]);
                                $people = $stmtLookup->fetchAll(PDO::FETCH_ASSOC);
                                $match_count = count($people);

                                if ($match_count === 1) {
                                    $codemeli = $people[0]['codemeli'];
                                } elseif ($match_count > 1) {
                                    $codemeli = '0000000000';
                                }
                            } catch (PDOException $e) {
                                writeToLog("Codemeli Lookup Error: " . $e->getMessage());
                            }
                        }

                        // Calculate talk seconds
                        if (isset($payload['billsec'])) {
                            $talk_seconds = (int)$payload['billsec'];
                        } else {
                            $duration = isset($payload['duration']) ? (int)$payload['duration'] : 0;
                            $wait = isset($payload['wait']) ? (int)$payload['wait'] : 0;
                            $talk_seconds = max(0, $duration - $wait);
                        }

                        // Check if answered
                        $disposition = strtoupper($payload['disposition'] ?? '');
                        $answered = ($disposition === 'ANSWERED') ? 1 : 0;

                        // 2. Insert into the permanent `calls` table
                        $stmtInsert = $conn->prepare(
                            "INSERT INTO calls (cuid, direction, exten, remote_number, talk_seconds, answered, codemeli)
                             VALUES (?, ?, ?, ?, ?, ?, ?)"
                        );
                        $stmtInsert->execute([$cuid, $direction, $exten, $remote_number, $talk_seconds, $answered, $codemeli]);

                    } catch (PDOException $e) {
                        writeToLog("CDR Insert Error: " . $e->getMessage() . " | Payload: " . $rawInput);
                    }

                    // 3. Remove from temporary `active_calls` table to clear the popup
                    try {
                        $stmtDel = $conn->prepare("DELETE FROM active_calls WHERE cuid = ?");
                        $stmtDel->execute([$payload['cuid']]);
                    } catch (PDOException $e) {
                        writeToLog("Active Calls Delete Error: " . $e->getMessage());
                    }
                } else {
                    writeToLog("Cdr skipped: missing cuid. Payload: " . $rawInput);
                }
                break;

            default:
                writeToLog("Unhandled event_name: " . $eventName . " | Payload: " . $rawInput);
                break;
        }
    } catch (PDOException $e) {
        writeToLog("WEBHOOK DATABASE ERROR: " . $e->getMessage() . " | Payload: " . $rawInput);
        http_response_code(500);
        echo json_encode(['status' => 'db_error']);
        exit;
    }

    echo json_encode(['status' => 'received']);
    exit;
}

if ($action === 'poll') {
    header('Content-Type: application/json');

    $operator_exten = $_GET['exten'] ?? null;
    if (!$operator_exten) {
        echo json_encode(['has_call' => false, 'error' => 'Extension not provided.']);
        exit;
    }

    try {
        // CHANGED: Now looks for active records with EITHER 'ringing' (inbound) or 'dialing' (outbound) statuses
        $stmt = $conn->prepare("SELECT cuid, caller_number FROM active_calls WHERE exten = ? AND status IN ('ringing', 'dialing') LIMIT 1");
        $stmt->execute([$operator_exten]);
        $call = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($call) {
            echo json_encode([
                'has_call' => true,
                'cuid'     => $call['cuid'],
                'caller'   => $call['caller_number']
            ]);
        } else {
            echo json_encode(['has_call' => false]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['has_call' => false, 'error' => 'Database error.']);
    }
    exit;
}

// =============================
// C. CALL STATUS CHECK
// =============================
if ($action === 'check_status') {
    header('Content-Type: application/json');
    $cuid = $_GET['cuid'];
    $response = ['call_ended' => false];

    // CHANGED: Verify status using active_calls. If the live tracking row is removed by an 'Idle' event or 'Cdr' entry, the dashboard interface knows instantly to clear the popup.
    $stmt = $conn->prepare("SELECT 1 FROM active_calls WHERE cuid = ? LIMIT 1");
    $stmt->execute([$cuid]);

    if (!$stmt->fetch()) {
        $response['call_ended'] = true;
    }

    echo json_encode($response);
    exit;
}

// If accessed directly in a browser without known action
echo "Engine is running. Awaiting webhooks or polls.";
?>