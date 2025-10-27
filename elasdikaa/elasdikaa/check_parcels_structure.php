<?php
include 'db_connect.php';

echo "<h2>فحص هيكل جدول الطرود</h2>";

// فحص هيكل الجدول
$query = "SHOW COLUMNS FROM parcels";
$result = $conn->query($query);

echo "<h3>أعمدة جدول parcels:</h3>";
echo "<table border='1' style='width:100%; text-align:center;'>";
echo "<tr><th>اسم العمود</th><th>نوع البيانات</th><th>يمكن أن يكون فارغ</th><th>المفتاح</th><th>الافتراضي</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// فحص عينة من البيانات المتعلقة بالمبالغ
echo "<h3>عينة من الطرود مع المبالغ:</h3>";
$sample_query = "
    SELECT id, tracking_number, cod_amount, shipping_fees, shipping_payer, paid_amount, total_amount 
    FROM parcels 
    WHERE cod_amount > 0 
    LIMIT 5
";
$sample_result = $conn->query($sample_query);

if ($sample_result && $sample_result->num_rows > 0) {
    echo "<table border='1' style='width:100%; text-align:center;'>";
    echo "<tr><th>ID</th><th>رقم التتبع</th><th>قيمة COD</th><th>رسوم الشحن</th><th>من يدفع الشحن</th><th>المبلغ المدفوع</th><th>الإجمالي</th></tr>";
    
    while ($row = $sample_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['tracking_number']}</td>";
        echo "<td>{$row['cod_amount']}</td>";
        echo "<td>{$row['shipping_fees']}</td>";
        echo "<td>{$row['shipping_payer']}</td>";
        echo "<td>{$row['paid_amount']}</td>";
        echo "<td>{$row['total_amount']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>لا توجد طرود مع قيم COD</p>";
}

$conn->close();
?>
