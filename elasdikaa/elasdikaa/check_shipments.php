<?php
include 'db_connect.php';

echo "<h2>فحص الشحنات في قاعدة البيانات</h2>";

// البحث عن أي شحنات موجودة
$query = "SELECT tracking_number, cod_amount, paid_amount, shipping_fees, shipping_payer, payment_status, sender_phone FROM parcels LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>الشحنات الموجودة:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>رقم التتبع</th><th>قيمة الشحنة</th><th>المدفوع</th><th>رسوم الشحن</th><th>من يدفع الشحن</th><th>حالة الدفع</th><th>هاتف المرسل</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['tracking_number'] . "</td>";
        echo "<td>" . $row['cod_amount'] . "</td>";
        echo "<td>" . $row['paid_amount'] . "</td>";
        echo "<td>" . $row['shipping_fees'] . "</td>";
        echo "<td>" . $row['shipping_payer'] . "</td>";
        echo "<td>" . $row['payment_status'] . "</td>";
        echo "<td>" . $row['sender_phone'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>لا توجد شحنات في قاعدة البيانات!</p>";
}

// فحص العملاء أيضاً
echo "<h3>العملاء الموجودون:</h3>";
$customers_query = "SELECT id, name, phone, balance FROM customers LIMIT 5";
$customers_result = $conn->query($customers_query);

if ($customers_result && $customers_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>الاسم</th><th>الهاتف</th><th>الرصيد</th></tr>";
    
    while ($row = $customers_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . $row['phone'] . "</td>";
        echo "<td>" . $row['balance'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>لا يوجد عملاء في قاعدة البيانات!</p>";
}

$conn->close();
?>










