<?php
include 'db_connect.php';

echo "<h2>فحص بنية جدول parcel_status</h2>";

// فحص أعمدة الجدول
$result = $conn->query("SHOW COLUMNS FROM parcel_status");

if ($result) {
    echo "<h3>أعمدة الجدول:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>اسم العمود</th><th>النوع</th><th>Null</th><th>المفتاح</th><th>القيمة الافتراضية</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "خطأ في قراءة بنية الجدول: " . $conn->error;
}

// عرض البيانات الموجودة
echo "<br><h3>البيانات الموجودة:</h3>";
$result = $conn->query("SELECT * FROM parcel_status ORDER BY id");

if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'>";
    
    // عرض أسماء الأعمدة
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    // عرض البيانات
    $result->data_seek(0); // العودة لبداية النتائج
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . ($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "خطأ في قراءة البيانات: " . $conn->error;
}

$conn->close();
?>
