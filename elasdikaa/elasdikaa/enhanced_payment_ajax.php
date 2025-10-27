<?php
/**
 * AJAX Handler for Enhanced Payment System
 * معالج AJAX للنظام المحدث للدفعات
 */

// إيقاف إظهار الأخطاء لمنع طباعة أي تحذيرات تفسد استجابة JSON
error_reporting(0);
ini_set('display_errors', 0);

// تفعيل Output Buffering لالتقاط أي مخرجات غير مرغوبة
ob_start();

// لضمان استجابة JSON
header('Content-Type: application/json');

// تضمين ملفات الاتصال بقاعدة البيانات والنظام المحدث
include 'db_connect.php';
include 'enhanced_payment_system.php';

$response = ['success' => false, 'message' => 'حدث خطأ غير متوقع.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // إنشاء كائن النظام المحدث
    $payment_system = new EnhancedPaymentSystem($conn);
    
    switch ($action) {
        case 'add_payment':
            $parcel_id = isset($_POST['parcel_id']) ? intval($_POST['parcel_id']) : 0;
            $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
            $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
            $reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
            $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
            $processed_by = isset($_SESSION['login_firstname']) ? $_SESSION['login_firstname'] : 'admin';
            
            $response = $payment_system->addPayment($parcel_id, $payment_amount, $payment_method, $reference_number, $notes, $processed_by);
            break;
            
        case 'record_delivery':
            $parcel_id = isset($_POST['parcel_id']) ? intval($_POST['parcel_id']) : 0;
            $delivered_value = isset($_POST['delivered_value']) ? floatval($_POST['delivered_value']) : 0;
            $delivery_notes = isset($_POST['delivery_notes']) ? trim($_POST['delivery_notes']) : '';
            $processed_by = isset($_SESSION['login_firstname']) ? $_SESSION['login_firstname'] : 'admin';
            
            $response = $payment_system->recordDelivery($parcel_id, $delivered_value, $delivery_notes, $processed_by);
            break;
            
        case 'record_return':
            $parcel_id = isset($_POST['parcel_id']) ? intval($_POST['parcel_id']) : 0;
            $return_value = isset($_POST['return_value']) ? floatval($_POST['return_value']) : 0;
            $return_reason = isset($_POST['return_reason']) ? trim($_POST['return_reason']) : '';
            $items_count = isset($_POST['items_count']) ? intval($_POST['items_count']) : 1;
            $processed_by = isset($_SESSION['login_firstname']) ? $_SESSION['login_firstname'] : 'admin';
            
            $response = $payment_system->recordReturn($parcel_id, $return_value, $return_reason, $items_count, $processed_by);
            break;
            
        case 'get_parcel_financial_data':
            $parcel_id = isset($_POST['parcel_id']) ? intval($_POST['parcel_id']) : 0;
            
            if ($parcel_id > 0) {
                $data = $payment_system->getParcelFinancialData($parcel_id);
                if ($data) {
                    $response = ['success' => true, 'data' => $data];
                } else {
                    $response = ['success' => false, 'message' => 'الشحنة غير موجودة'];
                }
            } else {
                $response = ['success' => false, 'message' => 'معرف الشحنة غير صالح'];
            }
            break;
            
        case 'get_payment_history':
            $parcel_id = isset($_POST['parcel_id']) ? intval($_POST['parcel_id']) : 0;
            
            if ($parcel_id > 0) {
                $payments = $payment_system->getParcelPaymentHistory($parcel_id);
                $response = ['success' => true, 'payments' => $payments];
            } else {
                $response = ['success' => false, 'message' => 'معرف الشحنة غير صالح'];
            }
            break;
            
        case 'get_returns':
            $parcel_id = isset($_POST['parcel_id']) ? intval($_POST['parcel_id']) : 0;
            
            if ($parcel_id > 0) {
                $returns = $payment_system->getParcelReturns($parcel_id);
                $response = ['success' => true, 'returns' => $returns];
            } else {
                $response = ['success' => false, 'message' => 'معرف الشحنة غير صالح'];
            }
            break;
            
        case 'get_financial_summary':
            $parcel_id = isset($_POST['parcel_id']) ? intval($_POST['parcel_id']) : 0;
            
            if ($parcel_id > 0) {
                $summary = $payment_system->getParcelFinancialSummary($parcel_id);
                if ($summary) {
                    $response = ['success' => true, 'summary' => $summary];
                } else {
                    $response = ['success' => false, 'message' => 'الشحنة غير موجودة'];
                }
            } else {
                $response = ['success' => false, 'message' => 'معرف الشحنة غير صالح'];
            }
            break;
            
        case 'update_shipment_status':
            $parcel_id = isset($_POST['parcel_id']) ? intval($_POST['parcel_id']) : 0;
            
            if ($parcel_id > 0) {
                $result = $payment_system->updateShipmentStatus($parcel_id);
                if ($result) {
                    $response = ['success' => true, 'message' => 'تم تحديث حالة الشحنة بنجاح'];
                } else {
                    $response = ['success' => false, 'message' => 'خطأ في تحديث حالة الشحنة'];
                }
            } else {
                $response = ['success' => false, 'message' => 'معرف الشحنة غير صالح'];
            }
            break;
            
        case 'get_financial_stats':
            $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
            $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
            
            $stats = $payment_system->getFinancialStats($date_from, $date_to);
            $response = ['success' => true, 'stats' => $stats];
            break;
            
        case 'bulk_payment_update':
            $parcel_ids = isset($_POST['parcel_ids']) ? $_POST['parcel_ids'] : [];
            $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
            $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
            $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
            $processed_by = isset($_SESSION['login_firstname']) ? $_SESSION['login_firstname'] : 'admin';
            
            if (is_array($parcel_ids) && count($parcel_ids) > 0 && $payment_amount > 0) {
                $conn->begin_transaction();
                $success_count = 0;
                $errors = [];
                
                try {
                    foreach ($parcel_ids as $parcel_id) {
                        $parcel_id = intval($parcel_id);
                        if ($parcel_id > 0) {
                            $result = $payment_system->addPayment($parcel_id, $payment_amount, $payment_method, '', $notes, $processed_by);
                            if ($result['success']) {
                                $success_count++;
                            } else {
                                $errors[] = "الشحنة #{$parcel_id}: " . $result['message'];
                            }
                        }
                    }
                    
                    if ($success_count > 0) {
                        $conn->commit();
                        $response = [
                            'success' => true,
                            'message' => "تم معالجة {$success_count} شحنة بنجاح",
                            'success_count' => $success_count,
                            'errors' => $errors
                        ];
                    } else {
                        $conn->rollback();
                        $response = [
                            'success' => false,
                            'message' => 'لم يتم معالجة أي شحنة بنجاح',
                            'errors' => $errors
                        ];
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $response = ['success' => false, 'message' => 'خطأ في المعالجة الجماعية: ' . $e->getMessage()];
                }
            } else {
                $response = ['success' => false, 'message' => 'بيانات غير صالحة للمعالجة الجماعية'];
            }
            break;
            
        case 'search_parcels':
            $search_term = isset($_POST['search_term']) ? trim($_POST['search_term']) : '';
            $search_type = isset($_POST['search_type']) ? $_POST['search_type'] : 'tracking_number';
            
            if (!empty($search_term)) {
                $search_conditions = [];
                $search_params = [];
                $param_types = '';
                
                switch ($search_type) {
                    case 'tracking_number':
                        $search_conditions[] = "tracking_number LIKE ?";
                        $search_params[] = "%{$search_term}%";
                        $param_types .= 's';
                        break;
                        
                    case 'sender_phone':
                        $search_conditions[] = "sender_phone LIKE ?";
                        $search_params[] = "%{$search_term}%";
                        $param_types .= 's';
                        break;
                        
                    case 'recipient_name':
                        $search_conditions[] = "recipient_name LIKE ?";
                        $search_params[] = "%{$search_term}%";
                        $param_types .= 's';
                        break;
                        
                    default:
                        $search_conditions[] = "(tracking_number LIKE ? OR sender_phone LIKE ? OR recipient_name LIKE ?)";
                        $search_params[] = "%{$search_term}%";
                        $search_params[] = "%{$search_term}%";
                        $search_params[] = "%{$search_term}%";
                        $param_types .= 'sss';
                        break;
                }
                
                $sql = "SELECT 
                    id, tracking_number, sender_phone, recipient_name,
                    total_shipment_value, delivered_value, returned_value,
                    amount_paid_so_far, remaining_balance, detailed_payment_status,
                    status, date_created
                    FROM parcels 
                    WHERE " . implode(' OR ', $search_conditions) . "
                    ORDER BY date_created DESC 
                    LIMIT 50";
                
                $stmt = $conn->prepare($sql);
                if (!empty($search_params)) {
                    $stmt->bind_param($param_types, ...$search_params);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                
                $parcels = [];
                while ($row = $result->fetch_assoc()) {
                    $parcels[] = $row;
                }
                $stmt->close();
                
                $response = ['success' => true, 'parcels' => $parcels];
            } else {
                $response = ['success' => false, 'message' => 'يرجى إدخال مصطلح البحث'];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'إجراء غير صالح'];
            break;
    }
}

// تجاهل أي مخرجات سابقة وإرسال استجابة JSON فقط
ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);

if (isset($conn)) {
    $conn->close();
}
?>
