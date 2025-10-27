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
$date_from = $_GET['date_from'] ?? null;
$date_to   = $_GET['date_to'] ?? null;
$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$q = trim($_GET['q'] ?? '');

$where = ['p.courier_id = ?'];
$params = [$courier_id];
$types = 'i';
$date_regex = '/^\d{4}-\d{2}-\d{2}$/';
if ($date_from && preg_match($date_regex, $date_from)) { $where[] = 'DATE(p.date_created) >= ?'; $params[] = $date_from; $types.='s'; }
if ($date_to && preg_match($date_regex, $date_to)) { $where[] = 'DATE(p.date_created) <= ?'; $params[] = $date_to; $types.='s'; }
if ($status !== null) { $where[] = 'p.status = ?'; $params[] = $status; $types.='i'; }
if ($q !== '') { $where[] = '(p.tracking_number LIKE CONCAT("%", ?, "%") OR p.recipient_name LIKE CONCAT("%", ?, "%") OR p.recipient_phone LIKE CONCAT("%", ?, "%"))'; $params[]=$q; $params[]=$q; $params[]=$q; $types.='sss'; }

$sql = 'SELECT p.id, p.tracking_number, p.recipient_name, p.recipient_phone, p.status, ps.name_ar as status_name, p.date_created, p.cod_amount,
               GREATEST(0, (CASE WHEN IFNULL(p.courier_commission,0)>0 THEN IFNULL(p.courier_commission,0) ELSE IFNULL(p.delivery_agent_fee,0) END) - IFNULL(p.company_profit,0)) as courier_commission_net,
               g.name as governorate_name, a.name as area_name
        FROM parcels p
        LEFT JOIN parcel_status ps ON ps.id = p.status
        LEFT JOIN governorates g ON g.id = p.recipient_governorate_id
        LEFT JOIN areas a ON a.id = p.recipient_area_id
        WHERE '.implode(' AND ', $where).' ORDER BY p.date_created DESC LIMIT 200';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();

echo json_encode(['success'=>true,'rows'=>$rows]);
exit;


