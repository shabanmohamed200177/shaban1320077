<?php
// ملف لجلب بيانات العملاء مع الأرصدة والملخص المالي
include 'db_connect.php';
include_once 'admin_class.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'طريقة الطلب غير صالحة']);
    exit;
}

$action = $_POST['action'] ?? '';
$admin = new Action();

switch ($action) {
    case 'get_customers_list':
        try {
            $search = $_POST['search'] ?? '';
            $governorate_filter = $_POST['governorate_filter'] ?? '';
            $page = intval($_POST['page'] ?? 1);
            $limit = 20; // عدد العملاء في كل صفحة
            $offset = ($page - 1) * $limit;
            
            // بناء الاستعلام
            $where_conditions = [];
            $params = [];
            $types = '';
            
            if (!empty($search)) {
                $where_conditions[] = "(c.name LIKE ? OR c.phone LIKE ?)";
                $search_param = "%$search%";
                $params[] = $search_param;
                $params[] = $search_param;
                $types .= 'ss';
            }
            
            if (!empty($governorate_filter)) {
                $where_conditions[] = "c.governorate_id = ?";
                $params[] = intval($governorate_filter);
                $types .= 'i';
            }
            
            $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
            
            // استعلام العدد الإجمالي
            $count_query = "SELECT COUNT(*) as total FROM customers c $where_clause";
            $count_stmt = $conn->prepare($count_query);
            if (!empty($params)) {
                $count_stmt->bind_param($types, ...$params);
            }
            $count_stmt->execute();
            $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
            $count_stmt->close();
            
            // التحقق من وجود جدول customer_balances وعمود balance
            $table_check = $conn->query("SHOW TABLES LIKE 'customer_balances'");
            $has_balance_table = ($table_check && $table_check->num_rows > 0);
            
            // التحقق من وجود عمود balance في جدول customers
            $column_check = $conn->query("SHOW COLUMNS FROM customers LIKE 'balance'");
            $has_balance_column = ($column_check && $column_check->num_rows > 0);
            
            // استعلام البيانات مع الأرصدة (مع فحص وجود الجدول والعمود)
            $balance_field = $has_balance_column ? "IFNULL(c.balance, 0)" : "0";
            
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
                     END) FROM parcels p WHERE p.client_id_fk = c.id) as total_net_amount
                FROM customers c
                LEFT JOIN governorates g ON c.governorate_id = g.id
                LEFT JOIN areas a ON c.area_id = a.id
                $where_clause
                ORDER BY c.id DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $conn->prepare($query);
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $customers = [];
            while ($row = $result->fetch_assoc()) {
                $customers[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'address' => $row['address'],
                    'governorate_name' => $row['governorate_name'],
                    'area_name' => $row['area_name'],
                    'status' => $row['status'],
                    'balance' => number_format(floatval($row['balance']), 2),
                    'balance_raw' => floatval($row['balance']),
                    'parcels_count' => intval($row['parcels_count']),
                    'total_net_amount' => number_format(floatval($row['total_net_amount'] ?? 0), 2),
                    'total_net_amount_raw' => floatval($row['total_net_amount'] ?? 0),
                    'date_created' => $row['date_created']
                ];
            }
            $stmt->close();
            
            echo json_encode([
                'status' => 'success',
                'data' => $customers,
                'total_records' => $total_records,
                'current_page' => $page,
                'total_pages' => ceil($total_records / $limit)
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'خطأ في جلب بيانات العملاء: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_customer_details':
        try {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            if ($customer_id <= 0) {
                throw new Exception('معرف العميل غير صالح');
            }
            
            // جلب بيانات العميل
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
            
            // جلب الملخص المالي
            $financial_summary = $admin->get_customer_financial_summary($customer_id);
            
            // جلب معاملات الرصيد
            $balance_transactions = $admin->get_customer_balance_transactions($customer_id, 20);
            
            // جلب الشحنات الأخيرة
            $parcels_query = "
                SELECT p.*, ps.name_ar as status_name
                FROM parcels p
                LEFT JOIN parcel_status ps ON p.status = ps.id
                WHERE p.client_id_fk = ?
                ORDER BY p.date_created DESC
                LIMIT 10
            ";
            $parcels_stmt = $conn->prepare($parcels_query);
            $parcels_stmt->bind_param("i", $customer_id);
            $parcels_stmt->execute();
            $parcels_result = $parcels_stmt->get_result();
            
            $recent_parcels = [];
            while ($parcel = $parcels_result->fetch_assoc()) {
                $recent_parcels[] = $parcel;
            }
            $parcels_stmt->close();
            
            echo json_encode([
                'status' => 'success',
                'customer' => $customer,
                'financial_summary' => $financial_summary,
                'balance_transactions' => $balance_transactions,
                'recent_parcels' => $recent_parcels
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'إجراء غير صالح']);
        break;
}

$conn->close();
?>