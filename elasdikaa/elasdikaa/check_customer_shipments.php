<?php
include 'db_connect.php';

$customer_id = 28;

// فحص بيانات العميل
$result = $conn->query("SELECT id, name, phone FROM customers WHERE id = $customer_id");
$customer = $result->fetch_assoc();

echo "<h2>فحص ربط العميل بالشحنات</h2>";
echo "<h3>بيانات العميل:</h3>";
echo "ID: " . $customer['id'] . "<br>";
echo "الاسم: " . $customer['name'] . "<br>";
echo "الهاتف: " . $customer['phone'] . "<br><br>";

// فحص الشحنات المرتبطة
$shipments_query = "SELECT tracking_number, sender_phone, cod_amount, paid_amount, shipping_fees, shipping_payer, status, payment_status FROM parcels WHERE sender_phone = '{$customer['phone']}'";
$shipments_result = $conn->query($shipments_query);

echo "<h3>الشحنات المرتبطة بهذا العميل:</h3>";
echo "عدد الشحنات: " . $shipments_result->num_rows . "<br><br>";

if ($shipments_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>رقم التتبع</th><th>قيمة الشحنة</th><th>المدفوع</th><th>رسوم الشحن</th><th>من يدفع</th><th>الحالة</th><th>حالة الدفع</th></tr>";
    
    while ($shipment = $shipments_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $shipment['tracking_number'] . "</td>";
        echo "<td>" . $shipment['cod_amount'] . "</td>";
        echo "<td>" . $shipment['paid_amount'] . "</td>";
        echo "<td>" . $shipment['shipping_fees'] . "</td>";
        echo "<td>" . $shipment['shipping_payer'] . "</td>";
        echo "<td>" . $shipment['status'] . "</td>";
        echo "<td>" . $shipment['payment_status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>










