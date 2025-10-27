<?php
include 'db_connect.php';
include 'customer_financial_calculator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'طريقة الطلب غير صالحة']);
    exit;
}

$action = $_POST['action'] ?? '';
$customer_id = intval($_POST['customer_id'] ?? 0);

if ($customer_id <= 0) {
    echo json_encode(['error' => 'معرف العميل غير صالح']);
    exit;
}

// Verify customer exists and get phone
$customer_stmt = $conn->prepare("SELECT phone FROM customers WHERE id = ?");
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_data = $customer_stmt->get_result()->fetch_assoc();
$customer_stmt->close();

if (!$customer_data) {
    echo json_encode(['error' => 'العميل غير موجود']);
    exit;
}

$calculator = new CustomerFinancialCalculator($conn);

switch ($action) {
    case 'get_customer_financials':
        $financials = $calculator->calculateCustomerFinancials($customer_id);
        echo json_encode($financials);
        break;
        
    case 'get_detailed_report':
        $report = $calculator->getDetailedFinancialReport($customer_id);
        echo json_encode($report);
        break;
        
    case 'process_partial_payment':
        $payment_amount = floatval($_POST['payment_amount'] ?? 0);
        $selected_shipments = $_POST['selected_shipments'] ?? [];
        $notes = $_POST['notes'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $reference_number = $_POST['reference_number'] ?? '';
        
        if ($payment_amount <= 0) {
            echo json_encode(['error' => 'مبلغ الدفعة غير صالح']);
            break;
        }
        
        if (empty($payment_method)) {
            echo json_encode(['error' => 'طريقة الدفع مطلوبة']);
            break;
        }
        
        $result = $calculator->processPartialPayment($customer_id, $payment_amount, $selected_shipments, $notes, $payment_method, $reference_number);
        
        // إضافة معلومات تشخيصية في حالة الخطأ
        if (isset($result['error'])) {
            error_log("Customer Payment Error for customer_id {$customer_id}: " . $result['error']);
        }
        
        echo json_encode($result);
        break;
        
    case 'get_unpaid_shipments':
        // الحصول على الشحنات غير المدفوعة للعميل
        $financials = $calculator->calculateCustomerFinancials($customer_id);
        
        if (isset($financials['error'])) {
            echo json_encode(['error' => $financials['error']]);
            break;
        }
        
        $unpaid_shipments = array_filter($financials['shipments_details'], function($shipment) {
            return $shipment['net_amount'] > 0;
        });
        
        echo json_encode([
            'success' => true,
            'shipments' => array_values($unpaid_shipments),
            'total_remaining' => $financials['remaining_balance']
        ]);
        break;
        
    case 'calculate_payment_distribution':
        // حساب توزيع دفعة معينة على الشحنات
        $payment_amount = floatval($_POST['payment_amount'] ?? 0);
        $selected_shipments = $_POST['selected_shipments'] ?? [];
        
        if ($payment_amount <= 0) {
            echo json_encode(['error' => 'مبلغ الدفعة غير صالح']);
            break;
        }
        
        $financials = $calculator->calculateCustomerFinancials($customer_id);
        
        if (isset($financials['error'])) {
            echo json_encode(['error' => $financials['error']]);
            break;
        }
        
        $distribution = [];
        $remaining_payment = $payment_amount;
        
        if (!empty($selected_shipments)) {
            // توزيع على شحنات محددة
            foreach ($selected_shipments as $shipment_id) {
                if ($remaining_payment <= 0) break;
                
                $shipment = array_filter($financials['shipments_details'], function($s) use ($shipment_id) {
                    return $s['id'] == $shipment_id;
                });
                
                if (!empty($shipment)) {
                    $shipment = array_values($shipment)[0];
                    $payment_for_shipment = min($remaining_payment, $shipment['remaining']);
                    
                    if ($payment_for_shipment > 0) {
                        $distribution[] = [
                            'shipment_id' => $shipment['id'],
                            'tracking_number' => $shipment['tracking_number'],
                            'remaining_before' => $shipment['remaining'],
                            'payment_amount' => $payment_for_shipment,
                            'remaining_after' => $shipment['remaining'] - $payment_for_shipment
                        ];
                        
                        $remaining_payment -= $payment_for_shipment;
                    }
                }
            }
        } else {
            // توزيع تلقائي (أقدم الشحنات أولاً)
            foreach ($financials['shipments_details'] as $shipment) {
                if ($remaining_payment <= 0) break;
                
                if ($shipment['remaining'] > 0) {
                    $payment_for_shipment = min($remaining_payment, $shipment['remaining']);
                    
                    $distribution[] = [
                        'shipment_id' => $shipment['id'],
                        'tracking_number' => $shipment['tracking_number'],
                        'remaining_before' => $shipment['remaining'],
                        'payment_amount' => $payment_for_shipment,
                        'remaining_after' => $shipment['remaining'] - $payment_for_shipment
                    ];
                    
                    $remaining_payment -= $payment_for_shipment;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'distribution' => $distribution,
            'used_amount' => $payment_amount - $remaining_payment,
            'unused_amount' => $remaining_payment
        ]);
        break;
        
    case 'get_payment_history':
        // الحصول على سجل المدفوعات مع التفاصيل
        $table_check = $conn->query("SHOW TABLES LIKE 'customer_payments'");
        
        if ($table_check && $table_check->num_rows > 0) {
            $query = "
                SELECT cp.*,
                       CASE 
                           WHEN cp.payment_method = 'cash' THEN 'دفع نقدي'
                           WHEN cp.payment_method = 'instapay' THEN 'انستا باي'
                           WHEN cp.payment_method = 'vodafone_cash' THEN 'فودافون كاش'
                           WHEN cp.payment_method = 'bank_transfer' THEN 'تحويل بنكي'
                           ELSE 'دفع جزئي'
                       END as payment_method_ar,
                       (SELECT COUNT(*) FROM customer_payment_details cpd WHERE cpd.payment_id = cp.id) as shipments_count,
                       (SELECT SUM(cpd.amount) FROM customer_payment_details cpd WHERE cpd.payment_id = cp.id) as total_distributed,
                       (SELECT GROUP_CONCAT(
                           CONCAT(p.tracking_number, ' (', FORMAT(cpd.amount, 2), ' جنيه)')
                           SEPARATOR '، '
                       ) FROM customer_payment_details cpd 
                       LEFT JOIN parcels p ON cpd.shipment_id = p.id 
                       WHERE cpd.payment_id = cp.id) as shipments_details,
                       (SELECT GROUP_CONCAT(
                           CONCAT(p.tracking_number, ':', p.recipient_name, ':', FORMAT(cpd.amount, 2))
                           SEPARATOR '|'
                       ) FROM customer_payment_details cpd 
                       LEFT JOIN parcels p ON cpd.shipment_id = p.id 
                       WHERE cpd.payment_id = cp.id) as detailed_shipments,
                       c.name as customer_name,
                       c.phone as customer_phone
                FROM customer_payments cp
                LEFT JOIN customers c ON cp.customer_id = c.id
                WHERE cp.customer_id = ?
                ORDER BY cp.created_at DESC
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $payments = [];
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['success' => true, 'payments' => $payments]);
        } else {
            echo json_encode(['success' => true, 'payments' => []]);
        }
        break;
        
    case 'get_unpaid_shipments':
        // الحصول على الشحنات غير المدفوعة للعميل
        $query = "
            SELECT 
                p.id,
                p.tracking_number,
                p.cod_amount,
                p.paid_amount,
                p.shipping_fees,
                p.shipping_payer,
                p.recipient_name,
                p.date_created,
                p.payment_status,
                GREATEST(0, IFNULL(p.cod_amount, 0) - IFNULL(p.paid_amount, 0)) as remaining,
                CASE 
                    WHEN IFNULL(p.shipping_payer, 'sender') = 'sender' THEN 
                        GREATEST(0, IFNULL(p.paid_amount, 0) - IFNULL(p.shipping_fees, 0))
                    ELSE 
                        IFNULL(p.paid_amount, 0)
                END as net_amount
            FROM parcels p
            LEFT JOIN customers c ON CAST(c.phone AS CHAR) = CAST(p.sender_phone AS CHAR)
            WHERE c.id = ?
            ORDER BY p.date_created ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $shipments = [];
        $total_remaining = 0;
        
        while ($row = $result->fetch_assoc()) {
            $remaining = floatval($row['remaining']);
            
            // إدراج فقط الشحنات القابلة للدفع
            if ($remaining > 0 && ($row['payment_status'] === 'unpaid' || $row['payment_status'] === 'partial_paid')) {
                $shipments[] = [
                    'id' => $row['id'],
                    'tracking_number' => $row['tracking_number'],
                    'cod_amount' => floatval($row['cod_amount']),
                    'paid_amount' => floatval($row['paid_amount']),
                    'shipping_fees' => floatval($row['shipping_fees']),
                    'shipping_payer' => $row['shipping_payer'],
                    'recipient_name' => $row['recipient_name'],
                    'remaining' => $remaining,
                    'net_amount' => floatval($row['net_amount']),
                    'created_at' => $row['date_created'],
                    'payment_status' => $row['payment_status']
                ];
                $total_remaining += $remaining;
            }
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'shipments' => $shipments,
            'total_remaining' => $total_remaining,
            'count' => count($shipments)
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'إجراء غير صالح']);
        break;
}

$conn->close();
?>
