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

// التحقق من وجود ملف
if (!isset($_FILES['sound_file']) || $_FILES['sound_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'لم يتم رفع ملف صوت']);
    exit;
}

$file = $_FILES['sound_file'];

// التحقق من نوع الملف
$allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'نوع الملف غير مدعوم. يرجى رفع ملف MP3, WAV, أو OGG']);
    exit;
}

// التحقق من حجم الملف (أقل من 5 ميجابايت)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'حجم الملف كبير جداً. الحد الأقصى 5 ميجابايت']);
    exit;
}

// إنشاء مجلد الأصوات إذا لم يكن موجود
$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'sounds';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// اسم الملف النهائي
$fileName = 'notification.mp3';
$filePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

// حذف الملف القديم إذا كان موجود
if (file_exists($filePath)) {
    unlink($filePath);
}

// رفع الملف الجديد
if (move_uploaded_file($file['tmp_name'], $filePath)) {
    // تعيين صلاحيات الملف
    chmod($filePath, 0644);
    
    echo json_encode([
        'success' => true, 
        'message' => 'تم رفع الصوت بنجاح',
        'file_name' => $fileName
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'فشل في حفظ الملف']);
}
?>



