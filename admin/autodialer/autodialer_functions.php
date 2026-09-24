<?php
function autodialerConfig(): array
{
    return [
        'base_url' => 'http://'. SIMOTEL_SERVER .'//api/v4/autodialer',

        'headers' => [
            'X-APIKEY: '.SIMOTEL_API_KEY,
            'Authorization: Basic '.SIMOTEL_BASIC_AUTH,
            'Content-Type: application/json',
            'Accept: application/json',
        ],

        // Common cURL options used by all calls
        'curl' => [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 25, // don't use 0 (infinite)
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ],
    ];
}
//trunk id's
//public '_id' => string '6989c3de35da8c491f3527de' (length=24)
//public 'title' => string 'class_trunk' (length=11)
//public '_id' => string '650aea35e4eb3d703d068885' (length=24)
//public 'title' => string 'OTP' (length=3)
//public '_id' => string '6989c3de35da8c491f3527de' (length=24)
//public 'title' => string 'class_trunk' (length=11)

function autodialerLog(string $actor, string $action, string $entityType): bool
{
    global $conn;

    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    $actor = trim($actor);
    $action = trim($action);
    $entityType = trim($entityType);

    if ($actor === '' || $action === '' || $entityType === '') {
        return false;
    }

    // Fit column sizes safely
    if (mb_strlen($actor) > 190) $actor = mb_substr($actor, 0, 190);
    if (mb_strlen($action) > 150) $action = mb_substr($action, 0, 150);
    if (mb_strlen($entityType) > 50) $entityType = mb_substr($entityType, 0, 50);

    $sql = "
        INSERT INTO autodialer_log (actor, action, entity_type, created_at)
        VALUES (:actor, :action, :entity_type, NOW())
    ";

    $st = $conn->prepare($sql);

    return $st->execute([
        ':actor'       => $actor,
        ':action'      => $action,
        ':entity_type' => $entityType,
    ]);
}


function autodialerContactsSearch($start = 0, $count = 20)
{
    $cfg = autodialerConfig();

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = $cfg['base_url'] . "/contacts/search";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = '{
        "alike":"true",
        "conditions":{"name":"","number":"","description":""},
        "pagination":{"start":' . (int)$start . ',"count":' . (int)$count . ',"sorting":{}}
    }';
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    curl_close($curl);

    $obj = json_decode((string)$response);

    // IMPORTANT: adjust this path if needed (see note below)
    $rows = [];
    if (isset($obj->data) && isset($obj->data->data) && is_array($obj->data->data)) {
        $rows = $obj->data->data;
    } elseif (isset($obj->data) && is_array($obj->data)) {
        $rows = $obj->data;
    }

    return $rows; // array of objects
}

function autodialerContactsAdd(string $name, string $number, string $description = '')
{
    $cfg = autodialerConfig();

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = $cfg['base_url'] . "/contacts/add";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = "{\r\n    \"name\":\"" . addslashes($name) . "\",\r\n    \"number\":\"" . addslashes($number) . "\",\r\n    \"description\":\"" . addslashes($description) . "\"\r\n}";
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);

    if (!curl_errno($curl)) {
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        echo 'response code:'.$httpcode, '<br/>';
    }

    curl_close($curl);
    echo $response;
}

function autodialerContactsRemove(string $id)
{
    $cfg = autodialerConfig();

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = $cfg['base_url'] . "/contacts/remove";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = "{\"_id\":\"" . addslashes($id) . "\"}";
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);

    if (!curl_errno($curl)) {
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        echo 'response code:'.$httpcode, '<br/>';
    }

    curl_close($curl);
    echo $response;
}

function autodialerContactsUpdate(string $id, string $name, string $number, string $description = '')
{
    $cfg = autodialerConfig();

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = $cfg['base_url'] . "/contacts/update";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] =
        "{\r\n" .
        "    \"_id\":\"" . addslashes($id) . "\",\r\n" .
        "    \"name\":\"" . addslashes($name) . "\",\r\n" .
        "    \"number\":\"" . addslashes($number) . "\",\r\n" .
        "    \"description\":\"" . addslashes($description) . "\"\r\n" .
        "}";
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);

    if (!curl_errno($curl)) {
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        echo 'response code:'.$httpcode, '<br/>';
    }

    curl_close($curl);
    echo $response;
}

function autodialerBlacklistList(string $q = ''): array
{
    global $conn;
    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    $q = trim($q);

    if ($q !== '') {
        $sql = "
            SELECT id, phone, description, created_by, created_at
            FROM autodialer_blacklist
            WHERE phone LIKE :q OR description LIKE :q OR created_by LIKE :q
            ORDER BY id DESC
            LIMIT 500
        ";
        $st = $conn->prepare($sql);
        $st->execute([':q' => '%' . $q . '%']);
    } else {
        $sql = "
            SELECT id, phone, description, created_by, created_at
            FROM autodialer_blacklist
            ORDER BY id DESC
            LIMIT 500
        ";
        $st = $conn->prepare($sql);
        $st->execute();
    }

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function autodialerBlacklistExists(string $phone, ?int $excludeId = null): bool
{
    global $conn;
    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    $phone = trim($phone);

    if ($excludeId) {
        $sql = "SELECT COUNT(*) FROM autodialer_blacklist WHERE phone = :phone AND id <> :id";
        $st = $conn->prepare($sql);
        $st->execute([':phone' => $phone, ':id' => $excludeId]);
    } else {
        $sql = "SELECT COUNT(*) FROM autodialer_blacklist WHERE phone = :phone";
        $st = $conn->prepare($sql);
        $st->execute([':phone' => $phone]);
    }

    return ((int)$st->fetchColumn()) > 0;
}

function autodialerBlacklistAdd(string $phone, string $description, string $createdBy): bool
{
    global $conn;
    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    $sql = "
        INSERT INTO autodialer_blacklist (phone, description, created_by, created_at)
        VALUES (:phone, :description, :created_by, NOW())
    ";
    $st = $conn->prepare($sql);

    return $st->execute([
        ':phone' => $phone,
        ':description' => $description,
        ':created_by' => $createdBy,
    ]);
}

function autodialerBlacklistUpdate(int $id, string $phone, string $description): bool
{
    global $conn;
    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    $sql = "
        UPDATE autodialer_blacklist
        SET phone = :phone, description = :description
        WHERE id = :id
        LIMIT 1
    ";
    $st = $conn->prepare($sql);

    return $st->execute([
        ':phone' => $phone,
        ':description' => $description,
        ':id' => $id,
    ]);
}

function autodialerBlacklistDelete(int $id): bool
{
    global $conn;
    if (!$conn instanceof PDO) {
        throw new RuntimeException('Global $conn is not a valid PDO instance.');
    }

    $sql = "DELETE FROM autodialer_blacklist WHERE id = :id LIMIT 1";
    $st = $conn->prepare($sql);

    return $st->execute([':id' => $id]);
}

function autodialerGroupsSearch(int $start = 0, int $count = 200, int $daysBack = 7): array
{
    $cfg = autodialerConfig();

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = rtrim($cfg['base_url'], '/') . "/groups/search";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = '{
        "alike":"true",
        "conditions":{"name":"","description":""},
        "pagination":{"start":' . (int)$start . ',"count":' . (int)$count . ',"sorting":{}}
    }';
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    curl_close($curl);

    $obj = json_decode((string)$response);

    $rows = [];
    if (isset($obj->data) && isset($obj->data->data) && is_array($obj->data->data)) {
        $rows = $obj->data->data;
    } else {
        return [];
    }

    // ---- Filter: only groups created in last N days (Jalali date-only, ignore time) ----
    // today in Jalali (English digits for parsing)
    $todayJ = jdate('Y-m-d', '', '', 'Asia/Tehran', 'en'); // e.g. 1404-12-02
    $parts = explode('-', $todayJ);
    if (count($parts) !== 3) return $rows; // fallback: can't compute

    $jyToday = (int)$parts[0];
    $jmToday = (int)$parts[1];
    $jdToday = (int)$parts[2];

    // midnight timestamps (Jalali -> unix) using jmktime from jdf.php
    $todayTs  = (int) jmktime(0, 0, 0, $jmToday, $jdToday, $jyToday, '', 'Asia/Tehran');
    $cutoffTs = $todayTs - ((int)$daysBack * 86400);

    $out = [];
    foreach ($rows as $g) {
        // try common date fields (adjust if your API uses a different one)
        $dateStr = (string)($g->date ?? $g->created_at ?? $g->createdAt ?? '');
        if ($dateStr === '') continue;

        // accept "1404-12-02" OR "1404-12-02 12:06:51" (ignore time)
        $dateStr = str_replace('/', '-', trim($dateStr));
        $datePart = explode(' ', $dateStr, 2)[0] ?? '';
        $d = explode('-', $datePart);
        if (count($d) !== 3) continue;

        $jy = (int)$d[0];
        $jm = (int)$d[1];
        $jd = (int)$d[2];

        if ($jy < 1300 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) continue;

        $ts = (int) jmktime(0, 0, 0, $jm, $jd, $jy, '', 'Asia/Tehran');
        if ($ts >= $cutoffTs && $ts <= $todayTs) {
            $out[] = $g;
        }
    }

    return $out;
}

function autodialerGroupsAddNumbers(string $name, array $numbers, string $description = '')
{
    $cfg = autodialerConfig();

    $payload = [
        "name" => $name,
        "description" => $description,
        "numbers" => array_values($numbers),
    ];

    $curl = curl_init();
    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = $cfg['base_url'] . "/groups/add";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);

    curl_close($curl);

    return [
        'httpcode' => (int)$httpcode,
        'error' => $err ?: null,
        'raw' => $response,
        'json' => json_decode((string)$response, true),
    ];
}

function autodialerGroupsRemove(string $id): array
{
    $cfg = autodialerConfig();

    $id = trim($id);
    if ($id === '') {
        return [
            'httpcode' => 0,
            'error' => 'Missing _id',
            'raw' => null,
            'json' => null,
        ];
    }

    $payload = ["_id" => $id];

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = $cfg['base_url'] . "/groups/remove";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);

    curl_close($curl);

    return [
        'httpcode' => (int)$httpcode,
        'error' => $err ?: null,
        'raw' => $response,
        'json' => json_decode((string)$response, true),
    ];
}

function autodialerAnnouncementsSearch(string $name = '')
{
    $cfg = autodialerConfig();

    $payload = [
        "alike" => "true",
        "conditions" => [
            "name" => (string)$name
        ]
    ];

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = $cfg['base_url'] . "/announcements/search";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);

    curl_close($curl);

    $obj = json_decode((string)$response);

    // Similar shapes seen in your other endpoints
    if (isset($obj->data) && isset($obj->data->data) && is_array($obj->data->data)) {
        return $obj->data->data;
    }
    if (isset($obj->data) && is_array($obj->data)) {
        return $obj->data;
    }
    if (is_array($obj)) {
        return $obj;
    }

    // If you want debugging later, return an array with meta.
    return [];
}

function autodialerAnnouncementsAdd(string $name, string $filename, string $description = ''): array
{
    $cfg = autodialerConfig();

    $name = trim($name);
    $filename = trim($filename);
    $description = trim($description);

    if ($name === '' || $filename === '') {
        return [
            'ok' => false,
            'httpcode' => 0,
            'error' => 'Missing required fields: name/filename',
            'raw' => null,
            'json' => null,
        ];
    }

    $payload = [
        "name" => $name,
        "filename" => $filename,
        "description" => $description,
    ];

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = rtrim($cfg['base_url'], '/') . "/announcements/add";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);

    curl_close($curl);

    $json = json_decode((string)$response, true);

    return [
        'ok' => ($httpcode >= 200 && $httpcode < 300),
        'httpcode' => $httpcode,
        'error' => $err ?: null,
        'raw' => $response,
        'json' => $json,
    ];
}

function autodialerAnnouncementsUpload(string $tmpPath, string $originalName = 'audio.mp3'): array
{
    $cfg = autodialerConfig();

    if (!is_file($tmpPath)) {
        return ['ok' => false, 'httpcode' => 0, 'error' => 'Temp file not found', 'json' => null, 'raw' => null];
    }

    $url = rtrim($cfg['base_url'], '/') . "/announcements/upload"; // per Simotel autodialer docs

    $ch = curl_init();

    // IMPORTANT: multipart upload must NOT use application/json content-type header.
    // Keep only auth headers.
    $headers = array_values(array_filter($cfg['headers'], function ($h) {
        return stripos($h, 'Content-Type:') !== 0;
    }));

    $post = [
        'file' => new CURLFile($tmpPath, mime_content_type($tmpPath) ?: 'application/octet-stream', $originalName),
    ];

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = $url;
    $opts[CURLOPT_CUSTOMREQUEST] = 'POST';
    $opts[CURLOPT_POSTFIELDS] = $post;
    $opts[CURLOPT_HTTPHEADER] = $headers;

    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $json = json_decode((string)$raw, true);

    // Simotel usually returns something like: { success:1, data:{ filename:"..." } } OR { data:{ filename:"..." } }
    $filename = $json['data']['filename'] ?? $json['filename'] ?? null;

    return [
        'ok' => ($httpcode >= 200 && $httpcode < 300 && is_string($filename) && $filename !== ''),
        'httpcode' => $httpcode,
        'error' => $err ?: null,
        'raw' => $raw,
        'json' => $json,
        'filename' => is_string($filename) ? $filename : null,
    ];
}

function autodialerAnnouncementsRemove(string $id): array
{
    $cfg = autodialerConfig();

    $id = trim($id);
    if ($id === '') {
        return ['ok' => false, 'httpcode' => 0, 'error' => 'Missing _id', 'raw' => null, 'json' => null];
    }

    $payload = ['_id' => $id];

    $curl = curl_init();

    $opts = $cfg['curl'];
    // IMPORTANT: use the pbx remove endpoint you gave
    $opts[CURLOPT_URL] = rtrim($cfg['base_url'], '/') . "/announcements/remove";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);

    curl_close($curl);

    return [
        'ok' => ($httpcode >= 200 && $httpcode < 300),
        'httpcode' => $httpcode,
        'error' => $err ?: null,
        'raw' => $response,
        'json' => json_decode((string)$response, true),
    ];
}

function autodialerCampaignsSearch(string $name = '', string $status = '', int $start = 0, int $count = 50): array
{
    $cfg = autodialerConfig();

    $payload = [
        "alike" => "true",
        "conditions" => [
            "name" => (string)$name,
            "description" => "",
            "status" => (string)$status,
        ],
        "pagination" => [
            "start" => (int)$start,
            "count" => (int)$count,
            "sorting" => (object)[], // keep empty
        ],
    ];

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = rtrim($cfg['base_url'], '/') . "/campaigns/search";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);

    curl_close($curl);

    $obj = json_decode((string)$response);

    // docs show: data.pagination + data.data[] :contentReference[oaicite:3]{index=3}
    if ($httpcode >= 200 && $httpcode < 300 && isset($obj->data) && isset($obj->data->data) && is_array($obj->data->data)) {
        return $obj->data->data;
    }

    return [];
}

function autodialerCampaignAdd(array $payload): array
{
    // Auto-set end = start + 10 days (same clock) if not provided
    if (!empty($payload['start']) && empty($payload['end'])) {
        $start = trim((string)$payload['start']);

        // supports both "Y-m-d H:i" and "Y-m-d H:i:s"
        $dt = DateTime::createFromFormat('Y-m-d H:i', $start) ?: DateTime::createFromFormat('Y-m-d H:i:s', $start);

        if ($dt instanceof DateTime) {
            $dt->modify('+10 days');
            $payload['end'] = $dt->format('Y-m-d H:i'); // matches your Simotel example
        }
    }

    // Enforce defaults if you want them hardcoded
    if (empty($payload['try_interval'])) $payload['try_interval'] = "600";
    if (empty($payload['try'])) $payload['try'] = "1";

    $cfg = autodialerConfig();

    $curl = curl_init();

    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = rtrim($cfg['base_url'], '/') . "/campaigns/add";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);

    curl_close($curl);

    return [
        'ok' => ($httpcode >= 200 && $httpcode < 300),
        'httpcode' => $httpcode,
        'error' => $err ?: null,
        'raw' => $response,
        'json' => json_decode((string)$response, true),
    ];
}
function simotelDateTimeFromJalali(string $jalaliDate, string $time = '00:00'): string
{
    // Accept 1404-12-02 or 1404/12/02
    $jalaliDate = str_replace('/', '-', trim($jalaliDate));
    $time = trim($time);

    if ($jalaliDate === '') return '';
    if ($time === '') $time = '00:00';

    $parts = explode('-', $jalaliDate);
    if (count($parts) !== 3) return '';

    $jy = (int)$parts[0];
    $jm = (int)$parts[1];
    $jd = (int)$parts[2];

    // Provided by jdf.php
    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);

    // Simotel example: "2021-02-19 15:17"
    return sprintf('%04d-%02d-%02d %s', $gy, $gm, $gd, $time);
}

function autodialerCampaignRemove(string $id): array
{
    $cfg = autodialerConfig();

    $id = trim($id);
    if ($id === '') {
        return ['ok' => false, 'httpcode' => 0, 'error' => 'Missing _id', 'raw' => null, 'json' => null];
    }

    $payload = ["_id" => $id];

    $curl = curl_init();
    $opts = $cfg['curl'];
    $opts[CURLOPT_URL] = rtrim($cfg['base_url'], '/') . "/campaigns/remove";
    $opts[CURLOPT_CUSTOMREQUEST] = "POST";
    $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $opts[CURLOPT_HTTPHEADER] = $cfg['headers'];

    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);

    curl_close($curl);

    return [
        'ok' => ($httpcode >= 200 && $httpcode < 300),
        'httpcode' => $httpcode,
        'error' => $err ?: null,
        'raw' => $response,
        'json' => json_decode((string)$response, true),
    ];
}

?>



