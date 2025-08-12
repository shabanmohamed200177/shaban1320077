<?php
include 'db_connect.php';

echo "<h2>فحص عمود التاريخ في جدول parcels</h2>";

$result = $conn->query("SHOW COLUMNS FROM parcels LIKE '%date%' OR SHOW COLUMNS FROM parcels LIKE '%created%' OR SHOW COLUMNS FROM parcels LIKE '%time%'");

echo "<h3>الأعمدة المتعلقة بالتاريخ:</h3>";
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "لا توجد أعمدة تاريخ واضحة، دعني أفحص جميع الأعمدة:<br><br>";
    
    $all_columns = $conn->query("SHOW COLUMNS FROM parcels");
    while ($col = $all_columns->fetch_assoc()) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
}

$conn->close();
?>










