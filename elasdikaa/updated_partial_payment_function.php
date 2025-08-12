<?php
/**
 * النظام المحدث للدفع الجزئي - متوافق مع customer_profile.php
 */

function processUpdatedPartialPayment($parcel_id, $collected_amount, $conn) {
    // جلب بيانات الشحنة
    $stmt = $conn->prepare("SELECT cod_amount, paid_amount, shipping_fees, shipping_payer, tracking_number, sender_phone FROM parcels WHERE id = ?");
    $stmt->bind_param("i", $parcel_id);
    $stmt->execute();
    $parcel = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$parcel) {
        return ['success' => false, 'message' => 'الشحنة غير موجودة'];
    }
    
    // الحسابات البسيطة
    $cod_amount = floatval($parcel['cod_amount']);
    $current_paid = floatval($parcel['paid_amount']);
    $shipping_fees = floatval($parcel['shipping_fees']);
    $shipping_payer = $parcel['shipping_payer'];
    
    // حساب الجديد
    $new_paid_amount = $current_paid + $collected_amount;
    
    // حساب ما يستحقه العميل (القاعدة الوحيدة!)
    if ($shipping_payer === 'sender') {
        // المرسل يدفع → المستحق = المحصل - الشحن
        $customer_gets = max(0, $collected_amount - $shipping_fees);
    } else {
        // المستلم يدفع → المستحق = المحصل (كامل)
        $customer_gets = $collected_amount;
    }
    
    // حالة الدفع
    $payment_status = ($new_paid_amount >= $cod_amount) ? 'paid' : 'partial_paid';
    $parcel_status = ($new_paid_amount >= $cod_amount) ? 5 : 6;
    
    $conn->begin_transaction();
    try {
        // تحديث الشحنة
        $update_sql = "UPDATE parcels SET 
            paid_amount = ?, 
            payment_status = ?, 
            status = ?, 
            customer_due_amount = IFNULL(customer_due_amount, 0) + ?
            WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("dsidi", $new_paid_amount, $payment_status, $parcel_status, $customer_gets, $parcel_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // سجل تتبع
        $track_message = "دفع جزئي: " . number_format($collected_amount, 2) . " جنيه، مستحق للعميل: " . number_format($customer_gets, 2) . " جنيه";
        $track_stmt = $conn->prepare("INSERT INTO parcel_tracks (parcel_id, status, remarks, date_created) VALUES (?, ?, ?, NOW())");
        $track_stmt->bind_param("iis", $parcel_id, $parcel_status, $track_message);
        $track_stmt->execute();
        $track_stmt->close();
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => "تم الدفع بنجاح! المحصل: " . number_format($collected_amount, 2) . " جنيه، مستحق للعميل: " . number_format($customer_gets, 2) . " جنيه",
            'collected' => $collected_amount,
            'customer_gets' => $customer_gets,
            'total_paid' => $new_paid_amount,
            'tracking_number' => $parcel['tracking_number']
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'خطأ: ' . $e->getMessage()];
    }
}
?>