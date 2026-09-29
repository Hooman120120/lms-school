<?php
/**
 * Pretend to be a gate device: sends a morning of arrivals to the push API.
 *
 *   php tools/attendance-simulate.php --url=https://my.school.ir/api/attendance/push.php --key=KEY
 *       --codes=0016196406,0012345678 [--date=2026-09-29] [--start=07:15] [--spread=40] [--late=2]
 *       [--drop-zeros] [--resend]
 *
 * --spread  arrival window in minutes from --start
 * --late    how many of the codes arrive 20-60 minutes after the window
 * --drop-zeros  send codes as numbers without leading zeros (like many devices)
 * --resend  send the same batch twice to check duplicates are ignored
 * Events are marked "test": true, so they are stored with source = simulator.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$opt = getopt('', ['url:', 'key:', 'codes:', 'date:', 'start:', 'spread:', 'late:', 'drop-zeros', 'resend']);
if (empty($opt['url']) || empty($opt['key']) || empty($opt['codes'])) {
    fwrite(STDERR, "usage: php tools/attendance-simulate.php --url=URL --key=KEY --codes=C1,C2 [--date= --start=07:15 --spread=40 --late=0 --drop-zeros --resend]\n");
    exit(2);
}
$codes = array_values(array_filter(array_map('trim', explode(',', (string) $opt['codes']))));
$date = (string) ($opt['date'] ?? date('Y-m-d'));
$start = strtotime("$date " . ($opt['start'] ?? '07:15'));
$spread = max(1, (int) ($opt['spread'] ?? 40)) * 60;
$late = min(count($codes), (int) ($opt['late'] ?? 0));

$events = [];
foreach ($codes as $i => $code) {
    $t = $start + random_int(0, $spread);
    if ($i >= count($codes) - $late) $t = $start + $spread + random_int(20 * 60, 60 * 60);
    $events[] = [
        'user' => isset($opt['drop-zeros']) ? (int) $code : $code,
        'time' => date('Y-m-d H:i:s', $t),
        'direction' => 'in',
        'score' => random_int(85, 99) / 100,
    ];
}

$send = function (array $events) use ($opt) {
    $ch = curl_init((string) $opt['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Device-Key: ' . $opt['key']],
        CURLOPT_POSTFIELDS => json_encode(['test' => true, 'events' => $events]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "HTTP $status " . ($body === false ? $err : $body) . "\n";
};

foreach ($events as $e) echo "  {$e['user']}  {$e['time']}\n";
$send($events);
if (isset($opt['resend'])) $send($events);
