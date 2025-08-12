<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db_connect.php';

// منع عرض الأخطاء في JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    SecurityMiddleware::checkSession();
    
    if (!isset($_SESSION['courier_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $status_id = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 0;
    if ($status_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'invalid status_id']);
        exit;
    }

    // استعلام قاعدة البيانات للحصول على الأسباب
    $stmt = $conn->prepare('SELECT id, reason_code, reason_text FROM parcel_status_reasons WHERE status_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC');
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }
    
    $stmt->bind_param('i', $status_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if (!$res) {
        throw new Exception('Failed to execute query');
    }
    
    $list = [];
    while ($row = $res->fetch_assoc()) { 
        $list[] = $row; 
    }
    $stmt->close();

    // إذا لم توجد أسباب في قاعدة البيانات، استخدم أسباب افتراضية
    if (empty($list)) {
        if ($status_id == 7) { // أسباب التأجيل
            $list = [
                ['id' => 1, 'reason_code' => 'POSTPONE_001', 'reason_text' => 'المستلم غير متاح'],
                ['id' => 2, 'reason_code' => 'POSTPONE_002', 'reason_text' => 'العنوان غير صحيح'],
                ['id' => 3, 'reason_code' => 'POSTPONE_003', 'reason_text' => 'المستلم رفض الاستلام'],
                ['id' => 4, 'reason_code' => 'POSTPONE_004', 'reason_text' => 'مشكلة في الدفع'],
                ['id' => 5, 'reason_code' => 'POSTPONE_005', 'reason_text' => 'أسباب أخرى']
            ];
        } elseif ($status_id == 8) { // أسباب الرفض
            $list = [
                ['id' => 1, 'reason_code' => 'REJECT_001', 'reason_text' => 'المستلم رفض الاستلام'],
                ['id' => 2, 'reason_code' => 'REJECT_002', 'reason_text' => 'العنوان غير صحيح'],
                ['id' => 3, 'reason_code' => 'REJECT_003', 'reason_text' => 'المستلم غير موجود'],
                ['id' => 4, 'reason_code' => 'REJECT_004', 'reason_text' => 'مشكلة في الدفع'],
                ['id' => 5, 'reason_code' => 'REJECT_005', 'reason_text' => 'أسباب أخرى']
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => $list, 'source' => 'database']);
    
} catch (Exception $e) {
    // في حالة حدوث خطأ، استخدم أسباب افتراضية
    $default_reasons = [];
    
    if ($status_id == 7) { // أسباب التأجيل
        $default_reasons = [
            ['id' => 1, 'reason_code' => 'POSTPONE_001', 'reason_text' => 'المستلم غير متاح'],
            ['id' => 2, 'reason_code' => 'POSTPONE_002', 'reason_text' => 'العنوان غير صحيح'],
            ['id' => 3, 'reason_code' => 'POSTPONE_003', 'reason_text' => 'المستلم رفض الاستلام'],
            ['id' => 4, 'reason_code' => 'POSTPONE_004', 'reason_text' => 'مشكلة في الدفع'],
            ['id' => 5, 'reason_code' => 'POSTPONE_005', 'reason_text' => 'أسباب أخرى']
        ];
    } elseif ($status_id == 8) { // أسباب الرفض
        $default_reasons = [
            ['id' => 1, 'reason_code' => 'REJECT_001', 'reason_text' => 'المستلم رفض الاستلام'],
            ['id' => 2, 'reason_code' => 'REJECT_002', 'reason_text' => 'العنوان غير صحيح'],
            ['id' => 3, 'reason_code' => 'REJECT_003', 'reason_text' => 'المستلم غير موجود'],
            ['id' => 4, 'reason_code' => 'REJECT_004', 'reason_text' => 'مشكلة في الدفع'],
            ['id' => 5, 'reason_code' => 'REJECT_005', 'reason_text' => 'أسباب أخرى']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $default_reasons, 'source' => 'fallback', 'error' => $e->getMessage()]);
}
exit;


