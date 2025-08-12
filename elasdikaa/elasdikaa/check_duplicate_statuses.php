<?php
include 'db_connect.php';

echo "<h2>فحص حالات الطرود المكررة</h2>";

// فحص جدول parcel_status
$query = "SELECT * FROM parcel_status ORDER BY id";
$result = $conn->query($query);

echo "<h3>جميع حالات الطرود:</h3>";
echo "<table border='1' style='width:100%; text-align:center;'>";
echo "<tr><th>ID</th><th>الاسم بالعربية</th><th>الأولوية</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['name_ar']}</td>";
    echo "<td>{$row['priority']}</td>";
    echo "</tr>";
}
echo "</table>";

// البحث عن الحالات المكررة
echo "<h3>البحث عن الحالات المكررة:</h3>";
$duplicate_query = "
    SELECT name_ar, COUNT(*) as count
    FROM parcel_status 
    GROUP BY name_ar 
    HAVING COUNT(*) > 1
";
$duplicate_result = $conn->query($duplicate_query);

if ($duplicate_result->num_rows > 0) {
    echo "<table border='1' style='width:50%; text-align:center;'>";
    echo "<tr><th>الاسم</th><th>عدد التكرار</th></tr>";
    while ($row = $duplicate_result->fetch_assoc()) {
        echo "<tr style='background-color: #ffcccc;'>";
        echo "<td>{$row['name_ar']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>لا توجد حالات مكررة</p>";
}

// فحص الحالات التي تحتوي على "دفع جزئي"
echo "<h3>الحالات التي تحتوي على 'دفع جزئي':</h3>";
$partial_query = "SELECT * FROM parcel_status WHERE name_ar LIKE '%دفع جزئي%' OR name_ar LIKE '%جزئي%'";
$partial_result = $conn->query($partial_query);

if ($partial_result->num_rows > 0) {
    echo "<table border='1' style='width:100%; text-align:center;'>";
    echo "<tr><th>ID</th><th>الاسم</th><th>الأولوية</th></tr>";
    while ($row = $partial_result->fetch_assoc()) {
        echo "<tr style='background-color: #ffffcc;'>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name_ar']}</td>";
        echo "<td>{$row['priority']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>لا توجد حالات تحتوي على 'دفع جزئي'</p>";
}

$conn->close();
?>
