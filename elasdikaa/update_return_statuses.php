<?php
include 'db_connect.php';

echo "<h2>تحديث حالات المرتجعات</h2>";

// 1. تحديث الحالة رقم 9 لتصبح "مرتجع للفرع"
$update_sql = "UPDATE parcel_status SET name_ar = 'مرتجع للفرع' WHERE id = 9";
if ($conn->query($update_sql)) {
    echo "✅ تم تحديث الحالة رقم 9 إلى 'مرتجع للفرع'<br>";
} else {
    echo "❌ فشل تحديث الحالة رقم 9: " . $conn->error . "<br>";
}

// 2. التحقق من وجود حالة "مرتجع للعميل"
$check_sql = "SELECT * FROM parcel_status WHERE name_ar = 'مرتجع للعميل'";
$check_result = $conn->query($check_sql);

if ($check_result && $check_result->num_rows > 0) {
    echo "ℹ️ حالة 'مرتجع للعميل' موجودة بالفعل<br>";
} else {
    // إضافة حالة جديدة "مرتجع للعميل"
    $insert_sql = "INSERT INTO parcel_status (name_ar, priority) VALUES ('مرتجع للعميل', 10)";
    if ($conn->query($insert_sql)) {
        echo "✅ تم إضافة حالة 'مرتجع للعميل'<br>";
        $new_status_id = $conn->insert_id;
        echo "رقم الحالة الجديدة: $new_status_id<br>";
    } else {
        echo "❌ فشل إضافة حالة 'مرتجع للعميل': " . $conn->error . "<br>";
    }
}

// 3. عرض الحالات المحدثة
echo "<br><h3>حالات الشحنات بعد التحديث:</h3>";
$result = $conn->query("SELECT * FROM parcel_status ORDER BY id");

if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th>ID</th><th>الاسم العربي</th><th>الأولوية</th><th>الوصف</th><th>النوع</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $description = '';
        $type = '';
        
        switch($row['id']) {
            case 1: 
                $description = 'بداية معالجة الطلب'; 
                $type = 'عادي';
                break;
            case 2: 
                $description = 'تم تسليم الشحنة للمندوب'; 
                $type = 'عادي';
                break;
            case 3: 
                $description = 'المندوب في طريقه للتسليم'; 
                $type = 'عادي';
                break;
            case 4: 
                $description = 'تم تسليم الشحنة بنجاح للمستلم'; 
                $type = 'مكتمل';
                break;
            case 5: 
                $description = 'تم دفع مستحقات الشحنة كاملة'; 
                $type = 'مالي';
                break;
            case 6: 
                $description = 'تم دفع جزء من مستحقات الشحنة'; 
                $type = 'مالي';
                break;
            case 7: 
                $description = 'تم تأجيل التسليم'; 
                $type = 'معلق';
                break;
            case 8: 
                $description = 'رفض المستلم استلام الشحنة'; 
                $type = 'مرفوض';
                break;
            case 9: 
                $description = 'الشحنة رجعت للفرع (لم يتم تسليمها)'; 
                $type = 'مرتجع';
                break;
            case 10: 
                $description = 'تم تسليم المرتجع للعميل المرسل'; 
                $type = 'مرتجع';
                break;
            default:
                if (strpos($row['name_ar'], 'مرتجع') !== false) {
                    $type = 'مرتجع';
                    $description = 'حالة مرتجع';
                } else {
                    $type = 'أخرى';
                    $description = 'حالة أخرى';
                }
        }
        
        $highlight = '';
        if ($type == 'مرتجع') {
            $highlight = 'style="background-color: #fff3cd; font-weight: bold;"';
        }
        
        echo "<tr $highlight>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name_ar'] . "</td>";
        echo "<td>" . $row['priority'] . "</td>";
        echo "<td>" . $description . "</td>";
        echo "<td>" . $type . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><h3>خطوات استخدام الحالات الجديدة:</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border: 1px solid #b3d9ff;'>";
echo "<h4>🔄 سير العمل للمرتجعات:</h4>";
echo "<ol>";
echo "<li><strong>الشحنة لم يتم تسليمها:</strong> يتم تغيير حالتها إلى 'مرتجع للفرع' (ID: 9)</li>";
echo "<li><strong>تسليم المرتجع للعميل:</strong> يتم تغيير حالتها إلى 'مرتجع للعميل' (ID: 10)</li>";
echo "</ol>";
echo "</div>";

echo "<br><div style='background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
echo "<h4 style='color: #155724; margin-top: 0;'>✅ تم تحديث حالات المرتجعات بنجاح!</h4>";
echo "<p style='color: #155724; margin-bottom: 0;'>الآن يمكنك استخدام الحالتين للتمييز بين المرتجعات في الفرع والمرتجعات المسلمة للعملاء.</p>";
echo "</div>";

$conn->close();
?>
