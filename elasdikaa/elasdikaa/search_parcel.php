<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tracking_number'])) {
    $tracking_number = trim($_POST['tracking_number']);
    
    if (strlen($tracking_number) < 3) {
        echo json_encode(['success' => false, 'message' => 'رقم التتبع قصير جداً']);
        exit;
    }
    
    // البحث في الشحنات
    $query = "
        SELECT 
            p.id, p.tracking_number, p.sender_name, p.sender_phone, 
            p.recipient_name, p.recipient_phone, p.cod_amount,
            p.shipment_direction, p.status, p.status_reason_note,
            ps.name_ar as status_name,
            psr.reason_text as status_reason,
            a.company_name as agent_name
        FROM parcels p
        LEFT JOIN parcel_status ps ON p.status = ps.id
        LEFT JOIN parcel_status_reasons psr ON p.status_reason_id = psr.id
        LEFT JOIN agents a ON p.agent_id = a.id
        WHERE p.tracking_number LIKE ? 
        AND p.shipment_direction = 'from_agent'
        ORDER BY p.id DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $search_term = '%' . $tracking_number . '%';
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($parcel = $result->fetch_assoc()) {
        // تنسيق أرقام الهاتف لإضافة رمز مصر
        if ($parcel['sender_phone']) {
            $phone = preg_replace('/\D/', '', $parcel['sender_phone']);
            if (substr($phone, 0, 2) === '01') {
                $parcel['sender_phone'] = '2' . $phone;
            } elseif (substr($phone, 0, 3) !== '201') {
                $parcel['sender_phone'] = '201' . ltrim($phone, '0');
            }
        }
        
        if ($parcel['recipient_phone']) {
            $phone = preg_replace('/\D/', '', $parcel['recipient_phone']);
            if (substr($phone, 0, 2) === '01') {
                $parcel['recipient_phone'] = '2' . $phone;
            } elseif (substr($phone, 0, 3) !== '201') {
                $parcel['recipient_phone'] = '201' . ltrim($phone, '0');
            }
        }
        
        echo json_encode([
            'success' => true,
            'parcel' => $parcel
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم العثور على شحنة بهذا الرقم أو الشحنة ليست من وكيل'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'طلب غير صحيح']);
}
?>
