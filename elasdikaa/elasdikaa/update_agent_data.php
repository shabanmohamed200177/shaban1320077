<?php
// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// التأكد من أن الطلب من نوع POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من أن جميع البيانات المطلوبة موجودة
    if (isset($_POST['id'], $_POST['company_name'], $_POST['contact_person'], $_POST['phone'], $_POST['email'], $_POST['geo_area'], $_POST['status'])) {
        
        $password = $_POST['password'] ?? '';
        
        // التحقق مما إذا كان هناك كلمة مرور جديدة لتحديثها
        if (!empty($password)) {
            // تشفير كلمة المرور الجديدة
            // يوصى بشدة باستخدام password_hash() بدلاً من md5()
            $hashed_password = md5($password);
            
            // إعداد استعلام التحديث مع كلمة المرور
            $stmt = $conn->prepare("UPDATE agents SET 
                                        company_name = ?, 
                                        contact_person = ?, 
                                        phone = ?, 
                                        email = ?, 
                                        geo_area = ?,
                                        password = ?,
                                        status = ?
                                    WHERE id = ?");
            
            // ربط المتغيرات بالاستعلام (sssi: 5 strings, 2 integers)
            $stmt->bind_param("ssssssii", 
                                $_POST['company_name'], 
                                $_POST['contact_person'], 
                                $_POST['phone'], 
                                $_POST['email'], 
                                $_POST['geo_area'],
                                $hashed_password,
                                $_POST['status'],
                                $_POST['id']);
        } else {
            // إعداد استعلام التحديث بدون كلمة المرور
            $stmt = $conn->prepare("UPDATE agents SET 
                                        company_name = ?, 
                                        contact_person = ?, 
                                        phone = ?, 
                                        email = ?, 
                                        geo_area = ?,
                                        status = ?
                                    WHERE id = ?");
            
            // ربط المتغيرات بالاستعلام (sssi: 5 strings, 2 integers)
            $stmt->bind_param("sssssii", 
                                $_POST['company_name'], 
                                $_POST['contact_person'], 
                                $_POST['phone'], 
                                $_POST['email'], 
                                $_POST['geo_area'],
                                $_POST['status'],
                                $_POST['id']);
        }

        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            echo "تم تحديث بيانات الوكيل بنجاح.";
        } else {
            // عرض خطأ دقيق في حال فشل التنفيذ
            echo "خطأ في تحديث بيانات الوكيل: " . $stmt->error;
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