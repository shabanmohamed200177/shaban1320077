<?php
// إيقاف إظهار الأخطاء لمنع طباعة أي تحذيرات تفسد استجابة JSON
error_reporting(0);
ini_set('display_errors', 0);

// تفعيل Output Buffering لالتقاط أي مخرجات غير مرغوبة
ob_start();

// لضمان استجابة JSON
header('Content-Type: application/json');

// تضمين النظام المحدث للدفعات
include_once 'enhanced_payment_system.php';

// بيانات الاتصال بقاعدة البيانات (تم نقلها هنا)
$db_host = 'localhost';
$db_user = 'u239043057_admin1';
$db_pass = 'V~t:k|3t';
$db_name = 'u239043057_shipping';

// محاولة الاتصال بقاعدة البيانات
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// التحقق من الاتصال
if ($conn->connect_error) {
    // تجاهل أي مخرجات سابقة
    ob_end_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'فشل الاتصال بقاعدة البيانات: ' . $conn->connect_error
    ]);
    exit();
}

$response = ['status' => 'error', 'message' => 'حدث خطأ غير متوقع.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['parcel_id'], $_POST['new_status'])) {
    $parcel_id = intval($_POST['parcel_id']);
    $new_status = intval($_POST['new_status']);
    $status_reason_id = isset($_POST['status_reason_id']) ? intval($_POST['status_reason_id']) : null;
    $status_reason_note = isset($_POST['status_reason_note']) ? trim($_POST['status_reason_note']) : '';
    
    // التحقق من إرسال السبب للحالات التي تتطلب ذلك (7=تأجيل، 8=رفض)
    if (($new_status == 7 || $new_status == 8) && empty($status_reason_id)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'يجب اختيار سبب لهذه الحالة.']);
        exit();
    }
    
    $parcel_qry = $conn->query("SELECT * FROM parcels WHERE id = $parcel_id");
    if ($parcel_qry && $parcel_qry->num_rows > 0) {
        $parcel_data = $parcel_qry->fetch_assoc();
        $agent_id = $parcel_data['agent_id'];
        $parcel_value = $parcel_data['price'];
        $old_status = $parcel_data['status'];

        // تحديث حالة الشحنة مع السبب
        if ($status_reason_id) {
            $stmt_update_parcel = $conn->prepare("UPDATE parcels SET status = ?, status_reason_id = ?, status_reason_note = ?, status_updated_at = NOW(), status_updated_by = ? WHERE id = ?");
            $changed_by = 'system'; // يمكن تخصيصه حسب النظام
            $stmt_update_parcel->bind_param("iissi", $new_status, $status_reason_id, $status_reason_note, $changed_by, $parcel_id);
        } else {
            $stmt_update_parcel = $conn->prepare("UPDATE parcels SET status = ?, status_updated_at = NOW(), status_updated_by = ? WHERE id = ?");
            $changed_by = 'system';
            $stmt_update_parcel->bind_param("isi", $new_status, $changed_by, $parcel_id);
        }
        
        if ($stmt_update_parcel) {
            if ($stmt_update_parcel->execute()) {
                
                // إضافة سجل تتبع تقليدي
                $stmt_insert_history = $conn->prepare("INSERT INTO parcel_tracks (parcel_id, status) VALUES (?, ?)");
                if ($stmt_insert_history) {
                    $stmt_insert_history->bind_param("ii", $parcel_id, $new_status);
                    $stmt_insert_history->execute();
                    $stmt_insert_history->close();
                }
                
                // تحديث حالة الدفع تلقائياً باستخدام النظام المحدث
                $payment_system = new EnhancedPaymentSystem($conn);
                $payment_system->updateShipmentStatus($parcel_id);
                
                // إضافة سجل تاريخ مفصل مع السبب (إذا وُجد)
                if ($status_reason_id) {
                    $history_sql = $conn->prepare("INSERT INTO parcel_status_history (parcel_id, old_status_id, new_status_id, reason_id, reason_note, changed_by, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($history_sql) {
                        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $changed_by = 'system';
                        $history_sql->bind_param("iiiiiss", $parcel_id, $old_status, $new_status, $status_reason_id, $status_reason_note, $changed_by, $user_ip);
                        $history_sql->execute();
                        $history_sql->close();
                    }
                }

                if ($new_status == 8) {
                    $check_commission_q = $conn->query("SELECT id FROM agent_transactions WHERE shipment_id = $parcel_id AND transaction_type = 'commission'");
                    if ($check_commission_q && $check_commission_q->num_rows == 0) {
                        $agent_data_q = $conn->query("SELECT commission_type, commission_value FROM agents WHERE id = $agent_id");
                        if ($agent_data_q && $agent_data_q->num_rows > 0) {
                            $agent_data = $agent_data_q->fetch_assoc();
                            
                            if ($agent_data) {
                                $commission_type = $agent_data['commission_type'];
                                $commission_value = $agent_data['commission_value'];
                                $calculated_commission = ($commission_type == 'fixed') ? $commission_value : ($parcel_value * $commission_value) / 100;

                                if ($calculated_commission > 0) {
                                    $description = "عمولة على الشحنة رقم: " . $parcel_data['reference_number'];
                                    $stmt_insert_commission = $conn->prepare("INSERT INTO agent_transactions (agent_id, transaction_type, amount, description, shipment_id) VALUES (?, 'commission', ?, ?, ?)");
                                    if ($stmt_insert_commission) {
                                        $stmt_insert_commission->bind_param("idsi", $agent_id, $calculated_commission, $description, $parcel_id);
                                        $stmt_insert_commission->execute();
                                        $stmt_insert_commission->close();
                                    }
                                }
                            }
                        }
                    }
                }
                $response['status'] = 'success';
                $response['message'] = 'تم تحديث حالة الشحنة بنجاح.';
            } else {
                $response['message'] = 'فشل تحديث حالة الشحنة: ' . $conn->error;
            }
            $stmt_update_parcel->close();
        } else {
            $response['message'] = 'فشل في إعداد تحديث SQL: ' . $conn->error;
        }
    } else {
        $response['message'] = 'الشحنة غير موجودة.';
    }
} else {
    $response['message'] = 'البيانات غير مكتملة.';
}

// تجاهل أي مخرجات سابقة وإرسال استجابة JSON فقط
ob_end_clean();
echo json_encode($response);
$conn->close();