<?php
// ===== فایل ajax/get_course_students_count.php =====
// مسیر: admin/Tel/ajax/get_course_students_count.php

include_once '../../../dbconfig.php';
include_once '../../../inc/function_login.php';
include_once '../../../inc/function.php';

header('Content-Type: application/json');

// بررسی وجود جلسه ادمین
if (!isset($_SESSION['admin'])) {
    echo json_encode(['count' => 0, 'error' => 'Unauthorized']);
    exit;
}

// دریافت کد پایه
$courseCode = isset($_POST['codecourse']) ? trim($_POST['codecourse']) : '';

if (empty($courseCode)) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    // شمارش دانش‌آموزان فعال در پایه
    $sql = "SELECT COUNT(*) as count 
            FROM dbcourse d 
            WHERE d.codecourse = ? AND d.activityuser = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$courseCode]);
    $result = $stmt->fetch(PDO::FETCH_OBJ);

    echo json_encode(['count' => (int)$result->count]);

} catch (PDOException $e) {
    error_log("Error getting course students count: " . $e->getMessage());
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}
?>