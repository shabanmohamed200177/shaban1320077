<?php
include 'db_connect.php';
$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);
$shipments = [];
$total = 0;

if($type === 'customer' && $id) {
    // جلب رقم هاتف العميل
    $customer_q = mysqli_query($conn, "SELECT phone FROM customers WHERE id = $id");
    $customer = mysqli_fetch_assoc($customer_q);
    $customer_phone = $customer ? $customer['phone'] : '';
    if($customer_phone) {
        $q = "SELECT id, reference_number, recipient_name, price, date_created 
              FROM parcels 
              WHERE sender_phone = '".mysqli_real_escape_string($conn, $customer_phone)."'
                AND status = 7 
                AND (is_paid IS NULL OR is_paid = 0)";
    } else {
        $q = "";
    }
} elseif($type === 'agent' && $id) {
    $q = "SELECT id, reference_number, recipient_name, price, date_created FROM parcels 
          WHERE agent_id = $id AND status = 7 AND (is_paid IS NULL OR is_paid = 0)";
} else {
    $q = "";
}

if($q) {
    $res = mysqli_query($conn, $q);
    while($row = mysqli_fetch_assoc($res)) {
        $row['price'] = floatval($row['price']);
        $shipments[] = $row;
        $total += $row['price'];
    }
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'shipments' => $shipments,
    'total' => $total
]);