<?php
// 🚀 معالج AJAX منفصل لتحديث حالة الشحنة - حل نهائي
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // التأكد من أن الطلب POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صالحة');
    }
    
    // التأكد من وجود الإجراء المطلوب
    if (!isset($_POST['action']) || $_POST['action'] !== 'change_status') {
        throw new Exception('إجراء غير صالح');
    }
    
    // اتصال جديد ومنفصل بقاعدة البيانات
    include 'db_connect.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'PaymentService.php';
    
    // استخراج البيانات
    $parcel_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 0;
    $remarks = $_POST['remarks'] ?? '';
    $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
    $returned_items = $_POST['returned_items'] ?? '';
    $payment_note = $_POST['payment_note'] ?? '';
    
    // التحقق من صحة البيانات
    if ($parcel_id <= 0) {
        throw new Exception('معرف الشحنة غير صالح');
    }
    
    if ($new_status <= 0) {
        throw new Exception('حالة الشحنة غير صالحة');
    }
    
    // الحصول على بيانات الشحنة الحالية
    $parcel_query = "SELECT id, tracking_number, status, cod_amount, shipping_fees, shipping_payer, sender_phone FROM parcels WHERE id = ?";
    $stmt = $conn->prepare($parcel_query);
    $stmt->bind_param("i", $parcel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $parcel_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$parcel_data) {
        throw new Exception('الشحنة غير موجودة');
    }
    
    // إذا كانت الحالة المختارة هي دفع جزئي (6) ومعه مبلغ، نستخدم خدمة الدفع الموحّدة
    if ($new_status === 6 && $partial_amount > 0) {
        try {
            // جلب المندوب المرتبط بالشحنة – لتمييز التحصيل إن وجد
            $courier_id_for_collection = null;
            if ($cstmt = $conn->prepare('SELECT courier_id FROM parcels WHERE id = ?')) {
                $cstmt->bind_param('i', $parcel_id);
                $cstmt->execute();
                $cres = $cstmt->get_result()->fetch_assoc();
                if ($cres && isset($cres['courier_id'])) {
                    $courier_id_for_collection = (int)$cres['courier_id'] ?: null;
                }
                $cstmt->close();
            }
            $adminName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'admin';
            $result = PaymentService::applyPartialPayment($conn, $parcel_id, $partial_amount, 'admin', $payment_note, $courier_id_for_collection, $adminName);

            // رسالة نجاح موحّدة
            $message = 'تم تسجيل الدفع الجزئي: ' . number_format($result['payment_amount'], 2) . ' جنيه. إجمالي المدفوع: ' . number_format($result['total_paid'], 2) . ' جنيه. المتبقي: ' . number_format($result['remaining_balance'], 2) . ' جنيه';
            echo json_encode([
                'status' => 'success',
                'message' => $message,
                'parcel_id' => $parcel_id,
                'new_status' => $result['new_status'],
                'tracking_number' => $parcel_data['tracking_number'],
                'data' => $result
            ]);
            exit;
        } catch (Throwable $e) {
            throw new Exception('فشل تسجيل الدفع الجزئي: ' . $e->getMessage());
        }
    }

    // غير ذلك: مجرّد تحديث حالة + تتبع
    $conn->begin_transaction();
    $update_query = "UPDATE parcels SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $new_status, $parcel_id);
    if (!$update_stmt->execute()) {
        throw new Exception('فشل في تحديث الشحنة: ' . $update_stmt->error);
    }
    $affected_rows = $update_stmt->affected_rows;
    $update_stmt->close();
    if ($affected_rows === 0) {
        throw new Exception('لم يتم تحديث أي سجل');
    }
    $track_query = "INSERT INTO parcel_tracks (parcel_id, status, remarks, date_created) VALUES (?, ?, ?, NOW())";
    $track_stmt = $conn->prepare($track_query);
    $track_stmt->bind_param("iis", $parcel_id, $new_status, $remarks);
    if (!$track_stmt->execute()) {
        throw new Exception('فشل في إضافة سجل التتبع: ' . $track_stmt->error);
    }
    $track_stmt->close();
    $conn->commit();
    
    // أسماء الحالات
    $status_names = [
        1 => 'قيد التنفيذ',
        2 => 'تم تسليمها للمندوب',
        3 => 'جاري التوصيل',
        4 => 'تم التسليم بنجاح',
        5 => 'تم الدفع بنجاح',
        6 => 'تم الدفع جزئي',
        7 => 'تم تأجيل الطلب',
        8 => 'تم الرفض',
        9 => 'المرتجعات'
    ];
    
    $status_name = $status_names[$new_status] ?? "حالة غير معروفة";
    $message = "تم تحديث حالة الشحنة #{$parcel_data['tracking_number']} إلى: $status_name";
    
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'parcel_id' => $parcel_id,
        'new_status' => $new_status,
        'tracking_number' => $parcel_data['tracking_number']
    ]);
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    
    // تسجيل الخطأ
    error_log("Status Update Error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// إغلاق الاتصال
if (isset($conn) && $conn) {
    $conn->close();
}
?>
