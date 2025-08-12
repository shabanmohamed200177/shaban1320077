<?php
// فحص الجداول المطلوبة
include 'db_connect.php';

echo "<h2>فحص الجداول المطلوبة</h2>";

$tables_to_check = [
    'parcels',
    'parcel_tracks', 
    'parcel_status',
    'parcel_status_history',
    'parcel_status_reasons',
    'customers',
    'customer_balances',
    'customer_payments'
];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ جدول $table موجود</p>";
        
        // فحص الأعمدة المهمة لجدول parcels
        if ($table == 'parcels') {
            $columns_to_check = ['status', 'payment_status', 'net_amount_due', 'shipping_payer', 'status_reason_id', 'status_reason_note'];
            foreach ($columns_to_check as $column) {
                $col_result = $conn->query("SHOW COLUMNS FROM parcels LIKE '$column'");
                if ($col_result && $col_result->num_rows > 0) {
                    echo "<span style='color: green;'>&nbsp;&nbsp;✓ العمود $column موجود</span><br>";
                } else {
                    echo "<span style='color: red;'>&nbsp;&nbsp;✗ العمود $column مفقود</span><br>";
                }
            }
        }
    } else {
        echo "<p style='color: red;'>✗ جدول $table مفقود</p>";
    }
}

// فحص حالات الشحنة
echo "<h3>حالات الشحنة المتاحة:</h3>";
$status_result = $conn->query("SELECT * FROM parcel_status ORDER BY id");
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        echo "<p>{$row['id']} - {$row['name_ar']}</p>";
    }
} else {
    echo "<p style='color: red;'>خطأ في جلب حالات الشحنة: " . $conn->error . "</p>";
}

$conn->close();
?>
