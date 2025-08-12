<?php
/**
 * حاسبة الدفع الجزئي والمبالغ المستحقة للعملاء
 */

class PartialPaymentCalculator {
    
    /**
     * حساب المبلغ المستحق للعميل بعد الدفع الجزئي
     * 
     * @param float $cod_amount قيمة الشحنة الأساسية
     * @param float $paid_amount المبلغ المدفوع جزئياً
     * @param float $shipping_fees رسوم الشحن
     * @param string $shipping_payer من يدفع رسوم الشحن (sender/recipient)
     * @return array
     */
    public static function calculateCustomerDue($cod_amount, $paid_amount, $shipping_fees, $shipping_payer) {
        $cod_amount = floatval($cod_amount);
        $paid_amount = floatval($paid_amount);
        $shipping_fees = floatval($shipping_fees);
        
        // المبلغ المتبقي من قيمة الشحنة
        $remaining_cod = $cod_amount - $paid_amount;
        
        // حساب المبلغ المستحق للعميل
        if ($shipping_payer === 'sender') {
            // إذا كان الشحن على المرسل، نخصم رسوم الشحن من المبلغ المستحق
            $customer_due = $remaining_cod - $shipping_fees;
        } else {
            // إذا كان الشحن على المستلم، المبلغ المستحق هو المتبقي كاملاً
            $customer_due = $remaining_cod;
        }
        
        // التأكد من أن المبلغ المستحق لا يكون سالباً
        $customer_due = max(0, $customer_due);
        
        return [
            'cod_amount' => $cod_amount,
            'paid_amount' => $paid_amount,
            'remaining_cod' => $remaining_cod,
            'shipping_fees' => $shipping_fees,
            'shipping_payer' => $shipping_payer,
            'customer_due' => $customer_due,
            'calculation_breakdown' => self::getCalculationBreakdown($cod_amount, $paid_amount, $shipping_fees, $shipping_payer, $customer_due)
        ];
    }
    
    /**
     * الحصول على تفصيل الحساب
     */
    private static function getCalculationBreakdown($cod_amount, $paid_amount, $shipping_fees, $shipping_payer, $customer_due) {
        $breakdown = [
            'قيمة الشحنة الأساسية' => number_format($cod_amount, 2) . ' جنيه',
            'المبلغ المدفوع جزئياً' => number_format($paid_amount, 2) . ' جنيه',
            'المتبقي من قيمة الشحنة' => number_format($cod_amount - $paid_amount, 2) . ' جنيه'
        ];
        
        if ($shipping_payer === 'sender') {
            $breakdown['رسوم الشحن (مخصومة من المستحق)'] = number_format($shipping_fees, 2) . ' جنيه';
            $breakdown['المبلغ المستحق للعميل'] = number_format($customer_due, 2) . ' جنيه';
            $breakdown['طريقة الحساب'] = '(قيمة الشحنة - المدفوع جزئياً) - رسوم الشحن';
        } else {
            $breakdown['رسوم الشحن (على المستلم)'] = number_format($shipping_fees, 2) . ' جنيه';
            $breakdown['المبلغ المستحق للعميل'] = number_format($customer_due, 2) . ' جنيه';
            $breakdown['طريقة الحساب'] = 'قيمة الشحنة - المدفوع جزئياً';
        }
        
        return $breakdown;
    }
    
    /**
     * تحديث مبلغ الدفع الجزئي في قاعدة البيانات
     */
    public static function updatePartialPayment($conn, $parcel_id, $partial_amount) {
        $parcel_id = intval($parcel_id);
        $partial_amount = floatval($partial_amount);
        
        try {
            $conn->begin_transaction();
            
            // جلب بيانات الشحنة الحالية
            $query = "SELECT cod_amount, shipping_fees, shipping_payer, paid_amount FROM parcels WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $parcel_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("الشحنة غير موجودة");
            }
            
            $parcel = $result->fetch_assoc();
            $current_paid = floatval($parcel['paid_amount'] ?? 0);
            $new_paid_amount = $current_paid + $partial_amount;
            
            // حساب المبلغ المستحق للعميل
            $calculation = self::calculateCustomerDue(
                $parcel['cod_amount'],
                $new_paid_amount,
                $parcel['shipping_fees'],
                $parcel['shipping_payer']
            );
            
            // تحديث الشحنة
            $update_query = "UPDATE parcels SET paid_amount = ?, status = 6 WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("di", $new_paid_amount, $parcel_id);
            $update_stmt->execute();
            
            // إضافة سجل في تتبع الشحنة
            $track_query = "INSERT INTO parcel_tracks (parcel_id, status, remarks, date_created) VALUES (?, 6, ?, NOW())";
            $track_stmt = $conn->prepare($track_query);
            $remarks = "تم دفع مبلغ جزئي: {$partial_amount} جنيه. المبلغ المستحق للعميل: {$calculation['customer_due']} جنيه";
            $track_stmt->bind_param("is", $parcel_id, $remarks);
            $track_stmt->execute();
            
            $conn->commit();
            
            return [
                'success' => true,
                'message' => 'تم تسجيل الدفع الجزئي بنجاح',
                'calculation' => $calculation
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            return [
                'success' => false,
                'message' => 'خطأ في تسجيل الدفع الجزئي: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * جلب تفاصيل الشحنة مع الحسابات
     */
    public static function getParcelFinancialDetails($conn, $parcel_id) {
        $query = "
            SELECT id, tracking_number, cod_amount, shipping_fees, shipping_payer, 
                   paid_amount, status, sender_name, recipient_name
            FROM parcels 
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        $parcel = $result->fetch_assoc();
        
        // حساب التفاصيل المالية
        $calculation = self::calculateCustomerDue(
            $parcel['cod_amount'],
            $parcel['paid_amount'],
            $parcel['shipping_fees'],
            $parcel['shipping_payer']
        );
        
        return array_merge($parcel, $calculation);
    }
}

// اختبار الكلاس
if (isset($_GET['test'])) {
    echo "<h2>اختبار حاسبة الدفع الجزئي</h2>";
    
    // مثال 1: الشحن على المستلم
    echo "<h3>مثال 1: الشحن على المستلم</h3>";
    $test1 = PartialPaymentCalculator::calculateCustomerDue(2500, 1000, 100, 'recipient');
    echo "<pre>" . print_r($test1, true) . "</pre>";
    
    // مثال 2: الشحن على المرسل
    echo "<h3>مثال 2: الشحن على المرسل</h3>";
    $test2 = PartialPaymentCalculator::calculateCustomerDue(2500, 1000, 100, 'sender');
    echo "<pre>" . print_r($test2, true) . "</pre>";
}
?>
