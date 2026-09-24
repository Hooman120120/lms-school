<?php
// ===== فایل ajax/get_tel_group_count.php =====
// مسیر: admin/Tel/ajax/get_tel_group_count.php

include_once '../../../dbconfig.php';
include_once '../../../inc/function_login.php';
include_once '../../../inc/function.php';

header('Content-Type: application/json');

// بررسی وجود جلسه ادمین
if (!isset($_SESSION['admin'])) {
    echo json_encode(['count' => 0, 'error' => 'Unauthorized']);
    exit;
}

// دریافت گروه ID
$groupId = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if ($groupId <= 0) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    // شمارش شماره‌های موجود در گروه
    $sql = "SELECT COUNT(*) as count FROM tel_db WHERE `group` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$groupId]);
    $result = $stmt->fetch(PDO::FETCH_OBJ);

    echo json_encode(['count' => (int)$result->count]);

} catch (PDOException $e) {
    error_log("Error getting tel group count: " . $e->getMessage());
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}
?>