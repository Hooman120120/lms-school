<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
// Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
$sessionUser = $_SESSION['admin']['user'];
$urlType = trim($_GET['type'] ?? '');
$isImpersonate = ($_SESSION['admin']['can_impersonate']) == 1;

// ================================
// بخش مدیریت لاگ‌های API
// ================================

// فیلترهای دریافتی از GET
$filter_success = null;
if (isset($_GET['filter_success']) && $_GET['filter_success'] !== '') {
    $filter_success = (int)$_GET['filter_success'];
}
$filter_event = isset($_GET['event_type']) ? trim($_GET['event_type']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($filter_success !== null) {
    $where[] = "is_success = ?";
    $params[] = $filter_success;
}
if ($filter_event !== '') {
    $where[] = "event_type = ?";
    $params[] = $filter_event;
}
if ($search !== '') {
    $where[] = "(request_url LIKE ? OR error_message LIKE ? OR request_body LIKE ? OR response_body LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereClause = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// شمارش کل رکوردها برای صفحه‌بندی
$countSql = "SELECT COUNT(*) FROM api_request_log $whereClause";
$stmtCount = $conn->prepare($countSql);
$stmtCount->execute($params);
$totalRows = $stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// دریافت رکوردهای لاگ با JOIN برای گرفتن نام درخواست‌دهنده (اختیاری)
$sql = "SELECT l.*, 
               (SELECT username FROM dbadmin WHERE username = l.requested_by LIMIT 1) AS requested_by_name
        FROM api_request_log l
        $whereClause
        ORDER BY l.is_success ASC, l.created_at DESC
        LIMIT $offset, $perPage";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_OBJ);

// دریافت لیست unique event_type ها برای فیلتر
$eventTypes = $conn->query("SELECT DISTINCT event_type FROM api_request_log WHERE event_type NOT IN ('get_quiz_summary', 'check_user_quiz', 'get_user_by_username') ORDER BY event_type")->fetchAll(PDO::FETCH_COLUMN);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>مدیریت لاگ‌های API | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .log-detail-modal .modal-body pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 400px;
            overflow-y: auto;
        }
        .badge-success { background-color: #28a745; color: #fff; }
        .badge-danger { background-color: #dc3545; color: #fff; }
        .badge-warning { background-color: #ffc107; color: #212529; }
        .table-log td, .table-log th { vertical-align: middle; }
        .log-row-failed { background-color: #fff2f0; }
        .log-row-success { background-color: #f0fff4; }
        .clickable-row { cursor: pointer; }
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-header border-bottom-0">
                                <h3 class="card-title">گزارش درخواست‌های API به سامانه آزمون</h3>
                                <div class="card-options">
                                    <a href="?reset=1" class="btn btn-sm btn-outline-secondary">حذف فیلترها</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- فیلترها -->
                                <form method="GET" class="row g-3 mb-4">
                                    <div class="col-md-3">
                                        <label class="form-label">وضعیت</label>
                                        <select name="filter_success" class="form-select">
                                            <option value="">همه</option>
                                            <option value="1" <?= $filter_success === 1 ? 'selected' : '' ?>>موفق</option>
                                            <option value="0" <?= $filter_success === 0 ? 'selected' : '' ?>>ناموفق</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">نوع رویداد</label>
                                        <select name="event_type" class="form-select">
                                            <option value="">همه</option>
                                            <?php foreach ($eventTypes as $et): ?>
                                                <option value="<?= htmlspecialchars($et) ?>" <?= $filter_event === $et ? 'selected' : '' ?>><?= htmlspecialchars($et) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">جستجو (URL, خطا, بدنه)</label>
                                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="متن مورد نظر...">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">اعمال فیلتر</button>
                                    </div>
                                </form>

                                <!-- جدول نمایش لاگ‌ها -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover text-center align-middle table-log" id="example1">
                                        <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>درخواست‌دهنده</th>
                                            <th>نوع هدف</th>
                                            <th>شناسه هدف</th>
                                            <th>رویداد</th>
                                            <th>متد</th>
                                            <th>کد وضعیت</th>
                                            <th>وضعیت</th>
                                            <th>پیام خطا</th>
                                            <th>تاریخ</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($logs)): ?>
                                            <tr><td colspan="11">هیچ لاگی یافت نشد</td></tr>
                                        <?php else: $rt = 1; ?>
                                            <?php foreach ($logs as $index => $log): ?>
                                                <?php
                                                if ($log->event_type == 'get_quiz_summary') continue;
                                                $rowClass = $log->is_success ? 'log-row-success' : 'log-row-failed';
                                                $statusBadge = $log->is_success ? '<span class="badge badge-success">موفق</span>' : '<span class="badge badge-danger">ناموفق</span>';
                                                ?>
                                                <tr class="<?= $rowClass ?> clickable-row" data-id="<?= $log->id ?>">
                                                    <td><?= $rt++ ?></td>
                                                    <td><?= htmlspecialchars($log->requested_by_name ?? $log->requested_by) ?></td>
                                                    <td><?= htmlspecialchars($log->target_type) ?></td>
                                                    <td><?= htmlspecialchars($log->target_id) ?></td>
                                                    <td><?= htmlspecialchars($log->event_type) ?></td>
                                                    <td><?= htmlspecialchars($log->request_method) ?></td>
                                                    <td><?= $log->response_status_code ?? '-' ?></td>
                                                    <td><?= $statusBadge ?></td>
                                                    <td class="text-wrap" style="max-width: 250px;"><?= htmlspecialchars(mb_substr($log->error_message ?? '', 0, 80)) ?></td>
                                                    <td dir="ltr"><?= jdate('Y/m/d H:i:s', strtotime($log->created_at), '', 'Asia/Tehran') ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info view-details" data-id="<?= $log->id ?>" data-bs-toggle="modal" data-bs-target="#detailModal">جزئیات</button>
                                                        <?php if (!$log->is_success): ?>
                                                            <button class="btn btn-sm btn-warning retry-log" data-id="<?= $log->id ?>">تلاش مجدد</button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- صفحه‌بندی -->
                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mt-4">
                                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page-1 ?>&filter_success=<?= $filter_success ?>&event_type=<?= urlencode($filter_event) ?>&search=<?= urlencode($search) ?>">قبلی</a>
                                            </li>
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&filter_success=<?= $filter_success ?>&event_type=<?= urlencode($filter_event) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page+1 ?>&filter_success=<?= $filter_success ?>&event_type=<?= urlencode($filter_event) ?>&search=<?= urlencode($search) ?>">بعدی</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال نمایش جزئیات لاگ -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">جزئیات درخواست API</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body log-detail-modal">
                <div id="detailContent">بارگذاری...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../footers.php'; ?>

<script>
    $(function() {
        // نمایش جزئیات لاگ با AJAX
        $('.view-details').click(function(e) {
            e.stopPropagation();
            var logId = $(this).data('id');
            $('#detailContent').html('در حال بارگذاری...');
            $.get('ajax_log_details.php?id=' + logId, function(data) {
                $('#detailContent').html(data);
            }).fail(function() {
                $('#detailContent').html('<div class="alert alert-danger">خطا در بارگذاری جزئیات</div>');
            });
        });

        // تلاش مجدد برای درخواست ناموفق
        $('.retry-log').click(function(e) {
            e.stopPropagation();
            var btn = $(this);
            var logId = btn.data('id');
            if (!confirm('آیا از تلاش مجدد این درخواست اطمینان دارید؟')) return;
            btn.prop('disabled', true).html('در حال اجرا...');
            $.post('retry_api_request.php', { log_id: logId }, function(response) {
                if (response.success) {
                    alert('درخواست با موفقیت انجام شد.');
                    location.reload();
                } else {
                    alert('خطا: ' + (response.message || 'نامشخص'));
                    btn.prop('disabled', false).html('تلاش مجدد');
                }
            }, 'json').fail(function() {
                alert('خطا در ارتباط با سرور');
                btn.prop('disabled', false).html('تلاش مجدد');
            });
        });

        // کلیک روی سطر برای نمایش جزئیات (اختیاری)
        $('.clickable-row').click(function() {
            $(this).find('.view-details').click();
        });
    });
</script>
</body>
</html>