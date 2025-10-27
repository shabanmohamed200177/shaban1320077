<?php
// 🔧 FINAL DIRECT STATUS UPDATE - معالج نهائي لتحديث حالة الشحنات
// بدون أي stored functions أو triggers

// تفعيل عرض الأخطاء للتصحيح
ini_set('display_errors', 1);
error_reporting(E_ALL);

// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// التأكد من وجود الاتصال
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'خطأ في الاتصال بقاعدة البيانات']);
    exit;
}

// التأكد من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'طريقة الطلب غير صالحة']);
    exit;
}

// استخراج البيانات من POST
$action = $_POST['action'] ?? '';
$parcel_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$new_status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 0;
$remarks = $_POST['remarks'] ?? '';
$partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
$returned_items = $_POST['returned_items'] ?? '';
$payment_note = $_POST['payment_note'] ?? '';

// التحقق من صحة البيانات الأساسية
if ($action !== 'change_status') {
    echo json_encode(['status' => 'error', 'message' => 'إجراء غير صالح']);
    exit;
}

if ($parcel_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'معرف الشحنة غير صالح']);
    exit;
}

if ($new_status <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'حالة الشحنة غير صالحة']);
    exit;
}

// بدء معاملة قاعدة البيانات
$conn->begin_transaction();

try {
    // الحصول على بيانات الشحنة الحالية
    $parcel_query = "SELECT id, tracking_number, status, cod_amount, shipping_fees, shipping_payer, sender_phone FROM parcels WHERE id = ?";
    $stmt = $conn->prepare($parcel_query);
    
    if (!$stmt) {
        throw new Exception('خطأ في تحضير استعلام البيانات: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $parcel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $parcel_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$parcel_data) {
        throw new Exception('الشحنة غير موجودة');
    }
    
    // استخراج البيانات المالية
    $cod_amount = floatval($parcel_data['cod_amount'] ?? 0);
    $shipping_fees = floatval($parcel_data['shipping_fees'] ?? 0);
    $shipping_payer = $parcel_data['shipping_payer'] ?? 'sender';
    $sender_phone = $parcel_data['sender_phone'] ?? '';
    
    // حساب المبلغ الصافي
    $net_amount = $cod_amount;
    if ($shipping_payer === 'sender') {
        $net_amount = max(0, $cod_amount - $shipping_fees);
    }
    
    // تحضير استعلام التحديث حسب نوع الحالة
    $update_query = "UPDATE parcels SET status = ?";
    $update_params = [$new_status];
    $update_types = "i";
    
    // إضافة الحقول المالية حسب الحالة
    if ($new_status == 4) {
        // حالة: تم التسليم بنجاح
        $update_query .= ", payment_status = 'pending', net_amount_due = ?";
        $update_params[] = $net_amount;
        $update_types .= "d";
        
    } elseif ($new_status == 5) {
        // حالة: تم الدفع بنجاح (كامل)
        $update_query .= ", payment_status = 'paid', paid_amount = ?, net_amount_due = ?";
        $update_params[] = $net_amount;
        $update_params[] = $net_amount;
        $update_types .= "dd";
        
    } elseif ($new_status == 6) {
        // حالة: تم الدفع جزئي
        if ($partial_amount <= 0) {
            throw new Exception('يجب إدخال مبلغ صالح للدفع الجزئي');
        }
        
        if ($partial_amount > $net_amount) {
            throw new Exception('المبلغ المدفوع لا يمكن أن يكون أكبر من المبلغ الإجمالي');
        }
        
        $update_query .= ", payment_status = 'partial_paid', paid_amount = ?, net_amount_due = ?";
        $update_params[] = $partial_amount;
        $update_params[] = $net_amount;
        $update_types .= "dd";
    }
    
    // إضافة شرط WHERE
    $update_query .= " WHERE id = ?";
    $update_params[] = $parcel_id;
    $update_types .= "i";
    
    // تنفيذ تحديث الشحنة
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        throw new Exception('خطأ في تحضير استعلام التحديث: ' . $conn->error);
    }
    
    $update_stmt->bind_param($update_types, ...$update_params);
    
    if (!$update_stmt->execute()) {
        throw new Exception('فشل في تحديث الشحنة: ' . $update_stmt->error);
    }
    
    $affected_rows = $update_stmt->affected_rows;
    $update_stmt->close();
    
    if ($affected_rows === 0) {
        throw new Exception('لم يتم تحديث أي سجل - تحقق من معرف الشحنة');
    }
    
    // إضافة سجل تتبع
    $track_query = "INSERT INTO parcel_tracks (parcel_id, status, remarks, date_created) VALUES (?, ?, ?, NOW())";
    $track_stmt = $conn->prepare($track_query);
    
    if (!$track_stmt) {
        throw new Exception('خطأ في تحضير استعلام التتبع: ' . $conn->error);
    }
    
    $track_stmt->bind_param("iis", $parcel_id, $new_status, $remarks);
    
    if (!$track_stmt->execute()) {
        throw new Exception('فشل في إضافة سجل التتبع: ' . $track_stmt->error);
    }
    
    $track_stmt->close();
    
    // تحديث رصيد العميل إذا كانت الحالة تستدعي ذلك
    if (in_array($new_status, [4, 5, 6]) && !empty($sender_phone)) {
        updateCustomerBalance($conn, $sender_phone);
    }
    
    // إضافة معلومات الدفع الجزئي إذا لزم الأمر
    if ($new_status == 6 && $partial_amount > 0) {
        recordPartialPayment($conn, $parcel_id, $sender_phone, $partial_amount, $payment_note);
    }
    
    // تأكيد المعاملة
    $conn->commit();
    
    // تحضير رسالة النجاح
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
    $message = "تم تحديث حالة الشحنة إلى: $status_name";
    
    if ($new_status == 6 && $partial_amount > 0) {
        $message .= " - المبلغ المدفوع: " . number_format($partial_amount, 2) . " جنيه";
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'parcel_id' => $parcel_id,
        'new_status' => $new_status,
        'net_amount' => $net_amount,
        'paid_amount' => ($new_status == 6) ? $partial_amount : (($new_status == 5) ? $net_amount : 0)
    ]);
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ
    $conn->rollback();
    
    // تسجيل الخطأ في log
    error_log("Status Update Error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'parcel_id' => $parcel_id,
        'attempted_status' => $new_status
    ]);
}

// دالة تحديث رصيد العميل
function updateCustomerBalance($conn, $sender_phone) {
    if (empty($sender_phone)) return false;
    
    try {
        // العثور على العميل
        $customer_stmt = $conn->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
        $customer_stmt->bind_param("s", $sender_phone);
        $customer_stmt->execute();
        $customer_result = $customer_stmt->get_result();
        
        if ($customer_result->num_rows > 0) {
            $customer = $customer_result->fetch_assoc();
            $customer_id = $customer['id'];
            
            // حساب الرصيد الجديد من الشحنات بنفس منطق PartialPaymentCalculator
            $balance_query = "
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN payment_status = 'paid' THEN 0
                        ELSE 
                            CASE 
                                WHEN IFNULL(shipping_payer, 'sender') = 'sender' THEN 
                                    GREATEST(0, (IFNULL(cod_amount, 0) - IFNULL(paid_amount, 0)) - IFNULL(shipping_fees, 0))
                                ELSE 
                                    GREATEST(0, IFNULL(cod_amount, 0) - IFNULL(paid_amount, 0))
                            END
                    END
                ), 0) as total_balance
                FROM parcels 
                WHERE sender_phone = ? AND status IN (4, 5, 6)
            ";
            
            $balance_stmt = $conn->prepare($balance_query);
            $balance_stmt->bind_param("s", $sender_phone);
            $balance_stmt->execute();
            $balance_result = $balance_stmt->get_result();
            $balance_data = $balance_result->fetch_assoc();
            $total_balance = floatval($balance_data['total_balance'] ?? 0);
            
            // تحديث رصيد العميل
            $update_balance_stmt = $conn->prepare("UPDATE customers SET balance = ? WHERE id = ?");
            $update_balance_stmt->bind_param("di", $total_balance, $customer_id);
            $update_balance_stmt->execute();
            
            $balance_stmt->close();
            $update_balance_stmt->close();
        }
        
        $customer_stmt->close();
        return true;
        
    } catch (Exception $e) {
        error_log("Customer Balance Update Error: " . $e->getMessage());
        return false;
    }
}

// دالة تسجيل الدفع الجزئي
function recordPartialPayment($conn, $parcel_id, $sender_phone, $amount, $note) {
    try {
        // العثور على العميل
        $customer_stmt = $conn->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
        $customer_stmt->bind_param("s", $sender_phone);
        $customer_stmt->execute();
        $customer_result = $customer_stmt->get_result();
        
        if ($customer_result->num_rows > 0) {
            $customer = $customer_result->fetch_assoc();
            $customer_id = $customer['id'];
            
            // تسجيل الدفعة
            $payment_stmt = $conn->prepare("
                INSERT INTO customer_payments 
                (customer_id, parcel_id, amount, payment_type, notes, created_at) 
                VALUES (?, ?, ?, 'partial', ?, NOW())
            ");
            $payment_stmt->bind_param("iids", $customer_id, $parcel_id, $amount, $note);
            $payment_stmt->execute();
            $payment_stmt->close();
        }
        
        $customer_stmt->close();
        return true;
        
    } catch (Exception $e) {
        error_log("Partial Payment Record Error: " . $e->getMessage());
        return false;
    }
}

// لا نغلق الاتصال هنا لأن الملف قد يكون مُضمناً من ملف آخر
// $conn->close();
?>
