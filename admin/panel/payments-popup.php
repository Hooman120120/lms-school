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

// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

// Security: Sanitize the Input
$codemeli = $_GET['user'];

$sql = "SELECT id, total_amount, created_at, status FROM payments WHERE codemeli = ? AND status != 'paid'";
$stmt = $conn->prepare($sql);
$stmt->execute([$codemeli]);
$payments = $stmt->fetchAll();

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>انتخاب صورتحساب</title>
    <?php include_once '../link.php'; ?>
    <style>
        .list-group-item { cursor: pointer; transition: all 0.2s; }
        .list-group-item:hover { background-color: #f0f7ff; transform: scale(1.01); }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h4 class="mb-4 text-primary">لطفا یکی از پرونده‌های مالی را انتخاب کنید:</h4>

    <div class="list-group">
        <?php foreach ($payments as $pay): ?>
            <a href="javascript:void(0);"
               onclick="selectPayment('<?php echo $pay['id']; ?>')"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-2 shadow-sm rounded">
                <div>
                    <i class="fa fa-file-invoice ml-2 text-secondary"></i>
                    <strong>شناسه پرداخت: <?php echo $pay['id']; ?></strong>
                    <div class="small text-muted mt-1">
                        تاریخ ثبت:
                        <?php
                        // Using your jdf.php if available for Persian dates
                        $date = explode(' ', $pay['created_at'])[0];
                        $d = explode('-', $date);
                        echo gregorian_to_jalali($d[0], $d[1], $d[2], '/');
                        ?>
                    </div>
                </div>
                <div class="text-left">
                    <span class="badge badge-primary-light mb-1 d-block px-3 py-2">
                        <?php echo number_format($pay['total_amount']); ?> تومان
                    </span>
                    <span class="btn btn-sm btn-primary">انتخاب و نمایش</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
    function selectPayment(paymentId) {
        // 1. Define the target URL for the main tab
        var targetUrl = "../Bill/paymentinstallments.php?id=" + paymentId;

        // 2. Check if the parent window (opener) still exists and isn't closed
        if (window.opener && !window.opener.closed) {
            // Redirect the main (parent) tab
            window.opener.location.href = targetUrl;

            // Focus the main tab (optional, depends on browser security)
            window.opener.focus();

            // 3. Close this popup
            window.close();
        } else {
            // Fallback: If the parent was closed, just open it in this window
            window.location.href = targetUrl;
        }
    }
</script>

</body>
</html>