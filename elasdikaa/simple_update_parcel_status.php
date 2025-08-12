<?php
// ملف مؤقت لحل مشكلة stored function
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parcel_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_status = isset($_POST['status_id']) ? intval($_POST['status_id']) : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
    $returned_items = isset($_POST['returned_items']) ? trim($_POST['returned_items']) : '';
    $payment_note = isset($_POST['payment_note']) ? trim($_POST['payment_note']) : '';

    if ($parcel_id <= 0 || $new_status <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'بيانات غير صالحة']);
        exit;
    }

    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        // الحصول على بيانات الشحنة قبل التحديث
        $stmt = $conn->prepare("SELECT * FROM parcels WHERE id = ?");
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $parcel_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$parcel_data) {
            throw new Exception('الشحنة غير موجودة');
        }

        // تحديث حالة الشحنة فقط (بدون stored functions)
        $update_query = "UPDATE parcels SET status = ?";
        $params = [$new_status];
        $types = "i";
        
        // إضافة تحديثات إضافية حسب نوع الحالة
        if ($new_status == 4) {
            // تم التسليم بنجاح
            $cod_amount = floatval($parcel_data['cod_amount']);
            $shipping_fees = floatval($parcel_data['shipping_fees']);
            $shipping_payer = $parcel_data['shipping_payer'] ?? 'sender';
            
            $net_amount = $cod_amount;
            if ($shipping_payer === 'sender') {
                $net_amount -= $shipping_fees;
            }
            
            $update_query .= ", payment_status = 'pending', net_amount_due = ?";
            $params[] = $net_amount;
            $types .= "d";
            
        } elseif ($new_status == 5) {
            // تم الدفع بنجاح (كامل)
            $cod_amount = floatval($parcel_data['cod_amount']);
            $shipping_fees = floatval($parcel_data['shipping_fees']);
            $shipping_payer = $parcel_data['shipping_payer'] ?? 'sender';
            
            $net_amount = $cod_amount;
            if ($shipping_payer === 'sender') {
                $net_amount -= $shipping_fees;
            }
            
            $update_query .= ", payment_status = 'paid', paid_amount = ?, net_amount_due = ?";
            $params[] = $net_amount;
            $params[] = $net_amount;
            $types .= "dd";
            
        } elseif ($new_status == 6 && $partial_amount > 0) {
            // تم الدفع جزئي
            $cod_amount = floatval($parcel_data['cod_amount']);
            $shipping_fees = floatval($parcel_data['shipping_fees']);
            $shipping_payer = $parcel_data['shipping_payer'] ?? 'sender';
            
            $net_amount = $cod_amount;
            if ($shipping_payer === 'sender') {
                $net_amount -= $shipping_fees;
            }
            
            $update_query .= ", payment_status = 'partial_paid', paid_amount = ?, net_amount_due = ?";
            $params[] = $partial_amount;
            $params[] = $net_amount;
            $types .= "dd";
        }
        
        $update_query .= " WHERE id = ?";
        $params[] = $parcel_id;
        $types .= "i";
        
        // تنفيذ التحديث
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception('خطأ في تحديث حالة الشحنة: ' . $stmt->error);
        }
        $stmt->close();

        // إضافة تتبع للحالة الجديدة
        $track_stmt = $conn->prepare("INSERT INTO parcel_tracks (parcel_id, status, date_created) VALUES (?, ?, NOW())");
        $track_stmt->bind_param("ii", $parcel_id, $new_status);
        $track_stmt->execute();
        $track_stmt->close();

        // تحديث رصيد العميل إذا لزم الأمر
        if (in_array($new_status, [4, 5, 6])) {
            updateCustomerBalance($conn, $parcel_data['sender_phone']);
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'تم تحديث حالة الشحنة بنجاح']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'خطأ: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'طريقة الطلب غير صالحة']);
}

// دالة تحديث رصيد العميل
function updateCustomerBalance($conn, $sender_phone) {
    // البحث عن العميل
    $customer_stmt = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
    $customer_stmt->bind_param("s", $sender_phone);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    if ($customer_result->num_rows > 0) {
        $customer = $customer_result->fetch_assoc();
        $customer_id = $customer['id'];
        
        // حساب الرصيد الجديد
        $balance_stmt = $conn->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN payment_status = 'pending' THEN net_amount_due
                    WHEN payment_status = 'partial_paid' THEN net_amount_due - IFNULL(paid_amount, 0)
                    ELSE 0
                END
            ), 0) as total_balance
            FROM parcels 
            WHERE sender_phone = ? AND status = 4 AND net_amount_due > 0
        ");
        $balance_stmt->bind_param("s", $sender_phone);
        $balance_stmt->execute();
        $balance_result = $balance_stmt->get_result();
        $balance_data = $balance_result->fetch_assoc();
        $total_balance = floatval($balance_data['total_balance']);
        
        // تحديث رصيد العميل
        $update_balance_stmt = $conn->prepare("UPDATE customers SET balance = ? WHERE id = ?");
        $update_balance_stmt->bind_param("di", $total_balance, $customer_id);
        $update_balance_stmt->execute();
        
        $balance_stmt->close();
        $update_balance_stmt->close();
    }
    
    $customer_stmt->close();
}

$conn->close();
?>
