<?php
function chahard_join_url(): string
{
    return 'http://localhost/chahard/api/lms-join.php';
}

function chahard_api_key(): string
{
    return trim((string) getenv('CHAHARD_API_KEY'));
}

function chahard_rooms_for_lms(): array
{
    $joinUrl = chahard_join_url();
    $key = chahard_api_key();
    if ($joinUrl === '' || $key === '' || !str_ends_with($joinUrl, '/lms-join.php')) throw new RuntimeException('اتصال چهاردیواری تنظیم نشده است');
    $url = substr($joinUrl, 0, -strlen('lms-join.php')) . 'lms-rooms.php';
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $response = json_decode((string) $body, true);
    if ($status !== 200 || !is_array($response) || empty($response['success']) || !is_array($response['data'] ?? null)) {
        throw new RuntimeException('فهرست اتاق‌های چهاردیواری دریافت نشد');
    }
    return $response['data'];
}
