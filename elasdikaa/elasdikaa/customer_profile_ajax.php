<?php
// ملف: customer_profile_ajax.php - معالج طلبات AJAX لصفحة ملف العميل

// تفعيل عرض الأخطاء للمساعدة في التطوير
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التأكد من وجود ملف الاتصال بقاعدة البيانات
if (!isset($conn)) {
    include 'db_connect.php';
}

// التحقق من نوع الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'طريقة طلب غير صحيحة']);
    exit;
}

// التحقق من وجود action
if (!isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'لم يتم تحديد الإجراء المطلوب']);
    exit;
}

$action = $_POST['action'];
$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

if ($customer_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'معرف العميل غير صحيح']);
    exit;
}

switch ($action) {
    case 'get_delivered_shipments':
        getDeliveredShipments($customer_id, $conn);
        break;
        
    case 'get_partial_shipments':
        getPartialShipments($customer_id, $conn);
        break;
        
    case 'get_returned_shipments':
        getReturnedShipments($customer_id, $conn);
        break;
        
    case 'get_customer_transactions':
        getCustomerTransactions($customer_id, $conn);
        break;
        
    case 'get_customer_activities':
        getCustomerActivities($customer_id, $conn);
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'إجراء غير معروف']);
        break;
}

// دالة جلب الشحنات المسلمة
function getDeliveredShipments($customer_id, $conn) {
    $query = "
        SELECT 
            p.id,
            p.tracking_number,
            p.recipient_name,
            p.cod_amount,
            p.shipping_fees,
            p.delivery_agent_fee,
            p.paid_amount,
            CASE 
                WHEN p.shipping_payer = 'sender' THEN p.cod_amount - p.shipping_fees
                ELSE p.cod_amount
            END as net_for_client
        FROM parcels p
        WHERE p.client_id_fk = ? 
        AND p.status = 4 
        AND (p.payment_status IS NULL OR p.payment_status != 'paid')
        ORDER BY p.date_created DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $shipments = [];
    while ($row = $result->fetch_assoc()) {
        $net_for_client = floatval($row['net_for_client']);
        $paid_amount = floatval($row['paid_amount'] ?? 0);
        $remaining_amount = $net_for_client - $paid_amount;
        
        $shipments[] = [
            'id' => $row['id'],
            'tracking_number' => $row['tracking_number'],
            'recipient_name' => $row['recipient_name'],
            'cod_amount' => floatval($row['cod_amount']),
            'shipping_fees' => floatval($row['shipping_fees'] ?? 0),
            'delivery_agent_fee' => floatval($row['delivery_agent_fee'] ?? 0),
            'delivery_date' => null,
            'net_for_client' => $net_for_client,
            'paid_amount' => $paid_amount,
            'remaining_amount' => $remaining_amount
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $shipments]);
}

// دالة جلب الشحنات الجزئية
function getPartialShipments($customer_id, $conn) {
    $query = "
        SELECT 
            p.id,
            p.tracking_number,
            p.recipient_name,
            p.cod_amount,
            p.shipping_fees,
            p.delivery_agent_fee,
            p.paid_amount,
            CASE 
                WHEN p.shipping_payer = 'sender' THEN p.cod_amount - p.shipping_fees
                ELSE p.cod_amount
            END as net_for_client
        FROM parcels p
        WHERE p.client_id_fk = ? 
        AND p.payment_status = 'partial_paid'
        ORDER BY p.date_created DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $shipments = [];
    while ($row = $result->fetch_assoc()) {
        $net_for_client = floatval($row['net_for_client']);
        $paid_amount = floatval($row['paid_amount'] ?? 0);
        $remaining_amount = $net_for_client - $paid_amount;
        
        $shipments[] = [
            'id' => $row['id'],
            'tracking_number' => $row['tracking_number'],
            'recipient_name' => $row['recipient_name'],
            'cod_amount' => floatval($row['cod_amount']),
            'shipping_fees' => floatval($row['shipping_fees'] ?? 0),
            'delivery_agent_fee' => floatval($row['delivery_agent_fee'] ?? 0),
            'paid_amount' => $paid_amount,
            'delivery_date' => null,
            'net_for_client' => $net_for_client,
            'remaining_amount' => $remaining_amount
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $shipments]);
}

// دالة جلب الشحنات المرتجعة
function getReturnedShipments($customer_id, $conn) {
    $query = "
        SELECT 
            p.id,
            p.tracking_number,
            p.recipient_name,
            p.cod_amount,
            p.return_value,
            p.return_reason,
            p.return_date,
            p.status
        FROM parcels p
        WHERE p.client_id_fk = ? 
        AND p.status IN (9, 10, 11, 12)
        ORDER BY p.return_date DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $shipments = [];
    while ($row = $result->fetch_assoc()) {
        $shipments[] = [
            'id' => $row['id'],
            'tracking_number' => $row['tracking_number'],
            'recipient_name' => $row['recipient_name'],
            'cod_amount' => floatval($row['cod_amount']),
            'return_value' => floatval($row['return_value'] ?? 0),
            'return_reason' => $row['return_reason'] ?? 'غير محدد',
            'return_date' => $row['return_date'],
            'status' => $row['status']
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $shipments]);
}

// دالة جلب المعاملات المالية للعميل
function getCustomerTransactions($customer_id, $conn) {
    $query = "
        SELECT 
            cb.type,
            cb.amount,
            cb.description,
            cb.balance_after,
            cb.created_at,
            cb.reference_type,
            cb.reference_id
        FROM customer_balances cb
        WHERE cb.customer_id = ?
        ORDER BY cb.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'type' => $row['type'],
            'amount' => floatval($row['amount']),
            'description' => $row['description'],
            'balance_after' => floatval($row['balance_after']),
            'created_at' => $row['created_at'],
            'reference_type' => $row['reference_type'],
            'reference_id' => $row['reference_id']
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $transactions]);
}

// دالة جلب أنشطة العميل
function getCustomerActivities($customer_id, $conn) {
    $query = "
        SELECT 
            cal.activity_type,
            cal.description,
            cal.created_at,
            cal.ip_address
        FROM customer_activity_log cal
        WHERE cal.customer_id = ?
        ORDER BY cal.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = [
            'activity_type' => $row['activity_type'],
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'ip_address' => $row['ip_address']
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $activities]);
}
?>
