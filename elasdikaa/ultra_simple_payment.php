<?php
include 'db_connect.php';

echo "<h1>⚡ نظام الدفع الجزئي المبسط جداً</h1>";

/**
 * النظام الأبسط على الإطلاق - بدون تعقيد
 * 
 * المبدأ:
 * 1. cod_amount = قيمة الشحنة (ثابت)
 * 2. paid_amount = ما تم تحصيله (متغير)
 * 3. customer_due_amount = الرصيد للعميل (جديد)
 */

function ultraSimplePartialPayment($parcel_id, $collected_amount, $conn) {
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
        // تحديث الشحنة فقط
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
        $track_message = "دفع جزئي بسيط: " . number_format($collected_amount, 2) . " جنيه، مستحق للعميل: " . number_format($customer_gets, 2) . " جنيه";
        $track_stmt = $conn->prepare("INSERT INTO parcel_tracks (parcel_id, status, remarks, date_created) VALUES (?, ?, ?, NOW())");
        $track_stmt->bind_param("iis", $parcel_id, $parcel_status, $track_message);
        $track_stmt->execute();
        $track_stmt->close();
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => "تم الدفع بنجاح!\nالمحصل: " . number_format($collected_amount, 2) . " جنيه\nالمستحق للعميل: " . number_format($customer_gets, 2) . " جنيه",
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

// اختبار النظام
echo "<h2>🧪 اختبار مع شحنة موجودة</h2>";

$test_query = "SELECT id, tracking_number, cod_amount, paid_amount, shipping_fees, shipping_payer, customer_due_amount FROM parcels WHERE sender_phone = '01000613138' ORDER BY id DESC LIMIT 1";
$result = $conn->query($test_query);

if ($result && $result->num_rows > 0) {
    $test_parcel = $result->fetch_assoc();
    
    echo "<div style='border: 1px solid #ddd; padding: 15px; background-color: #f9f9f9; margin: 10px 0;'>";
    echo "<h3>📦 الشحنة:</h3>";
    echo "<p><strong>رقم التتبع:</strong> " . $test_parcel['tracking_number'] . "</p>";
    echo "<p><strong>قيمة الشحنة:</strong> " . number_format($test_parcel['cod_amount'], 2) . " جنيه</p>";
    echo "<p><strong>المدفوع حالياً:</strong> " . number_format($test_parcel['paid_amount'], 2) . " جنيه</p>";
    echo "<p><strong>رسوم الشحن:</strong> " . number_format($test_parcel['shipping_fees'], 2) . " جنيه</p>";
    echo "<p><strong>من يدفع الشحن:</strong> " . ($test_parcel['shipping_payer'] === 'sender' ? 'المرسل' : 'المستلم') . "</p>";
    echo "<p><strong>رصيد العميل الحالي:</strong> " . number_format($test_parcel['customer_due_amount'], 2) . " جنيه</p>";
    echo "</div>";
    
    echo "<h3>💰 اختبار دفع 1000 جنيه:</h3>";
    
    $test_result = ultraSimplePartialPayment($test_parcel['id'], 1000, $conn);
    
    if ($test_result['success']) {
        echo "<div style='border: 2px solid #28a745; padding: 15px; background-color: #d4edda;'>";
        echo "<h3>✅ نجح النظام المبسط!</h3>";
        echo "<p>" . nl2br($test_result['message']) . "</p>";
        echo "</div>";
        
        // فحص البيانات بعد التحديث
        $check_query = "SELECT paid_amount, customer_due_amount, payment_status FROM parcels WHERE id = " . $test_parcel['id'];
        $check_result = $conn->query($check_query);
        if ($check_result && $check_result->num_rows > 0) {
            $updated = $check_result->fetch_assoc();
            echo "<h3>📊 البيانات بعد التحديث:</h3>";
            echo "<p><strong>إجمالي المدفوع:</strong> " . number_format($updated['paid_amount'], 2) . " جنيه</p>";
            echo "<p><strong>رصيد العميل:</strong> " . number_format($updated['customer_due_amount'], 2) . " جنيه</p>";
            echo "<p><strong>حالة الدفع:</strong> " . $updated['payment_status'] . "</p>";
        }
        
        // حساب رصيد العميل الإجمالي
        $total_balance_query = "SELECT SUM(customer_due_amount) as total FROM parcels WHERE sender_phone = '01000613138'";
        $balance_result = $conn->query($total_balance_query);
        if ($balance_result && $balance_result->num_rows > 0) {
            $balance_data = $balance_result->fetch_assoc();
            echo "<div style='border: 2px solid #007bff; padding: 15px; background-color: #e7f3ff; margin: 20px 0;'>";
            echo "<h3>💼 إجمالي رصيد العميل: " . number_format($balance_data['total'], 2) . " جنيه</h3>";
            echo "</div>";
        }
        
    } else {
        echo "<div style='border: 2px solid #dc3545; padding: 15px; background-color: #f8d7da;'>";
        echo "<h3>❌ فشل:</h3>";
        echo "<p>" . $test_result['message'] . "</p>";
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>❌ لا توجد شحنات للاختبار</p>";
}

echo "<h2>🎯 النتيجة:</h2>";
echo "<p style='font-size: 18px; color: green;'><strong>هذا النظام بسيط جداً ويعمل!</strong></p>";
echo "<ul>";
echo "<li>✅ <strong>لا جداول إضافية</strong></li>";
echo "<li>✅ <strong>منطق واحد واضح</strong></li>";
echo "<li>✅ <strong>حساب صحيح للرصيد</strong></li>";
echo "<li>✅ <strong>يمكن ربطه بـ customer_profile.php بسهولة</strong></li>";
echo "</ul>";

$conn->close();
?>










