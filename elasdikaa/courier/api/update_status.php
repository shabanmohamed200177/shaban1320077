<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db_connect.php';

SecurityMiddleware::checkSession();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['courier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$courier_id = (int)$_SESSION['courier_id'];

// منع عرض التحذيرات داخل JSON
if (function_exists('ini_set')) { @ini_set('display_errors', 0); @ini_set('log_errors', 1); }

$parcel_id = (int)($_POST['parcel_id'] ?? 0);
$new_status = (int)($_POST['new_status_id'] ?? 0);
$reason_id = isset($_POST['reason_id']) ? (int)$_POST['reason_id'] : null;
$reason_note = trim($_POST['reason_note'] ?? '');
$partial_paid = isset($_POST['partial_paid']) ? (float)$_POST['partial_paid'] : null;
$returned_pieces = isset($_POST['returned_pieces']) ? (int)$_POST['returned_pieces'] : null;
$partial_note = trim($_POST['partial_note'] ?? '');
$postpone_date = trim($_POST['postpone_date'] ?? '');

if ($parcel_id <= 0 || $new_status <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$conn->begin_transaction();
try {
    // التحقق أن الشحنة تخص هذا المندوب
    $chk = $conn->prepare('SELECT status FROM parcels WHERE id = ? AND courier_id = ? LIMIT 1');
    $chk->bind_param('ii', $parcel_id, $courier_id);
    $chk->execute();
    $r = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$r) { throw new Exception('Parcel not found or not assigned to courier'); }
    $old_status = (int)$r['status'];

    // تحديث الشحنة بحسب الحالة
    $set_extra = '';
    $params = [$new_status, $reason_id, $reason_note, $courier_id];
    $types = 'issi';
    
    if ($new_status == 7) { // مؤجل
        $set_extra .= ', postpone_date = ?';
        $params[] = ($postpone_date ?: null);
        $types .= 's';
    }
    
    if ($new_status == 6) { // دفع جزئي
        $set_extra .= ', partial_paid = ?, returned_pieces = ?, partial_note = ?';
        $params[] = ($partial_paid !== null ? $partial_paid : 0);
        $params[] = ($returned_pieces !== null ? $returned_pieces : 0);
        $params[] = $partial_note;
        $types .= 'dis';
    }
    
    $params[] = $parcel_id;
    $types .= 'i';
    
    // استخدام أسماء الأعمدة الصحيحة من قاعدة البيانات
    $sql = 'UPDATE parcels SET status = ?, status_reason_id = ?, status_reason_note = ?, status_updated_by = ?, status_updated_at = NOW() ' . $set_extra . ' WHERE id = ?';
    $up = $conn->prepare($sql);
    $up->bind_param($types, ...$params);
    $up->execute();
    $up->close();

    // سجل التاريخ في parcel_status_history
    $hist = $conn->prepare('INSERT INTO parcel_status_history (parcel_id, old_status_id, new_status_id, reason_id, reason_note, changed_by, ip_address) VALUES (?,?,?,?,?,?,?)');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $by = $_SESSION['courier_name'] ?? ('courier#'.$courier_id);
    $hist->bind_param('iiiisss', $parcel_id, $old_status, $new_status, $reason_id, $reason_note, $by, $ip);
    $hist->execute();
    $hist->close();

    // سجل تتبع في parcel_tracks
    $trk = $conn->prepare('INSERT INTO parcel_tracks (parcel_id, status, date_created, remarks) VALUES (?, ?, NOW(), ?)');
    $tracking_remarks = '';
    if ($new_status == 6) {
        $tracking_remarks = "دفع جزئي: " . ($partial_paid ?? 0) . " جنيه، قطع مرتجعة: " . ($returned_pieces ?? 0);
    } elseif ($new_status == 7) {
        $tracking_remarks = "تم التأجيل إلى: " . ($postpone_date ?? 'غير محدد');
    } elseif ($new_status == 8) {
        $tracking_remarks = "تم الرفض: " . ($reason_note ?? '');
    }
    $trk->bind_param('iis', $parcel_id, $new_status, $tracking_remarks);
    $trk->execute();
    $trk->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'تم تحديث حالة الشحنة بنجاح']);
    
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;


