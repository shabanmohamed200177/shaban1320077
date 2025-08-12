<?php
/**
 * نظام دفع جزئي موحد - حل نهائي ومباشر
 * يعمل من أي مكان في النظام بنفس الطريقة
 */

function processPartialPayment($parcel_id, $collected_amount, $conn) {
    // جلب بيانات الشحنة
    $query = "SELECT cod_amount, paid_amount, shipping_fees, shipping_payer, tracking_number, sender_phone FROM parcels WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $parcel_id);
    $stmt->execute();
    $parcel = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$parcel) {
        return ['success' => false, 'message' => 'الشحنة غير موجودة'];
    }
    
    $cod_amount = floatval($parcel['cod_amount']);
    $current_paid = floatval($parcel['paid_amount']);
    $shipping_fees = floatval($parcel['shipping_fees']);
    $shipping_payer = $parcel['shipping_payer'];
    
    // حساب المبلغ الجديد
    $new_paid_amount = $current_paid + $collected_amount;
    
    // حساب المبلغ المستحق للعميل (الأهم!)
    if ($shipping_payer === 'sender') {
        // المرسل يدفع الشحن: المستحق = المبلغ المُحصل - رسوم الشحن
        $customer_due_amount = max(0, $collected_amount - $shipping_fees);
    } else {
        // المستلم يدفع الشحن: المستحق = المبلغ المُحصل
        $customer_due_amount = $collected_amount;
    }
    
    // تحديد حالة الدفع
    $payment_status = ($new_paid_amount >= $cod_amount) ? 'paid' : 'partial_paid';
    $parcel_status = ($new_paid_amount >= $cod_amount) ? 5 : 6;
    
    $conn->begin_transaction();
    try {
        // تحديث الشحنة
        $update_query = "UPDATE parcels SET 
            paid_amount = ?, 
            payment_status = ?, 
            status = ?, 
            customer_due_amount = IFNULL(customer_due_amount, 0) + ?
            WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("dsidi", $new_paid_amount, $payment_status, $parcel_status, $customer_due_amount, $parcel_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // إضافة سجل تتبع
        $track_message = "دفع جزئي: {$collected_amount} جنيه. المستحق للعميل: {$customer_due_amount} جنيه";
        $track_query = "INSERT INTO parcel_tracks (parcel_id, status, remarks, date_created) VALUES (?, ?, ?, NOW())";
        $track_stmt = $conn->prepare($track_query);
        $track_stmt->bind_param("iis", $parcel_id, $parcel_status, $track_message);
        $track_stmt->execute();
        $track_stmt->close();
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => "تم الدفع الجزئي بنجاح!\nالمُحصل: {$collected_amount} جنيه\nالمستحق للعميل: {$customer_due_amount} جنيه",
            'customer_due' => $customer_due_amount,
            'total_paid' => $new_paid_amount,
            'tracking_number' => $parcel['tracking_number']
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'خطأ في المعالجة: ' . $e->getMessage()];
    }
}

// اختبار الدالة إذا تم استدعاؤها مباشرة
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_payment'])) {
    include 'db_connect.php';
    
    $parcel_id = intval($_POST['parcel_id']);
    $amount = floatval($_POST['amount']);
    
    $result = processPartialPayment($parcel_id, $amount, $conn);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>
