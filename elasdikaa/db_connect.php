<?php
/**
 * ملف الاتصال بقاعدة البيانات - محدث مع نظام معالجة الأخطاء
 */

// تضمين نظام معالجة الأخطاء
require_once 'error_handler.php';

// بيانات الاتصال بقاعدة البيانات
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'elasdikaa';

// إنشاء اتصال جديد
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// التحقق من الاتصال
if ($conn->connect_error) {
    // استخدام نظام معالجة الأخطاء الجديد
    handleDatabaseError('فشل الاتصال بقاعدة البيانات: ' . $conn->connect_error, $conn);
}

// تعيين ترميز UTF-8
if (!$conn->set_charset("utf8mb4")) {
    handleDatabaseError('فشل في تعيين ترميز قاعدة البيانات: ' . $conn->error, $conn);
}

// تعيين إعدادات إضافية للأمان
$conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

// تسجيل نجاح الاتصال
if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') {
    error_log("Database connection established successfully");
}
?>
