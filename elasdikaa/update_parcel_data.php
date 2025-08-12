<?php
// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// التأكد من أن الطلب من نوع POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من أن جميع البيانات المطلوبة موجودة
    if (isset($_POST['id'], $_POST['reference_number'], $_POST['recipient_name'], $_POST['to_area'], $_POST['price'], $_POST['status'])) {

        // إعداد استعلام التحديث باستخدام Prepared Statements لمنع حقن SQL
        $stmt = $conn->prepare("UPDATE parcels SET 
                                    reference_number = ?, 
                                    recipient_name = ?, 
                                    to_area = ?, 
                                    price = ?, 
                                    status = ? 
                                WHERE id = ?");

        // ربط المتغيرات بالاستعلام
        $stmt->bind_param("sssdii", 
                            $_POST['reference_number'], 
                            $_POST['recipient_name'], 
                            $_POST['to_area'], 
                            $_POST['price'], 
                            $_POST['status'], 
                            $_POST['id']);

        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            echo "تم تحديث الشحنة بنجاح.";
        } else {
            // عرض خطأ دقيق في حال فشل التنفيذ
            echo "خطأ في تحديث الشحنة: " . $stmt->error;
        }

        // إغلاق الاستعلام
        $stmt->close();
    } else {
        echo "خطأ: البيانات غير مكتملة.";
    }
} else {
    echo "خطأ: طريقة الطلب غير صالحة.";
}

// إغلاق الاتصال بقاعدة البيانات
$conn->close();
?>