<?php
// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

if (isset($_POST['id']) && isset($_POST['status'])) {
    $agent_id = intval($_POST['id']);
    $new_status = intval($_POST['status']);

    // تحديث حالة الوكيل في قاعدة البيانات
    $stmt = $conn->prepare("UPDATE agents SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $agent_id);
    
    if ($stmt->execute()) {
        echo "تم تحديث حالة الوكيل بنجاح.";
    } else {
        echo "حدث خطأ أثناء تحديث الحالة.";
    }
} else {
    echo "بيانات غير صحيحة.";
}
?>