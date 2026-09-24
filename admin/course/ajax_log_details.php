<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/jdf.php';

if (!isset($_SESSION['admin'])) {
    echo 'دسترسی غیرمجاز';
    exit;
}
$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM api_request_log WHERE id = ?");
$stmt->execute([$id]);
$log = $stmt->fetch(PDO::FETCH_OBJ);
if (!$log) {
    echo 'رکورد یافت نشد';
    exit;
}

function toJalali($datetime) {
    if (empty($datetime)) return '-';
    $tz = new DateTimeZone('Asia/Tehran');
    $date = new DateTime($datetime, $tz);
    return jdate('Y/m/d H:i:s', $date->getTimestamp(), '', 'local');
}
?>
<style>
    .detail-table td, .detail-table th { vertical-align: top; padding: 8px; }
    .detail-table pre {
        background: #f8f9fa;
        padding: 8px;
        border-radius: 5px;
        white-space: pre-wrap;
        word-wrap: break-word;
        max-height: 300px;
        overflow-y: auto;
        margin: 0;
        direction: ltr;
        text-align: left;
    }
    .detail-table .rtl-text {
        direction: rtl;
        text-align: right;
    }
</style>
<table class="table table-sm table-bordered detail-table">
    <tr><th>شناسه</th><td><?= $log->id ?></td></tr>
    <tr><th>درخواست‌دهنده</th><td class="rtl-text"><?= htmlspecialchars($log->requested_by) ?></td></tr>
    <tr><th>نوع هدف</th><td class="rtl-text"><?= htmlspecialchars($log->target_type) ?></td></tr>
    <tr><th>شناسه هدف</th><td><?= htmlspecialchars($log->target_id) ?></td></tr>
    <tr><th>رویداد</th><td class="rtl-text"><?= htmlspecialchars($log->event_type) ?></td></tr>
    <tr><th>متد</th><td><?= htmlspecialchars($log->request_method) ?></td></tr>
    <tr><th>آدرس URL</th><td dir="ltr"><pre><?= htmlspecialchars($log->request_url) ?></pre></td></tr>
    <tr><th>هدرهای درخواست</th><td dir="ltr"><pre><?= htmlspecialchars($log->request_headers ?? '-') ?></pre></td></tr>
    <tr><th>بدنه درخواست</th><td dir="ltr"><pre><?= htmlspecialchars($log->request_body ?? '-') ?></pre></td></tr>
    <tr><th>کد وضعیت HTTP</th><td><?= $log->response_status_code ?></td></tr>
    <tr><th>بدنه پاسخ</th>
        <td dir="ltr">
            <pre style="max-height: 400px; overflow-y: auto;"><?= htmlspecialchars($log->response_body ?? '-') ?></pre>
        </td>
    </tr>
    <tr><th>وضعیت</th><td class="rtl-text"><?= $log->is_success ? 'موفق' : 'ناموفق' ?></td></tr>
    <tr><th>تعداد تلاش مجدد</th><td><?= $log->retry_count ?></td></tr>
    <tr><th>پیام خطا</th><td class="rtl-text" dir="ltr"><pre><?= htmlspecialchars($log->error_message ?? '-') ?></pre></td></tr>
    <tr><th>تاریخ ایجاد</th><td class="rtl-text"><?= toJalali($log->created_at) ?></td></tr>
    <tr><th>آخرین تلاش مجدد</th><td class="rtl-text"><?= $log->last_retry_at ? toJalali($log->last_retry_at) : '-' ?></td></tr>
</table>