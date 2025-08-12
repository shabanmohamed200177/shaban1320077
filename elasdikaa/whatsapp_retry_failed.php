<?php
/**
 * مهمة cron لإعادة محاولة إرسال الإشعارات الفاشلة
 * يجب تشغيلها كل 5 دقائق
 * 
 * إضافة إلى crontab:
 * */5 * * * * /usr/bin/php /path/to/whatsapp_retry_failed.php
 */

// منع الوصول المباشر من المتصفح
if (php_sapi_name() !== 'cli' && !isset($_GET['force_run'])) {
    http_response_code(403);
    die('هذا الملف يجب تشغيله من سطر الأوامر فقط');
}

include_once __DIR__ . '/db_connect.php';
include_once __DIR__ . '/WhatsAppNotificationManager.php';

try {
    $whatsapp_manager = new WhatsAppNotificationManager($conn);
    
    echo "[" . date('Y-m-d H:i:s') . "] بدء إعادة محاولة الإرسال...\n";
    
    // إعادة محاولة إرسال الرسائل الفاشلة (حد أقصى 10 رسائل)
    $result = $whatsapp_manager->retryFailedNotifications(10);
    
    echo "[" . date('Y-m-d H:i:s') . "] تم الانتهاء من إعادة المحاولة\n";
    
    // تنظيف السجلات القديمة (أكثر من 30 يوم)
    $cleanup_query = "DELETE FROM notification_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $conn->query($cleanup_query);
    
    echo "[" . date('Y-m-d H:i:s') . "] تم تنظيف السجلات القديمة\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] خطأ: " . $e->getMessage() . "\n";
    error_log("WhatsApp Retry Job Error: " . $e->getMessage());
}
?>


