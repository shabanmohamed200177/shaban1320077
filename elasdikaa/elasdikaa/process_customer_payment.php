<?php
// ملف: process_customer_payment.php - معالج دفع العميل

// تفعيل عرض الأخطاء للمساعدة في التطوير
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التأكد من وجود ملف الاتصال بقاعدة البيانات
if (!isset($conn)) {
    include 'db_connect.php';
}

// التحقق من نوع الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'طريقة طلب غير صحيحة']);
    exit;
}

// التحقق من وجود action
if (!isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'لم يتم تحديد الإجراء المطلوب']);
    exit;
}

$action = $_POST['action'];

switch ($action) {
    case 'process_payment':
        processPayment($conn);
        break;
        
    case 'get_parcel_tracking':
        getParcelTracking($conn);
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'إجراء غير معروف']);
        break;
}

// دالة معالجة الدفع
function processPayment($conn) {
    $parcel_id = intval($_POST['parcel_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    
    if ($parcel_id <= 0 || $amount <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'بيانات غير صحيحة'
        ]);
        exit;
    }
    
    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        // جلب معلومات الشحنة
        $parcel_query = "SELECT * FROM parcels WHERE id = ?";
        $parcel_stmt = $conn->prepare($parcel_query);
        $parcel_stmt->bind_param("i", $parcel_id);
        $parcel_stmt->execute();
        $parcel_result = $parcel_stmt->get_result();
        
        if ($parcel_result->num_rows === 0) {
            throw new Exception('الشحنة غير موجودة');
        }
        
        $parcel = $parcel_result->fetch_assoc();
        $client_id = $parcel['client_id_fk'];
        
        if (!$client_id) {
            throw new Exception('الشحنة لا تنتمي لأي عميل');
        }
        
        // حساب المبلغ المتبقي
        $remaining_amount = $parcel['cod_amount'] - ($parcel['paid_amount'] ?? 0);
        
        if ($amount > $remaining_amount) {
            throw new Exception('المبلغ المدفوع أكبر من المبلغ المتبقي');
        }
        
        // تحديث حالة الدفع في الشحنة
        $new_paid_amount = ($parcel['paid_amount'] ?? 0) + $amount;
        $payment_status = ($new_paid_amount >= $parcel['cod_amount']) ? 'paid' : 'partial_paid';
        
        $update_parcel = "UPDATE parcels SET 
            paid_amount = ?, 
            payment_status = ?,
            partial_payment_date = CASE WHEN ? = 'partial_paid' THEN NOW() ELSE partial_payment_date END
            WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_parcel);
        $update_stmt->bind_param("dssi", $new_paid_amount, $payment_status, $payment_status, $parcel_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception('فشل في تحديث حالة الشحنة');
        }
        
        // تسجيل الدفع في جدول customer_payments
        $payment_query = "INSERT INTO customer_payments 
            (customer_id, parcel_id, amount, payment_date, payment_type, notes, created_at) 
            VALUES (?, ?, ?, NOW(), 'cod_payment', ?, NOW())";
        
        $payment_stmt = $conn->prepare($payment_query);
        $payment_stmt->bind_param("iids", $client_id, $parcel_id, $amount, $notes);
        
        if (!$payment_stmt->execute()) {
            throw new Exception('فشل في تسجيل الدفع');
        }
        
        // تحديث رصيد العميل
        $balance_query = "INSERT INTO customer_balances 
            (customer_id, amount, balance_type, transaction_date, description, created_at) 
            VALUES (?, ?, 'credit', NOW(), ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            amount = amount + VALUES(amount),
            updated_at = NOW()";
        
        $balance_description = "دفع شحنة رقم: " . $parcel['tracking_number'];
        $balance_stmt = $conn->prepare($balance_query);
        $balance_stmt->bind_param("ids", $client_id, $amount, $balance_description);
        
        if (!$balance_stmt->execute()) {
            throw new Exception('فشل في تحديث رصيد العميل');
        }
        
        // تسجيل النشاط
        $activity_query = "INSERT INTO customer_activity_log 
            (customer_id, activity_type, description, activity_date, created_at) 
            VALUES (?, 'payment', ?, NOW(), NOW())";
        
        $activity_description = "تم دفع شحنة رقم: " . $parcel['tracking_number'] . " بمبلغ: " . $amount . " جنيه";
        $activity_stmt = $conn->prepare($activity_query);
        $activity_stmt->bind_param("is", $client_id, $activity_description);
        
        if (!$activity_stmt->execute()) {
            throw new Exception('فشل في تسجيل النشاط');
        }
        
        // تأكيد المعاملة
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'تم تسجيل الدفع بنجاح',
            'data' => [
                'parcel_id' => $parcel_id,
                'amount_paid' => $amount,
                'total_paid' => $new_paid_amount,
                'payment_status' => $payment_status,
                'remaining_amount' => $remaining_amount - $amount
            ]
        ]);
        
    } catch (Exception $e) {
        // التراجع عن المعاملة في حالة الخطأ
        $conn->rollback();
        echo json_encode([
            'status' => 'error',
            'message' => 'حدث خطأ أثناء تسجيل الدفع: ' . $e->getMessage()
        ]);
    }
}

// دالة جلب رقم التتبع
function getParcelTracking($conn) {
    $parcel_id = intval($_POST['parcel_id'] ?? 0);
    
    if ($parcel_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'معرف الشحنة غير صحيح'
        ]);
        exit;
    }
    
    $query = "SELECT tracking_number FROM parcels WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $parcel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'الشحنة غير موجودة'
        ]);
        exit;
    }
    
    $parcel = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'tracking_number' => $parcel['tracking_number']
        ]
    ]);
}
?>
