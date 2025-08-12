<?php
include 'db_connect.php';

echo "<h1>📦 إنشاء شحنة جديدة للاختبار</h1>";

// إنشاء شحنة جديدة بسيطة
$tracking_number = 'TEST-' . strtoupper(uniqid());
$cod_amount = 2000;
$shipping_fees = 100;
$shipping_payer = 'sender'; // المرسل يدفع الشحن

$insert_sql = "INSERT INTO parcels (
    tracking_number, 
    cod_amount, 
    paid_amount, 
    shipping_fees, 
    shipping_payer, 
    sender_phone, 
    sender_name,
    recipient_name,
    payment_status,
    status,
    date_created
) VALUES (?, ?, 0, ?, ?, '01000613138', 'داود', 'المستلم التجريبي', 'unpaid', 1, NOW())";

$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("sdis", $tracking_number, $cod_amount, $shipping_fees, $shipping_payer);

if ($stmt->execute()) {
    $new_parcel_id = $conn->insert_id;
    
    echo "<div style='border: 2px solid #28a745; padding: 15px; background-color: #d4edda;'>";
    echo "<h2>✅ تم إنشاء شحنة جديدة!</h2>";
    echo "<p><strong>رقم التتبع:</strong> $tracking_number</p>";
    echo "<p><strong>قيمة الشحنة:</strong> " . number_format($cod_amount, 2) . " جنيه</p>";
    echo "<p><strong>رسوم الشحن:</strong> " . number_format($shipping_fees, 2) . " جنيه</p>";
    echo "<p><strong>من يدفع الشحن:</strong> المرسل</p>";
    echo "<p><strong>معرف الشحنة:</strong> $new_parcel_id</p>";
    echo "</div>";
    
    echo "<h2>💰 اختبار النظام الجديد مع هذه الشحنة:</h2>";
    echo "<p><a href='simple_partial_payment_system.php' target='_blank' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 اختبار النظام الجديد</a></p>";
    
    echo "<h3>🎯 السيناريو المتوقع:</h3>";
    echo "<ul>";
    echo "<li><strong>عند دفع 1000 جنيه:</strong></li>";
    echo "<li>المرسل يدفع الشحن → المستحق للعميل = 1000 - 100 = 900 جنيه ✅</li>";
    echo "<li><strong>عند دفع 500 جنيه أخرى:</strong></li>";
    echo "<li>المستحق للعميل = 500 - 100 = 400 جنيه</li>";
    echo "<li><strong>إجمالي رصيد العميل = 900 + 400 = 1300 جنيه</strong></li>";
    echo "</ul>";
    
} else {
    echo "<p style='color: red;'>❌ فشل في إنشاء الشحنة: " . $conn->error . "</p>";
}

$stmt->close();
$conn->close();
?>


