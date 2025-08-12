<?php
/**
 * ملف تحديث حالة الشحنة - محدث مع نظام الأمان الجديد
 */

// تضمين نظام الأمان
require_once '../middleware.php';
require_once '../error_handler.php';

// بدء الجلسة مع الحماية
SecurityMiddleware::checkSession();

// التحقق من تسجيل دخول المندوب
SecurityMiddleware::requireUserType(['courier']);

include '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

// قراءة بيانات JSON من الطلب
$input = json_decode(file_get_contents('php://input'), true);
$parcel_id = isset($input['parcel_id']) ? intval($input['parcel_id']) : null;
$new_status = isset($input['new_status']) ? intval($input['new_status']) : null;

if (!$parcel_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
    exit();
}

$courier_id = $_SESSION['courier_id'];

try {
    // تحقق أن الشحنة تخص المندوب
    $stmt = $conn->prepare("SELECT id, status FROM parcels WHERE id = ? AND courier_id = ?");
    $stmt->bind_param("ii", $parcel_id, $courier_id);
    $stmt->execute();
    $parcel = $stmt->get_result()->fetch_assoc();

    if (!$parcel) {
        echo json_encode(['success' => false, 'message' => 'الشحنة غير موجودة أو لا تخصك']);
        exit();
    }

    // تحقق من صحة تغير الحالة (تعديل حسب الحالات لديك)
    $valid_transitions = [
        1 => [2, 3],       // جديد => مُسند أو تم الاستلام (مثال)
        2 => [3],          // مُسند => تم الاستلام
        3 => [4, 5],       // تم الاستلام => قيد النقل أو خارج للتوصيل
        4 => [5],          // قيد النقل => خارج للتوصيل
        5 => [6, 7],       // خارج للتوصيل => تم التوصيل أو فشل التوصيل
        6 => [],           // تم التوصيل => لا يمكن التغيير
        7 => [5],          // فشل التوصيل => إعادة خارج للتوصيل
        8 => [],           // مرتجع أو ملغي => لا يمكن التغيير
        9 => [],           // تعليق، الخ
        10 => []
    ];

    $current_status = intval($parcel['status']);

    if (!isset($valid_transitions[$current_status]) || !in_array($new_status, $valid_transitions[$current_status])) {
        echo json_encode(['success' => false, 'message' => 'تغيير الحالة غير مسموح']);
        exit();
    }

    // تحديث الحالة
    $update_stmt = $conn->prepare("UPDATE parcels SET status = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("ii", $new_status, $parcel_id);

    if ($update_stmt->execute()) {
        // تسجيل السجل
        $log_stmt = $conn->prepare("INSERT INTO parcel_status_logs (parcel_id, courier_id, old_status, new_status, created_at) VALUES (?, ?, ?, ?, NOW())");
        $log_stmt->bind_param("iiii", $parcel_id, $courier_id, $current_status, $new_status);
        $log_stmt->execute();

        echo json_encode(['success' => true, 'message' => 'تم تغيير الحالة بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث الحالة']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
