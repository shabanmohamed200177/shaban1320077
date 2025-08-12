<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';

// التحقق من تسجيل الدخول
SecurityMiddleware::checkSession();
if (!isset($_SESSION['courier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

// مسار ملف الصوت
$filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'sounds' . DIRECTORY_SEPARATOR . 'notification.mp3';

// حذف الملف إذا كان موجود
if (file_exists($filePath)) {
    if (unlink($filePath)) {
        echo json_encode(['success' => true, 'message' => 'تم حذف الصوت المخصص']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في حذف الملف']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'لا يوجد صوت مخصص لحذفه']);
}
?>



