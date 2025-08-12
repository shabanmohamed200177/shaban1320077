<?php
include 'db_connect.php';

echo "<h2>فحص أعمدة updated_at</h2>";

// فحص جدول customer_payments
$result1 = $conn->query("SHOW COLUMNS FROM customer_payments LIKE 'updated_at'");
echo "جدول customer_payments - عمود updated_at: ";
if ($result1 && $result1->num_rows > 0) {
    echo "✅ موجود<br>";
} else {
    echo "❌ غير موجود<br>";
    
    // إضافة العمود
    echo "جاري إضافة العمود...<br>";
    $add_sql = "ALTER TABLE customer_payments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    if ($conn->query($add_sql)) {
        echo "✅ تم إضافة عمود updated_at لجدول customer_payments<br>";
    } else {
        echo "❌ فشل إضافة العمود: " . $conn->error . "<br>";
    }
}

// فحص جدول parcels
$result2 = $conn->query("SHOW COLUMNS FROM parcels LIKE 'updated_at'");
echo "جدول parcels - عمود updated_at: ";
if ($result2 && $result2->num_rows > 0) {
    echo "✅ موجود<br>";
} else {
    echo "❌ غير موجود<br>";
    
    // إضافة العمود
    echo "جاري إضافة العمود...<br>";
    $add_sql = "ALTER TABLE parcels ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    if ($conn->query($add_sql)) {
        echo "✅ تم إضافة عمود updated_at لجدول parcels<br>";
    } else {
        echo "❌ فشل إضافة العمود: " . $conn->error . "<br>";
    }
}

echo "<br><h3>تم الانتهاء من الفحص</h3>";
echo "<p><a href='customer_profile.php?id=1'>اختبار صفحة Customer Profile مرة أخرى</a></p>";

$conn->close();
?>
