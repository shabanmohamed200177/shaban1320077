<?php
// ملف AJAX محسن لإدارة العملاء مع النظام المالي المتقدم
include 'db_connect.php';
include_once 'admin_class.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'طريقة الطلب غير صالحة']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$admin = new Action();

// Helper function to get customer balance
function getCustomerBalance($conn, $customer_id) {
    // Check if customer_balances table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'customer_balances'");
    if ($table_check && $table_check->num_rows > 0) {
        $balance_query = "
            SELECT SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END) as balance
            FROM customer_balances 
            WHERE customer_id = ? AND status = 'completed'
        ";
        $stmt = $conn->prepare($balance_query);
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return floatval($result['balance'] ?? 0);
    }
    
    // Fallback to customers table balance column
    $column_check = $conn->query("SHOW COLUMNS FROM customers LIKE 'balance'");
    if ($column_check && $column_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT balance FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return floatval($result['balance'] ?? 0);
    }
    
    return 0.00;
}

// Helper function to get customer financial summary
function getCustomerFinancialSummary($conn, $customer_id) {
    // استخدام CustomerFinancialCalculator المحدث
    include_once 'customer_financial_calculator.php';
    $calculator = new CustomerFinancialCalculator($conn);
    $financials = $calculator->calculateCustomerFinancials($customer_id);
    
    if (isset($financials['error'])) {
        return [
            'current_balance' => 0,
            'total_credit' => 0,
            'total_debit' => 0,
            'total_transactions' => 0,
            'pending_payments' => 0,
            'total_shipments' => 0,
            'delivered_shipments' => 0,
            'total_cod_amount' => 0
        ];
    }
    
    // الحصول على الرصيد الحالي
    $current_balance = getCustomerBalance($conn, $customer_id);
    
    return [
        'current_balance' => $current_balance,
        'total_credit' => $financials['total_net_amount'], // المستحق للعميل
        'total_debit' => $financials['total_paid_amount'], // المدفوع للعميل
        'total_transactions' => $financials['total_shipments'],
        'pending_payments' => $financials['total_net_amount'] - $financials['total_paid_amount'], // المتبقي
        'total_shipments' => $financials['total_shipments'],
        'delivered_shipments' => $financials['delivered_shipments'],
        'total_cod_amount' => $financials['total_cod_amount'],
        'remaining_balance' => $financials['remaining_balance'], // الرصيد المتبقي
        'shipments_details' => $financials['shipments_details'] // تفاصيل الشحنات
    ];
}

switch ($action) {
    case 'get_customers_enhanced':
        try {
            $search_query = $_POST['search_query'] ?? '';
            $governorate_id = $_POST['governorate_id'] ?? '';
            $balance_filter = $_POST['balance_filter'] ?? '';
            $status_filter = $_POST['status_filter'] ?? '';
            
            // Build WHERE conditions
            $where_conditions = ['1'];
            $params = [];
            $types = '';
            
            if (!empty($search_query)) {
                $where_conditions[] = "(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
                $search_param = "%$search_query%";
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
                $types .= 'sss';
            }
            
            if (!empty($governorate_id)) {
                $where_conditions[] = "c.governorate_id = ?";
                $params[] = intval($governorate_id);
                $types .= 'i';
            }
            
            if (!empty($status_filter)) {
                $where_conditions[] = "c.status = ?";
                $params[] = intval($status_filter);
                $types .= 'i';
            }
            
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            
            // Check for balance column existence
            $column_check = $conn->query("SHOW COLUMNS FROM customers LIKE 'balance'");
            $has_balance_column = ($column_check && $column_check->num_rows > 0);
            $balance_field = $has_balance_column ? "IFNULL(c.balance, 0)" : "0";
            
            // Main query
            $query = "
                SELECT 
                    c.*,
                    g.name as governorate_name,
                    a.name as area_name,
                    $balance_field as balance,
                    (SELECT COUNT(*) FROM parcels p WHERE p.client_id_fk = c.id) as parcels_count,
                    (SELECT SUM(CASE 
                        WHEN p.shipping_payer = 'sender' THEN IFNULL(p.cod_amount, 0) - IFNULL(p.shipping_fees, 0) 
                        ELSE IFNULL(p.cod_amount, 0) 
                     END) FROM parcels p WHERE p.client_id_fk = c.id) as total_net_amount,
                    (SELECT SUM(CASE WHEN p.status = 4 AND p.payment_status != 'paid' THEN IFNULL(p.cod_amount, 0) ELSE 0 END) 
                     FROM parcels p WHERE p.client_id_fk = c.id) as pending_payments,
                    (SELECT MAX(p.date_created) FROM parcels p WHERE p.client_id_fk = c.id) as last_activity
                FROM customers c
                LEFT JOIN governorates g ON c.governorate_id = g.id
                LEFT JOIN areas a ON c.area_id = a.id
                $where_clause
                ORDER BY c.id DESC
            ";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $customers = [];
            while ($row = $result->fetch_assoc()) {
                // Apply balance filter if specified
                $balance = floatval($row['balance']);
                if (!empty($balance_filter)) {
                    if ($balance_filter === 'positive' && $balance <= 0) continue;
                    if ($balance_filter === 'negative' && $balance >= 0) continue;
                    if ($balance_filter === 'zero' && $balance != 0) continue;
                }
                
                $customers[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'address' => $row['address'],
                    'governorate_name' => $row['governorate_name'],
                    'area_name' => $row['area_name'],
                    'status' => $row['status'],
                    'balance' => $balance,
                    'parcels_count' => intval($row['parcels_count']),
                    'total_net_amount' => floatval($row['total_net_amount'] ?? 0),
                    'pending_payments' => floatval($row['pending_payments'] ?? 0),
                    'last_activity' => $row['last_activity'],
                    'date_created' => $row['date_created']
                ];
            }
            $stmt->close();
            
            echo json_encode([
                'data' => $customers
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'خطأ في جلب بيانات العملاء: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_customer_statistics':
        try {
            // Get total customers
            $total_customers = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
            
            // Get balance statistics
            $balance_stats = ['total_positive_balance' => 0, 'total_negative_balance' => 0];
            
            $column_check = $conn->query("SHOW COLUMNS FROM customers LIKE 'balance'");
            if ($column_check && $column_check->num_rows > 0) {
                $balance_query = "
                    SELECT 
                        SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as total_positive_balance,
                        SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END) as total_negative_balance
                    FROM customers
                ";
                $balance_result = $conn->query($balance_query)->fetch_assoc();
                $balance_stats = [
                    'total_positive_balance' => floatval($balance_result['total_positive_balance'] ?? 0),
                    'total_negative_balance' => floatval($balance_result['total_negative_balance'] ?? 0)
                ];
            }
            
            // Get total shipments
            $total_shipments = $conn->query("SELECT COUNT(*) as count FROM parcels")->fetch_assoc()['count'];
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total_customers' => intval($total_customers),
                    'total_positive_balance' => $balance_stats['total_positive_balance'],
                    'total_negative_balance' => $balance_stats['total_negative_balance'],
                    'total_shipments' => intval($total_shipments)
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'خطأ في جلب الإحصائيات: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_governorates':
        try {
            $governorates_query = "SELECT id, name FROM governorates ORDER BY name ASC";
            $result = $conn->query($governorates_query);
            
            $governorates = [];
            while ($row = $result->fetch_assoc()) {
                $governorates[] = $row;
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => $governorates
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'خطأ في جلب المحافظات: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_customer_full_details':
        try {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            if ($customer_id <= 0) {
                throw new Exception('معرف العميل غير صالح');
            }
            
            // Get customer basic info
            $customer_query = "
                SELECT c.*, g.name as governorate_name, a.name as area_name
                FROM customers c
                LEFT JOIN governorates g ON c.governorate_id = g.id
                LEFT JOIN areas a ON c.area_id = a.id
                WHERE c.id = ?
            ";
            $stmt = $conn->prepare($customer_query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $customer = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$customer) {
                throw new Exception('العميل غير موجود');
            }
            
            // Get financial summary
            $summary = getCustomerFinancialSummary($conn, $customer_id);
            
            // Get recent activity
            $recent_activity = [];
            
            // Check if customer_activity_log table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'customer_activity_log'");
            if ($table_check && $table_check->num_rows > 0) {
                $activity_query = "
                    SELECT action, description, created_at
                    FROM customer_activity_log 
                    WHERE customer_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ";
                $stmt = $conn->prepare($activity_query);
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $activity_result = $stmt->get_result();
                
                while ($activity = $activity_result->fetch_assoc()) {
                    $recent_activity[] = $activity;
                }
                $stmt->close();
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'customer' => $customer,
                    'summary' => $summary,
                    'recent_activity' => $recent_activity
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        
    case 'get_customer_financial_report':
        try {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            if ($customer_id <= 0) {
                throw new Exception('معرف العميل غير صالح');
            }
            
            // Get financial summary
            $financial_summary = getCustomerFinancialSummary($conn, $customer_id);
            
            // Get balance transactions
            $balance_transactions = [];
            $table_check = $conn->query("SHOW TABLES LIKE 'customer_balances'");
            if ($table_check && $table_check->num_rows > 0) {
                $balance_query = "
                    SELECT type, amount, description, created_at
                    FROM customer_balances 
                    WHERE customer_id = ? AND status = 'completed'
                    ORDER BY created_at DESC 
                    LIMIT 20
                ";
                $stmt = $conn->prepare($balance_query);
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $balance_result = $stmt->get_result();
                
                while ($transaction = $balance_result->fetch_assoc()) {
                    $balance_transactions[] = $transaction;
                }
                $stmt->close();
            }
            
            // Get recent payments
            $recent_payments = [];
            $payments_table_check = $conn->query("SHOW TABLES LIKE 'customer_payments'");
            if ($payments_table_check && $payments_table_check->num_rows > 0) {
                $payments_query = "
                    SELECT amount, payment_method, created_at
                    FROM customer_payments 
                    WHERE customer_id = ?
                    ORDER BY created_at DESC 
                    LIMIT 10
                ";
                $stmt = $conn->prepare($payments_query);
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $payments_result = $stmt->get_result();
                
                while ($payment = $payments_result->fetch_assoc()) {
                    $recent_payments[] = $payment;
                }
                $stmt->close();
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'financial_summary' => $financial_summary,
                    'balance_transactions' => $balance_transactions,
                    'recent_payments' => $recent_payments
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        
    case 'get_customer_shipments_detailed':
        try {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            if ($customer_id <= 0) {
                throw new Exception('معرف العميل غير صالح');
            }
            
            // Get shipments with status information
            $shipments_query = "
                SELECT 
                    p.*,
                    ps.name_ar as status_name,
                    ps.color as status_color,
                    CASE 
                        WHEN p.payment_status = 'paid' THEN 'paid'
                        WHEN p.payment_status = 'partial_paid' THEN 'partial_paid'
                        ELSE 'unpaid'
                    END as payment_status
                FROM parcels p
                LEFT JOIN parcel_status ps ON p.status = ps.id
                WHERE p.client_id_fk = ?
                ORDER BY p.date_created DESC
                LIMIT 50
            ";
            $stmt = $conn->prepare($shipments_query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $shipments_result = $stmt->get_result();
            
            $shipments = [];
            while ($shipment = $shipments_result->fetch_assoc()) {
                $shipments[] = $shipment;
            }
            $stmt->close();
            
            // Get shipments summary
            $summary_query = "
                SELECT 
                    COUNT(*) as total_shipments,
                    SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered_shipments,
                    SUM(CASE WHEN status IN (1,2,3) THEN 1 ELSE 0 END) as pending_shipments,
                    SUM(IFNULL(cod_amount, 0)) as total_cod_amount
                FROM parcels 
                WHERE client_id_fk = ?
            ";
            $stmt = $conn->prepare($summary_query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $summary = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'shipments' => $shipments,
                    'summary' => [
                        'total_shipments' => intval($summary['total_shipments']),
                        'delivered_shipments' => intval($summary['delivered_shipments']),
                        'pending_shipments' => intval($summary['pending_shipments']),
                        'total_cod_amount' => floatval($summary['total_cod_amount'])
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        
    case 'add_customer_payment':
        try {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_type = $_POST['payment_type'] ?? 'cash';
            $notes = $_POST['notes'] ?? '';
            
            if ($customer_id <= 0 || $amount <= 0) {
                throw new Exception('بيانات الدفعة غير صالحة');
            }
            
            $conn->begin_transaction();
            
            try {
                // Add to customer_payments table if it exists
                $payments_table_check = $conn->query("SHOW TABLES LIKE 'customer_payments'");
                if ($payments_table_check && $payments_table_check->num_rows > 0) {
                    $payment_query = "
                        INSERT INTO customer_payments (customer_id, amount, payment_method, notes, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ";
                    $stmt = $conn->prepare($payment_query);
                    $stmt->bind_param("idss", $customer_id, $amount, $payment_type, $notes);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Add to customer_balances table if it exists
                $balances_table_check = $conn->query("SHOW TABLES LIKE 'customer_balances'");
                if ($balances_table_check && $balances_table_check->num_rows > 0) {
                    // Get current balance
                    $current_balance = getCustomerBalance($conn, $customer_id);
                    $new_balance = $current_balance + $amount;
                    
                    $balance_type = ($payment_type === 'debit') ? 'debit' : 'credit';
                    
                    $balance_query = "
                        INSERT INTO customer_balances 
                        (customer_id, type, amount, description, balance_before, balance_after, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
                    ";
                    $stmt = $conn->prepare($balance_query);
                    $description = "دفعة $payment_type: $notes";
                    $stmt->bind_param("isdsdd", $customer_id, $balance_type, $amount, $description, $current_balance, $new_balance);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Update customer balance in customers table if column exists
                $column_check = $conn->query("SHOW COLUMNS FROM customers LIKE 'balance'");
                if ($column_check && $column_check->num_rows > 0) {
                    $balance_adjustment = ($payment_type === 'debit') ? -$amount : $amount;
                    $update_query = "UPDATE customers SET balance = IFNULL(balance, 0) + ? WHERE id = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("di", $balance_adjustment, $customer_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Log activity if table exists
                $activity_table_check = $conn->query("SHOW TABLES LIKE 'customer_activity_log'");
                if ($activity_table_check && $activity_table_check->num_rows > 0) {
                    $activity_query = "
                        INSERT INTO customer_activity_log (customer_id, action, description, created_at)
                        VALUES (?, ?, ?, NOW())
                    ";
                    $stmt = $conn->prepare($activity_query);
                    $action = "تسجيل دفعة";
                    $description = "تم تسجيل دفعة بمبلغ $amount جنيه - نوع الدفع: $payment_type";
                    $stmt->bind_param("iss", $customer_id, $action, $description);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $conn->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'تم تسجيل الدفعة بنجاح'
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        
    case 'export_excel':
        try {
            // Get all customers data
            $query = "
                SELECT 
                    c.*,
                    g.name as governorate_name,
                    a.name as area_name,
                    IFNULL(c.balance, 0) as balance,
                    (SELECT COUNT(*) FROM parcels p WHERE p.client_id_fk = c.id) as parcels_count,
                    (SELECT SUM(IFNULL(p.cod_amount, 0)) FROM parcels p WHERE p.client_id_fk = c.id) as total_cod
                FROM customers c
                LEFT JOIN governorates g ON c.governorate_id = g.id
                LEFT JOIN areas a ON c.area_id = a.id
                ORDER BY c.id DESC
            ";
            
            $result = $conn->query($query);
            
            // Set headers for Excel export
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="customers_report_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo '<table border="1">';
            echo '<tr>';
            echo '<th>رقم العميل</th>';
            echo '<th>اسم العميل</th>';
            echo '<th>رقم الهاتف</th>';
            echo '<th>البريد الإلكتروني</th>';
            echo '<th>المحافظة</th>';
            echo '<th>المنطقة</th>';
            echo '<th>الرصيد الحالي</th>';
            echo '<th>عدد الشحنات</th>';
            echo '<th>إجمالي COD</th>';
            echo '<th>تاريخ التسجيل</th>';
            echo '</tr>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . $row['phone'] . '</td>';
                echo '<td>' . ($row['email'] ?: 'غير محدد') . '</td>';
                echo '<td>' . ($row['governorate_name'] ?: 'غير محدد') . '</td>';
                echo '<td>' . ($row['area_name'] ?: 'غير محدد') . '</td>';
                echo '<td>' . number_format($row['balance'], 2) . '</td>';
                echo '<td>' . $row['parcels_count'] . '</td>';
                echo '<td>' . number_format($row['total_cod'], 2) . '</td>';
                echo '<td>' . $row['date_created'] . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'خطأ في التصدير: ' . $e->getMessage()]);
        }
        break;
        
    case 'print_report':
        try {
            // Similar to export but formatted for printing
            header('Content-Type: text/html; charset=utf-8');
            
            echo '<!DOCTYPE html>';
            echo '<html lang="ar" dir="rtl">';
            echo '<head>';
            echo '<meta charset="UTF-8">';
            echo '<title>تقرير العملاء</title>';
            echo '<style>';
            echo 'body { font-family: Arial, sans-serif; direction: rtl; }';
            echo 'table { width: 100%; border-collapse: collapse; }';
            echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }';
            echo 'th { background-color: #f2f2f2; }';
            echo '</style>';
            echo '</head>';
            echo '<body>';
            echo '<h1>تقرير العملاء - ' . date('Y-m-d H:i') . '</h1>';
            
            // Same table structure as export
            $query = "
                SELECT 
                    c.*,
                    g.name as governorate_name,
                    a.name as area_name,
                    IFNULL(c.balance, 0) as balance,
                    (SELECT COUNT(*) FROM parcels p WHERE p.client_id_fk = c.id) as parcels_count
                FROM customers c
                LEFT JOIN governorates g ON c.governorate_id = g.id
                LEFT JOIN areas a ON c.area_id = a.id
                ORDER BY c.id DESC
            ";
            
            $result = $conn->query($query);
            
            echo '<table>';
            echo '<tr>';
            echo '<th>رقم العميل</th>';
            echo '<th>اسم العميل</th>';
            echo '<th>رقم الهاتف</th>';
            echo '<th>المحافظة</th>';
            echo '<th>الرصيد الحالي</th>';
            echo '<th>عدد الشحنات</th>';
            echo '</tr>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . $row['phone'] . '</td>';
                echo '<td>' . ($row['governorate_name'] ?: 'غير محدد') . '</td>';
                echo '<td>' . number_format($row['balance'], 2) . '</td>';
                echo '<td>' . $row['parcels_count'] . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            echo '</body>';
            echo '</html>';
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'خطأ في الطباعة: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'إجراء غير صالح']);
        break;
}

$conn->close();
?>
