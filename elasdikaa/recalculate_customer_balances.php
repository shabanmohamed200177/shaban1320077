<?php
include 'db_connect.php';

echo "<h2>إعادة حساب أرصدة العملاء</h2>";

// الحصول على جميع العملاء
$customers_query = "SELECT id, name, phone, balance FROM customers";
$customers_result = $conn->query($customers_query);

$updated_count = 0;

if ($customers_result->num_rows > 0) {
    while ($customer = $customers_result->fetch_assoc()) {
        $customer_id = $customer['id'];
        $customer_phone = $customer['phone'];
        $old_balance = floatval($customer['balance']);
        
        // حساب الرصيد الجديد من الشحنات باستخدام نفس المنطق
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
        $balance_stmt->bind_param("s", $customer_phone);
        $balance_stmt->execute();
        $balance_result = $balance_stmt->get_result();
        $balance_data = $balance_result->fetch_assoc();
        $new_balance = floatval($balance_data['total_balance'] ?? 0);
        
        // تحديث رصيد العميل إذا كان مختلف
        if (abs($old_balance - $new_balance) > 0.01) {
            $update_stmt = $conn->prepare("UPDATE customers SET balance = ? WHERE id = ?");
            $update_stmt->bind_param("di", $new_balance, $customer_id);
            $update_stmt->execute();
            
            echo "<p>✅ تم تحديث رصيد العميل: <strong>" . htmlspecialchars($customer['name']) . "</strong></p>";
            echo "<ul>";
            echo "<li>الرصيد القديم: " . number_format($old_balance, 2) . " جنيه</li>";
            echo "<li>الرصيد الجديد: " . number_format($new_balance, 2) . " جنيه</li>";
            echo "<li>الفرق: " . number_format($new_balance - $old_balance, 2) . " جنيه</li>";
            echo "</ul>";
            
            $updated_count++;
        }
    }
}

echo "<h3>🎉 تم الانتهاء من إعادة حساب الأرصدة</h3>";
echo "<p><strong>عدد العملاء المُحدَّثين:</strong> $updated_count</p>";

if ($updated_count > 0) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✅ تم إعادة حساب الأرصدة بنجاح!</h4>";
    echo "<p>الآن ستعرض صفحة customer_profile الأرصدة الصحيحة.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>ℹ️ جميع الأرصدة صحيحة بالفعل</h4>";
    echo "<p>لا توجد أرصدة بحاجة للتحديث.</p>";
    echo "</div>";
}

echo "<p><a href='customer_profile.php?id=1' class='btn btn-primary'>اختبار صفحة العميل</a></p>";

?>










