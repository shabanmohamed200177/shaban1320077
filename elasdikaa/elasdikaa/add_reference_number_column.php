<?php
include 'db_connect.php';

echo "<h2>إضافة عمود reference_number لجدول customer_payments</h2>";

// فحص وجود العمود
$check = $conn->query("SHOW COLUMNS FROM customer_payments LIKE 'reference_number'");

if ($check && $check->num_rows > 0) {
    echo "✅ عمود reference_number موجود بالفعل<br>";
} else {
    echo "❌ عمود reference_number غير موجود - جاري الإضافة...<br>";
    
    $add_sql = "ALTER TABLE customer_payments ADD COLUMN reference_number VARCHAR(255) NULL AFTER payment_method";
    
    if ($conn->query($add_sql)) {
        echo "✅ تم إضافة عمود reference_number بنجاح<br>";
    } else {
        echo "❌ فشل إضافة العمود: " . $conn->error . "<br>";
    }
}

// إضافة أو تحديث قائمة طرق الدفع المدعومة
echo "<br><h3>طرق الدفع المدعومة الآن:</h3>";
echo "<ul>";
echo "<li><strong>cash</strong> - دفع نقدي</li>";
echo "<li><strong>instapay</strong> - انستا باي</li>";
echo "<li><strong>vodafone_cash</strong> - فودافون كاش</li>";
echo "<li><strong>bank_transfer</strong> - تحويل بنكي</li>";
echo "<li><strong>partial_payment</strong> - دفع جزئي (القديم)</li>";
echo "</ul>";

echo "<br><h3>تم الانتهاء من التحديث</h3>";
echo "<p><a href='customer_profile.php?id=1'>اختبار نظام الدفع المحدث</a></p>";

$conn->close();
?>
