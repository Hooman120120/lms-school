<?php
// فایل اتصال به دیتابیس خود را اینجا ضمیمه کنید
include_once '../../dbconfig.php';

if (isset($_POST['codecourse'])) {
    $codecourse = $_POST['codecourse'];
    
    // واکشی کلاس‌های فعال برای این کد پایه
    $sql = "SELECT id, name FROM classes WHERE codecourse = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codecourse]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($classes) > 0) {
        foreach ($classes as $c) {
            echo "<option value='{$c['id']}'>{$c['name']}</option>";
        }
    } else {
        // در صورتی که کلاسی تعریف نشده باشد
        echo "<option value='' disabled>کلاسی برای این پایه تعریف نشده است</option>";
    }
}
?>
