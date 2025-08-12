<?php
include 'db_connect.php';

echo "<h2>فحص بنية جدول customer_balances</h2>";

// إنشاء الجدول أولاً
$create_sql = "CREATE TABLE IF NOT EXISTS customer_balances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_phone VARCHAR(50) NOT NULL,
    parcel_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'المبلغ المستحق للعميل',
    transaction_type ENUM('payment', 'withdrawal') DEFAULT 'payment',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_phone (customer_phone),
    INDEX idx_parcel_id (parcel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($create_sql)) {
    echo "<p style='color: green;'>✅ تم إنشاء/التحقق من جدول customer_balances</p>";
} else {
    echo "<p style='color: red;'>❌ خطأ في إنشاء الجدول: " . $conn->error . "</p>";
}

// فحص بنية الجدول
$desc_result = $conn->query("DESCRIBE customer_balances");
if ($desc_result) {
    echo "<h3>بنية الجدول:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $desc_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ لم يتم العثور على الجدول</p>";
}

// اختبار إدراج بيانات
echo "<h3>اختبار إدراج بيانات:</h3>";
$test_insert = "INSERT INTO customer_balances (customer_phone, parcel_id, amount, notes) VALUES ('01000613138', 999, 100.50, 'اختبار')";

if ($conn->query($test_insert)) {
    echo "<p style='color: green;'>✅ نجح إدراج البيانات التجريبية</p>";
    
    // حذف البيانات التجريبية
    $conn->query("DELETE FROM customer_balances WHERE parcel_id = 999");
    echo "<p>تم حذف البيانات التجريبية</p>";
} else {
    echo "<p style='color: red;'>❌ فشل إدراج البيانات: " . $conn->error . "</p>";
}

$conn->close();
?>










