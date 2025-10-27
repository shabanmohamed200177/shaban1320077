<?php
include 'db_connect.php';

echo "<h2>إضافة أعمدة المرتجعات</h2>";

// إضافة أعمدة للمرتجعات في جدول parcels
$columns_to_add = [
    ['column' => 'returned_items_count', 'type' => 'INT DEFAULT 0', 'comment' => 'عدد القطع المرتجعة'],
    ['column' => 'return_value', 'type' => 'DECIMAL(10,2) DEFAULT 0.00', 'comment' => 'قيمة المرتجع'],
    ['column' => 'return_reason', 'type' => 'TEXT', 'comment' => 'سبب الإرجاع'],
    ['column' => 'return_date', 'type' => 'TIMESTAMP NULL', 'comment' => 'تاريخ الإرجاع']
];

foreach ($columns_to_add as $col) {
    // التحقق من وجود العمود
    $check_query = "SHOW COLUMNS FROM parcels LIKE '{$col['column']}'";
    $check_result = $conn->query($check_query);
    
    if ($check_result->num_rows === 0) {
        // إضافة العمود
        $add_query = "ALTER TABLE parcels ADD COLUMN {$col['column']} {$col['type']} COMMENT '{$col['comment']}'";
        if ($conn->query($add_query)) {
            echo "✅ تم إضافة العمود {$col['column']}<br>";
        } else {
            echo "❌ فشل في إضافة العمود {$col['column']}: " . $conn->error . "<br>";
        }
    } else {
        echo "ℹ️ العمود {$col['column']} موجود بالفعل<br>";
    }
}

echo "<br><h3>تحديث بيانات المرتجعات الموجودة:</h3>";

// تحديث الشحنات ذات الحالة مرتجع للفرع (9) أو مرتجع للعميل (10)
$update_returns_query = "
    UPDATE parcels 
    SET return_value = CASE 
        WHEN shipping_payer = 'sender' THEN GREATEST(0, COALESCE(cod_amount, 0) - COALESCE(paid_amount, 0) - COALESCE(shipping_fees, 0))
        ELSE GREATEST(0, COALESCE(cod_amount, 0) - COALESCE(paid_amount, 0))
    END,
    return_date = COALESCE(status_updated_at, date_created),
    return_reason = COALESCE(status_reason_note, 'لم يتم تسليم الشحنة')
    WHERE status IN (9, 10) AND return_value IS NULL
";

if ($conn->query($update_returns_query)) {
    $affected_rows = $conn->affected_rows;
    echo "✅ تم تحديث {$affected_rows} شحنة مرتجعة<br>";
} else {
    echo "❌ فشل في تحديث المرتجعات: " . $conn->error . "<br>";
}

// عرض عينة من المرتجعات المحدثة
echo "<br><h3>عينة من المرتجعات:</h3>";
$sample_query = "
    SELECT id, tracking_number, cod_amount, paid_amount, shipping_fees, shipping_payer, 
           returned_items_count, return_value, return_reason, return_date, status
    FROM parcels 
    WHERE status IN (9, 10) 
    LIMIT 5
";

$sample_result = $conn->query($sample_query);

if ($sample_result && $sample_result->num_rows > 0) {
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>رقم التتبع</th><th>قيمة الشحنة</th><th>المدفوع</th><th>عدد القطع</th><th>قيمة المرتجع</th><th>الحالة</th>";
    echo "</tr>";
    
    while ($row = $sample_result->fetch_assoc()) {
        $status_text = ($row['status'] == 9) ? 'مرتجع للفرع' : 'مرتجع للعميل';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['tracking_number']}</td>";
        echo "<td>{$row['cod_amount']} جنيه</td>";
        echo "<td>{$row['paid_amount']} جنيه</td>";
        echo "<td>{$row['returned_items_count']}</td>";
        echo "<td style='font-weight: bold; color: green;'>{$row['return_value']} جنيه</td>";
        echo "<td><span style='background: orange; padding: 3px 8px; border-radius: 3px; color: white;'>{$status_text}</span></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>لا توجد شحنات مرتجعة</p>";
}

echo "<br><div style='background: #e8f5e8; padding: 15px; border-radius: 5px;'>";
echo "<h3>✅ تم إعداد نظام المرتجعات بنجاح!</h3>";
echo "<p><strong>الأعمدة المضافة:</strong></p>";
echo "<ul>";
echo "<li><strong>returned_items_count:</strong> عدد القطع المرتجعة</li>";
echo "<li><strong>return_value:</strong> قيمة المرتجع (المبلغ المستحق للعميل)</li>";
echo "<li><strong>return_reason:</strong> سبب الإرجاع</li>";
echo "<li><strong>return_date:</strong> تاريخ الإرجاع</li>";
echo "</ul>";
echo "<p><strong>الحساب:</strong> قيمة المرتجع = (قيمة الشحنة - المدفوع) - رسوم الشحن (إذا كانت على المرسل)</p>";
echo "</div>";

$conn->close();
?>
