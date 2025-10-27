<?php
// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

if (isset($_POST['id'])) {
    $agent_id = intval($_POST['id']);

    // حذف الوكيل من قاعدة البيانات
    $stmt = $conn->prepare("DELETE FROM agents WHERE id = ?");
    $stmt->bind_param("i", $agent_id);
    
    if ($stmt->execute()) {
        echo "تم حذف الوكيل بنجاح.";
    } else {
        echo "حدث خطأ أثناء حذف الوكيل.";
    }
} else {
    echo "بيانات غير صحيحة.";
}
?>