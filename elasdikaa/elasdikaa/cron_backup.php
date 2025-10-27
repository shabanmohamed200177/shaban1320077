<?php
/**
 * مهمة cron للنسخ الاحتياطية التلقائية - شركة الأصدقاء
 * Cron Job for Automatic Backups - Alasdeqaa Company
 */

// تشغيل هذا الملف كل 12 ساعة عبر cron job
// مثال: 0 */12 * * * /usr/bin/php /path/to/your/project/cron_backup.php

// إعداد البيئة
set_time_limit(0); // بدون حد زمني
ini_set('memory_limit', '512M'); // زيادة الذاكرة

// تضمين الملفات المطلوبة
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/backup_system.php';

// تسجيل بداية المهمة
$log_file = __DIR__ . '/logs/cron_backup.log';
$log_dir = dirname($log_file);

// إنشاء مجلد السجلات إذا لم يكن موجوداً
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

writeLog("بدء مهمة النسخ الاحتياطية التلقائية");

try {
    // محاكاة جلسة المدير للنسخ التلقائية
    session_start();
    $_SESSION['login_id'] = 1; // افتراض أن المدير له ID = 1
    $_SESSION['login_type'] = 1;
    
    // إنشاء كائن نظام النسخ الاحتياطية
    $backupSystem = new BackupSystem($conn);
    
    // تشغيل النسخ التلقائية
    $result = $backupSystem->scheduleAutomaticBackups();
    
    if ($result['status'] === 'success') {
        writeLog("تم إنشاء نسخة احتياطية تلقائية بنجاح: " . $result['backup_name']);
        
        // إضافة إشعار للمديرين
        if (class_exists('NotificationSystem')) {
            $notificationSystem = new NotificationSystem($conn);
            $notificationSystem->addNotification([
                'user_id' => null,
                'is_global' => 1,
                'title' => 'نسخة احتياطية تلقائية',
                'message' => 'تم إنشاء نسخة احتياطية تلقائية بنجاح بحجم ' . $backupSystem->formatFileSize($result['file_size'] ?? 0),
                'type' => 'success',
                'icon' => 'fas fa-database',
                'category' => 'backup',
                'priority' => 'low'
            ]);
        }
        
    } elseif ($result['status'] === 'info') {
        writeLog("لا حاجة لنسخة احتياطية الآن: " . $result['message']);
        
    } else {
        writeLog("فشل في النسخة الاحتياطية التلقائية: " . $result['message']);
        
        // إضافة إشعار خطأ
        if (class_exists('NotificationSystem')) {
            $notificationSystem = new NotificationSystem($conn);
            $notificationSystem->addNotification([
                'user_id' => null,
                'is_global' => 1,
                'title' => 'فشل في النسخة الاحتياطية التلقائية',
                'message' => $result['message'],
                'type' => 'danger',
                'icon' => 'fas fa-exclamation-circle',
                'category' => 'backup',
                'priority' => 'high'
            ]);
        }
    }
    
    // تنظيف الإشعارات القديمة
    if (class_exists('NotificationSystem')) {
        $notificationSystem = new NotificationSystem($conn);
        $cleanupResult = $notificationSystem->cleanupNotifications();
        writeLog("تنظيف الإشعارات: " . $cleanupResult['message']);
    }
    
} catch (Exception $e) {
    writeLog("خطأ في مهمة النسخ الاحتياطية: " . $e->getMessage());
    
    // إضافة إشعار خطأ حرج
    try {
        if (class_exists('NotificationSystem')) {
            $notificationSystem = new NotificationSystem($conn);
            $notificationSystem->addNotification([
                'user_id' => null,
                'is_global' => 1,
                'title' => 'خطأ حرج في النسخ الاحتياطية',
                'message' => 'حدث خطأ أثناء تنفيذ مهمة النسخ الاحتياطية التلقائية: ' . $e->getMessage(),
                'type' => 'danger',
                'icon' => 'fas fa-exclamation-triangle',
                'category' => 'system',
                'priority' => 'urgent'
            ]);
        }
    } catch (Exception $notificationError) {
        writeLog("فشل في إرسال إشعار الخطأ: " . $notificationError->getMessage());
    }
    
} finally {
    // تنظيف الجلسة
    session_destroy();
    writeLog("انتهاء مهمة النسخ الاحتياطية التلقائية");
}

// إضافة معلومات الاستخدام
$memory_usage = memory_get_peak_usage(true);
$execution_time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];

writeLog("استخدام الذاكرة: " . round($memory_usage / 1024 / 1024, 2) . " MB");
writeLog("وقت التنفيذ: " . round($execution_time, 2) . " ثانية");
writeLog("---");

// إذا تم تشغيله من المتصفح (للاختبار)
if (isset($_GET['test']) && $_GET['test'] === '1') {
    echo "<h2>اختبار مهمة النسخ الاحتياطية التلقائية</h2>";
    echo "<p>تم تنفيذ المهمة بنجاح. راجع ملف السجل للتفاصيل.</p>";
    echo "<p>ملف السجل: <code>{$log_file}</code></p>";
    
    // عرض آخر 20 سطر من السجل
    if (file_exists($log_file)) {
        $log_lines = file($log_file);
        $recent_logs = array_slice($log_lines, -20);
        
        echo "<h3>آخر 20 سجل:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
        echo htmlspecialchars(implode('', $recent_logs));
        echo "</pre>";
    }
    
    echo "<p><a href='backup_system.php'>عرض النسخ الاحتياطية</a></p>";
}
?>