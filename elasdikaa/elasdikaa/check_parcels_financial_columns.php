<?php
include 'db_connect.php';

echo "فحص الأعمدة المالية في جدول الطرود:<br><br>";

// فحص الأعمدة المالية فقط
$financial_columns = [
    'price', 'cod_amount', 'shipping_fees', 'shipping_payer', 
    'paid_amount', 'total_amount', 'is_paid', 'partial_payment_amount',
    'payment_method', 'commission', 'net_amount'
];

foreach ($financial_columns as $column) {
    $query = "SHOW COLUMNS FROM parcels LIKE '$column'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "✅ $column: {$row['Type']} - {$row['Null']} - افتراضي: {$row['Default']}<br>";
    } else {
        echo "❌ $column: غير موجود<br>";
    }
}

echo "<br><h3>عينة من البيانات:</h3>";

// فحص عينة من البيانات
$sample_query = "
    SELECT id, tracking_number, 
           COALESCE(cod_amount, 0) as cod_amount,
           COALESCE(shipping_fees, 0) as shipping_fees,
           COALESCE(shipping_payer, 'recipient') as shipping_payer,
           COALESCE(paid_amount, 0) as paid_amount,
           COALESCE(price, 0) as price,
           status
    FROM parcels 
    WHERE (cod_amount > 0 OR paid_amount > 0)
    LIMIT 3
";

$sample_result = $conn->query($sample_query);

if ($sample_result && $sample_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>رقم التتبع</th><th>قيمة COD</th><th>رسوم الشحن</th><th>من يدفع</th><th>المدفوع</th><th>السعر</th><th>الحالة</th>";
    echo "</tr>";
    
    while ($row = $sample_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['tracking_number']}</td>";
        echo "<td>{$row['cod_amount']} جنيه</td>";
        echo "<td>{$row['shipping_fees']} جنيه</td>";
        echo "<td>{$row['shipping_payer']}</td>";
        echo "<td>{$row['paid_amount']} جنيه</td>";
        echo "<td>{$row['price']} جنيه</td>";
        echo "<td>{$row['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "لا توجد طرود بمبالغ مالية";
}

// حساب المنطق المطلوب
echo "<br><h3>شرح المنطق المطلوب:</h3>";
echo "<div style='background: #e8f4fd; padding: 15px; border-radius: 5px;'>";
echo "<strong>مثال:</strong><br>";
echo "• قيمة الشحنة (COD): 2500 جنيه<br>";
echo "• تم دفع جزئي: 1000 جنيه<br>";
echo "• رسوم الشحن: 100 جنيه<br>";
echo "<br>";
echo "<strong>إذا كان الشحن على المستلم:</strong><br>";
echo "• المبلغ المستحق للعميل = 2500 - 1000 = 1500 جنيه<br>";
echo "<br>";
echo "<strong>إذا كان الشحن على المرسل:</strong><br>";
echo "• المبلغ المستحق للعميل = (2500 - 1000) - 100 = 1400 جنيه<br>";
echo "</div>";

$conn->close();
?>
