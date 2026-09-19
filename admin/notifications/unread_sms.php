<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
require_once '../../vendor/autoload.php';

use Melipayamak\MelipayamakApi;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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
$schoolRoleKey = SchoolStaffRoleKey((string)($_SESSION['admin']['user'] ?? ''));
if (!in_array($schoolRoleKey, ['manager', 'general_deputy', 'executive_deputy'], true)
    && !Permision('permision_viewalert')) {
    http_response_code(403);
    exit('دسترسی مشاهده پیامک‌های دریافتی برای سمت شما تعریف نشده است.');
}

// ===================== خروجی اکسل =====================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // دریافت داده‌ها با همان فیلترها
    $filterPhone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
    $filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

    $whereConditions = [];
    $params = [];

    if (!empty($filterPhone)) {
        $whereConditions[] = "(from_number = :phone OR to_number = :phone)";
        $params[':phone'] = $filterPhone;
    }

    if ($filterStatus === 'unread') {
        $whereConditions[] = "is_read = 0";
    } elseif ($filterStatus === 'read') {
        $whereConditions[] = "is_read = 1";
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    $query = "SELECT * FROM sms_inbox $whereClause ORDER BY received_at DESC LIMIT 1000";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $exportData = $stmt->fetchAll(PDO::FETCH_OBJ);

    // ===== افزودن این خط برای معکوس کردن ترتیب =====
    $exportData = array_reverse($exportData);
    // ===============================================

    if (empty($exportData)) {
        die('داده‌ای برای خروجی وجود ندارد.');
    }

    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // عنوان ستون‌ها
        $headers = ['شناسه', 'متن پیام', 'تاریخ دریافت', 'گیرنده', 'فرستنده', 'وضعیت'];
        $colIndex = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($colIndex . '1', $header);
            $sheet->getStyle($colIndex . '1')->getFont()->setBold(true);
            $sheet->getStyle($colIndex . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($colIndex . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($colIndex . '1')->getFill()->getStartColor()->setARGB('FFE0E0E0');
            $colIndex++;
        }

        // پر کردن داده‌ها
        $row = 2;
        foreach ($exportData as $msg) {
            $isRead = (bool)$msg->is_read;
            $jalaliDate = jdate('Y/m/d H:i', strtotime($msg->received_at));
            $statusText = $isRead ? 'خوانده شده' : 'خوانده نشده';

            $sheet->setCellValue('A' . $row, $msg->id);
            $sheet->setCellValue('B' . $row, $msg->body);
            $sheet->setCellValue('C' . $row, $jalaliDate);
            $sheet->setCellValue('D' . $row, $msg->to_number);
            $sheet->setCellValue('E' . $row, $msg->from_number);
            $sheet->setCellValue('F' . $row, $statusText);

            // رنگ‌آمیزی ردیف بر اساس وضعیت
            if (!$isRead) {
                $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->getStartColor()->setARGB('FFFFF3CD');
            }

            $row++;
        }

        // تنظیم عرض ستون‌ها
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // تنظیم مرزها
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $sheet->getStyle('A1:F' . ($row - 1))->applyFromArray($styleArray);

        // تنظیم تراز برای ستون متن
        $sheet->getStyle('B2:B' . ($row - 1))->getAlignment()->setWrapText(true);

        // ایجاد و دانلود
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="sms_inbox_export_' . date('Ymd_His') . '.xlsx"');
        $writer->save('php://output');
        exit;

    } catch (Exception $e) {
        die('خطا در تولید اکسل: ' . $e->getMessage());
    }
}
// =====================================================

// -------------------- دریافت شماره‌های اختصاصی (از API) --------------------
$numbers = [];
try {
    $api = new MelipayamakApi(__USERSMS, __PASSSMS);
    $smsRest = $api->sms();
    $response = $smsRest->getNumbers();

    if (is_string($response)) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $response = $decoded;
        }
    }

    $api_numbers = [];
    if (is_array($response) && isset($response['Data']) && is_array($response['Data'])) {
        $api_numbers = array_column($response['Data'], 'Number');
    } elseif (is_object($response) && property_exists($response, 'Data')) {
        $data = $response->Data;
        if (is_array($data)) {
            $api_numbers = array_column($data, 'Number');
        } elseif (is_object($data) && property_exists($data, 'Data')) {
            $inner = $data->Data;
            if (is_array($inner)) {
                $api_numbers = array_column($inner, 'Number');
            }
        }
    }

    $numbers = array_values(array_filter(array_map('trim', $api_numbers)));
} catch (Exception $e) {
    error_log("Error getting numbers: " . $e->getMessage());
    $numbers = [];
}

// ===================== پردازش اکشن‌ها =====================
// اکشن: علامت خوانده شده
if (isset($_POST['action']) && $_POST['action'] === 'mark_read' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("UPDATE sms_inbox SET is_read = 1 WHERE id = :id");
        $stmt->execute([':id' => (int)$_POST['id']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// اکشن: حذف پیام
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("DELETE FROM sms_inbox WHERE id = :id");
        $stmt->execute([':id' => (int)$_POST['id']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===================== فیلتر و دریافت پیام‌ها =====================
$filterPhone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

// ساخت کوئری شرطی
$whereConditions = [];
$params = [];

// فیلتر بر اساس شماره (فرستنده یا گیرنده)
if (!empty($filterPhone)) {
    $whereConditions[] = "(from_number = :phone OR to_number = :phone)";
    $params[':phone'] = $filterPhone;
}

// فیلتر بر اساس وضعیت خوانده شده
if ($filterStatus === 'unread') {
    $whereConditions[] = "is_read = 0";
} elseif ($filterStatus === 'read') {
    $whereConditions[] = "is_read = 1";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// دریافت پیام‌ها از جدول sms_inbox
$query = "SELECT * FROM sms_inbox $whereClause ORDER BY received_at DESC LIMIT 100";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$inboxMessages = $stmt->fetchAll(PDO::FETCH_OBJ);

// دریافت تعداد پیام‌های خوانده‌نشده
$unreadStmt = $conn->query("SELECT COUNT(*) FROM sms_inbox WHERE is_read = 0");
$unreadCount = $unreadStmt->fetchColumn();

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>پیامک‌های دریافتی | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .msg-body { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle; }
        .modal-body { white-space: pre-wrap; word-break: break-word; max-height: 400px; overflow-y: auto; }
        .table-responsive { overflow-x: auto; }
        .unread-row { background-color: #fff3cd !important; border-right: 4px solid #ffc107; }
        .read-row { background-color: #f8f9fa !important; }
        .badge-unread { background-color: #dc3545; color: white; border-radius: 50%; padding: 2px 10px; font-size: 12px; }
        .action-dropdown { min-width: 120px; }
    </style>
</head>
<body class="main-body leftmenu">
<div id="global-loader"><img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری"></div>
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">پیامک‌های دریافتی
                                        <?php if ($unreadCount > 0): ?>
                                            <span class="badge-unread"><?php echo $unreadCount; ?> جدید</span>
                                        <?php endif; ?>
                                    </h4>
                                    <div>
                                        <a href="<?php echo url('admin/Tel/safe_sms.php'); ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fa fa-search"></i> گزارش کامل
                                        </a>
                                        <?php if ($inboxMessages && count($inboxMessages) > 0): ?>
                                            <a href="?export=excel<?php echo !empty($filterPhone) ? '&phone=' . urlencode($filterPhone) : ''; ?><?php echo !empty($filterStatus) ? '&status=' . urlencode($filterStatus) : ''; ?>" class="btn btn-success btn-sm">
                                                <i class="fa fa-file-excel-o"></i> خروجی اکسل
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- فیلتر -->
                                <form method="GET" class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">شماره (فرستنده/گیرنده):</label>
                                        <select class="form-control" name="phone">
                                            <option value="">همه سرشماره‌ها</option>
                                            <?php foreach ($numbers as $num): ?>
                                                <option value="<?php echo htmlspecialchars($num); ?>" <?php echo ($filterPhone == $num) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($num); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">وضعیت:</label>
                                        <select class="form-control" name="status">
                                            <option value="">همه</option>
                                            <option value="unread" <?php echo ($filterStatus === 'unread') ? 'selected' : ''; ?>>خوانده نشده</option>
                                            <option value="read" <?php echo ($filterStatus === 'read') ? 'selected' : ''; ?>>خوانده شده</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 align-self-end">
                                        <button type="submit" class="btn btn-primary w-100">اعمال فیلتر</button>
                                    </div>
                                    <?php if (!empty($filterPhone) || !empty($filterStatus)): ?>
                                        <div class="col-md-2 align-self-end">
                                            <a href="?" class="btn btn-outline-danger w-100">حذف فیلتر</a>
                                        </div>
                                    <?php endif; ?>
                                </form>

                                <?php if ($inboxMessages && count($inboxMessages) > 0): ?>
                                    <div class="table-responsive mt-3">
                                        <table id="example1" class="table table-bordered table-hover text-center align-middle">
                                            <thead class="table-light">
                                            <tr>
                                                <th>شناسه</th>
                                                <th>متن پیام</th>
                                                <th>تاریخ دریافت</th>
                                                <th>گیرنده</th>
                                                <th>فرستنده</th>
                                                <th>وضعیت</th>
                                                <th>عملیات</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($inboxMessages as $msg):
                                                $isRead = (bool)$msg->is_read;
                                                $rowClass = $isRead ? 'read-row' : 'unread-row';
                                                $jalaliDate = jdate('Y/m/d H:i', strtotime($msg->received_at));
                                                $body = $msg->body;
                                                $shortBody = mb_substr($body, 0, 30, 'UTF-8');
                                                $isLong = mb_strlen($body, 'UTF-8') > 30;
                                                $msgId = $msg->id;
                                                ?>
                                                <tr class="<?php echo $rowClass; ?>" data-id="<?php echo $msgId; ?>">
                                                    <td><?php echo htmlspecialchars($msgId); ?></td>
                                                    <td>
                                                        <span class="msg-body" title="<?php echo htmlspecialchars($body); ?>">
                                                            <?php echo htmlspecialchars($shortBody) . ($isLong ? '...' : ''); ?>
                                                        </span>
                                                        <?php if ($isLong): ?>
                                                            <button type="button" class="btn btn-sm btn-info view-msg" data-msgbody="<?php echo htmlspecialchars($body); ?>">
                                                                <i class="fa fa-eye"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $jalaliDate; ?></td>
                                                    <td><?php echo htmlspecialchars($msg->to_number); ?></td>
                                                    <td><?php echo htmlspecialchars($msg->from_number); ?></td>
                                                    <td>
                                                        <?php if (!$isRead): ?>
                                                            <span class="badge bg-danger">خوانده نشده</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">خوانده شده</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                اقدامات
                                                            </button>
                                                            <ul class="dropdown-menu action-dropdown">
                                                                <?php if (!$isRead): ?>
                                                                    <li>
                                                                        <a class="dropdown-item mark-read-btn" href="#" data-id="<?php echo $msgId; ?>">
                                                                            <i class="fa fa-check-circle text-success"></i> تغییر به خوانده شده
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <li>
                                                                    <a class="dropdown-item text-danger delete-btn" href="#" data-id="<?php echo $msgId; ?>">
                                                                        <i class="fa fa-trash"></i> حذف
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fa fa-inbox fa-2x d-block mb-2"></i>
                                        هیچ پیامکی یافت نشد.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>

<!-- مودال مشاهده کامل پیام -->
<div class="modal fade" id="msgModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">متن کامل پیام</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="بستن">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="msgFullBody">در حال بارگذاری...</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // مشاهده کامل پیام
        $(document).on('click', '.view-msg', function() {
            var fullBody = $(this).data('msgbody');
            $('#msgFullBody').html(fullBody || 'متن پیام موجود نیست.');
            $('#msgModal').modal('show');
        });

        // علامت خوانده شده (AJAX)
        $(document).on('click', '.mark-read-btn', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $row = $('tr[data-id="' + id + '"]');

            $.post('unread_sms.php', {
                action: 'mark_read',
                id: id
            }, function(res) {
                if (res.success) {
                    // به‌روزرسانی ظاهر ردیف
                    $row.removeClass('unread-row').addClass('read-row');
                    $row.find('.badge').removeClass('bg-danger').addClass('bg-success').text('خوانده شده');
                    $row.find('.mark-read-btn').parent().remove();

                    // به‌روزرسانی شمارنده
                    var badge = $('.badge-unread');
                    var current = parseInt(badge.text()) || 0;
                    if (current > 0) {
                        var newCount = current - 1;
                        if (newCount > 0) {
                            badge.text(newCount);
                        } else {
                            badge.remove();
                        }
                    }
                } else {
                    alert('خطا در به‌روزرسانی وضعیت.');
                }
            }, 'json').fail(function() {
                alert('خطا در ارتباط با سرور.');
            });
        });

        // حذف پیام (AJAX)
        $(document).on('click', '.delete-btn', function(e) {
            e.preventDefault();
            var id = $(this).data('id');

            if (!confirm('آیا از حذف این پیام اطمینان دارید؟')) {
                return;
            }

            $.post('unread_sms.php', {
                action: 'delete',
                id: id
            }, function(res) {
                if (res.success) {
                    $('tr[data-id="' + id + '"]').fadeOut(300, function() {
                        $(this).remove();
                        if ($('tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert('خطا در حذف پیام.');
                }
            }, 'json').fail(function() {
                alert('خطا در ارتباط با سرور.');
            });
        });
    });
</script>
</body>
</html>
