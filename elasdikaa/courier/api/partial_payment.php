<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db_connect.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'PaymentService.php';

SecurityMiddleware::checkSession();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['courier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$courier_id = (int)$_SESSION['courier_id'];

// منع عرض التحذيرات داخل JSON
if (function_exists('ini_set')) { 
    @ini_set('display_errors', 0); 
    @ini_set('log_errors', 1); 
}

// التحقق من نوع الطلب
$action = $_POST['action'] ?? '';

if ($action === 'process_payment') {
    processPartialPayment();
} elseif ($action === 'get_payment_info') {
    getPaymentInfo();
} else {
    echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
    exit;
}

/**
 * معالجة الدفع الجزئي
 */
function processPartialPayment() {
    global $conn, $courier_id;
    
    $parcel_id = (int)($_POST['parcel_id'] ?? 0);
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);
    $payment_note = trim($_POST['payment_note'] ?? '');
    $returned_pieces = isset($_POST['returned_pieces']) ? (int)$_POST['returned_pieces'] : 0;
    
    if ($parcel_id <= 0 || $payment_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
        exit;
    }

    try {
        // تأكيد ملكية الشحنة للمندوب والحصول على الحالة القديمة
        $chk = $conn->prepare('SELECT id, status FROM parcels WHERE id = ? AND courier_id = ? LIMIT 1');
        $chk->bind_param('ii', $parcel_id, $courier_id);
        $chk->execute();
        $parcel = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$parcel) {
            throw new Exception('الشحنة غير موجودة أو غير مخصصة لهذا المندوب');
        }

        $old_status = (int)$parcel['status'];
        
        // استدعاء خدمة الدفع الموحّدة
        $result = PaymentService::applyPartialPayment(
            $conn,
            $parcel_id,
            $payment_amount,
            'courier',
            $payment_note,
            $courier_id,
            $_SESSION['courier_name'] ?? ('courier#'.$courier_id)
        );

        // إذا كان هناك تسليم جزئي، سجّل المرتجع بناءً على المتبقي غير المُحصل نقدًا (raw)
        if ($returned_pieces > 0) {
            // اجلب إجمالي المطلوب
            $info = PaymentService::getPaymentInfo($conn, $parcel_id);
            $totalToCollect = (float)$info['total_to_collect'];

            // تحقق من وجود عمود source لاستخدام تحصيلات المندوب فقط
            $hasSource = false;
            $colRes = $conn->query("SHOW COLUMNS FROM parcel_collections LIKE 'source'");
            if ($colRes && $colRes->num_rows > 0) { $hasSource = true; }
            if ($colRes) { $colRes->close(); }

            if ($hasSource) {
                $sumStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM parcel_collections WHERE parcel_id = ? AND source = 'courier'");
            } else {
                $sumStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM parcel_collections WHERE parcel_id = ? AND collected_by_courier_id IS NOT NULL");
            }
            $sumStmt->bind_param('i', $parcel_id);
            $sumStmt->execute();
            $sumRaw = (float)($sumStmt->get_result()->fetch_assoc()['s'] ?? 0);
            $sumStmt->close();

            // قيمة المرتجع النظرية = المطلوب - إجمالي ما حُصّل نقدًا من العميل
            $targetReturn = max(0.0, $totalToCollect - $sumRaw);

            // خصم ما سبق تسجيله كمرتجع
            $retSumStmt = $conn->prepare('SELECT COALESCE(SUM(returned_value),0) AS r FROM parcel_returns WHERE parcel_id = ?');
            $retSumStmt->bind_param('i', $parcel_id);
            $retSumStmt->execute();
            $alreadyReturned = (float)($retSumStmt->get_result()->fetch_assoc()['r'] ?? 0);
            $retSumStmt->close();

            $returnDelta = max(0.0, $targetReturn - $alreadyReturned);
            if ($returnDelta > 0.0) {
                $insRet = $conn->prepare('INSERT INTO parcel_returns (parcel_id, returned_items_count, returned_value, return_reason, return_method, processed_by, notes) VALUES (?, ?, ?, \'Partial delivery\', \'partial\', ?, ?)');
                $processedBy = $_SESSION['courier_name'] ?? ('courier#'.$courier_id);
                $insRet->bind_param('iidds', $parcel_id, $returned_pieces, $returnDelta, $processedBy, $payment_note);
                // Adjust bind types: parcel_id(int), pieces(int), value(decimal->d), processed_by(string), notes(string)
                $insRet->close();
                $insRet = $conn->prepare('INSERT INTO parcel_returns (parcel_id, returned_items_count, returned_value, return_reason, return_method, processed_by, notes) VALUES (?, ?, ?, \'Partial delivery\', \'partial\', ?, ?)');
                $insRet->bind_param('iiddss', $parcel_id, $returned_pieces, $returnDelta, $processedBy, $payment_note);
                $insRet->execute();
                $insRet->close();
            }
        }

        // حفظ تاريخ الحالة (History)
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $reason_note = 'دفع جزئي: ' . number_format($payment_amount, 2) . ' جنيه. المتبقي: ' . number_format($result['remaining_balance'], 2) . ' جنيه';
        $history_sql = 'INSERT INTO parcel_status_history (parcel_id, old_status_id, new_status_id, reason_id, reason_note, changed_by, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $history_stmt = $conn->prepare($history_sql);
        $nullReason = null;
        $changed_by = $_SESSION['courier_name'] ?? ('courier#'.$courier_id);
        $history_stmt->bind_param('iiissss', $parcel_id, $old_status, $result['new_status'], $nullReason, $reason_note, $changed_by, $ip_address);
        $history_stmt->execute();
        $history_stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'تم تسجيل الدفع بنجاح',
            'data' => $result
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * الحصول على معلومات الدفع للشحنة
 */
function getPaymentInfo() {
    global $conn, $courier_id;
    
    $parcel_id = (int)($_POST['parcel_id'] ?? 0);
    
    if ($parcel_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرف الشحنة غير صحيح']);
        exit;
    }
    
    try {
        // تأكيد ملكية الشحنة للمندوب
        $chk = $conn->prepare('SELECT id FROM parcels WHERE id = ? AND courier_id = ? LIMIT 1');
        $chk->bind_param('ii', $parcel_id, $courier_id);
        $chk->execute();
        $owns = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$owns) {
            echo json_encode(['success' => false, 'message' => 'الشحنة غير موجودة']);
            exit;
        }

        $info = PaymentService::getPaymentInfo($conn, $parcel_id);
        $data = [
            'total_to_collect' => $info['total_to_collect'],
            'paid_amount' => $info['paid_amount'],
            'partial_paid' => 0.0, // لم نعد نستخدم partial_paid كتخزين منفصل
            'total_paid_so_far' => $info['paid_amount'],
            'remaining_balance' => $info['remaining_balance'],
            'payment_status' => $info['payment_status'],
            'shipment_direction' => $info['shipment_direction'],
            'shipping_payer' => $info['shipping_payer'],
            'delivery_agent_fee' => $info['delivery_agent_fee'],
            'shipping_fees' => $info['shipping_fees']
        ];
        echo json_encode(['success' => true, 'data' => $data]);
        
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
