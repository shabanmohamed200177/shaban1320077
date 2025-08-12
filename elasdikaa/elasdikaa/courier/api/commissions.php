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
$date_regex = '/^\d{4}-\d{2}-\d{2}$/';

$where = ['p.courier_id = ?','p.status = 4'];
$params = [$courier_id];
$types = 'i';

if ($date_from && preg_match($date_regex, $date_from)) { $where[] = 'DATE(p.date_created) >= ?'; $params[] = $date_from; $types.='s'; }
if ($date_to && preg_match($date_regex, $date_to)) { $where[] = 'DATE(p.date_created) <= ?'; $params[] = $date_to; $types.='s'; }

$where_sql = implode(' AND ', $where);

// Summary
$sum_sql = 'SELECT COUNT(*) as delivered_count, 
                   IFNULL(SUM(p.cod_amount),0) as cod_delivered, 
                   SUM(GREATEST(0, (CASE WHEN IFNULL(p.courier_commission,0)>0 THEN IFNULL(p.courier_commission,0) ELSE IFNULL(p.delivery_agent_fee,0) END) - IFNULL(p.company_profit,0))) as commission_total
            FROM parcels p WHERE '.$where_sql;
$stmt = $conn->prepare($sum_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Rows
$rows_sql = 'SELECT p.id, p.tracking_number, p.date_created, p.cod_amount,
                    GREATEST(0, (CASE WHEN IFNULL(p.courier_commission,0)>0 THEN IFNULL(p.courier_commission,0) ELSE IFNULL(p.delivery_agent_fee,0) END) - IFNULL(p.company_profit,0)) as courier_commission_net,
                    g.name as governorate_name, a.name as area_name, p.recipient_name
             FROM parcels p 
             LEFT JOIN governorates g ON g.id = p.recipient_governorate_id
             LEFT JOIN areas a ON a.id = p.recipient_area_id
             WHERE '.$where_sql.' ORDER BY p.date_created DESC';
$stmt2 = $conn->prepare($rows_sql);
$stmt2->bind_param($types, ...$params);
$stmt2->execute();
$res = $stmt2->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $r['cod_amount'] = (float)($r['cod_amount'] ?? 0);
    $r['courier_commission_net'] = (float)($r['courier_commission_net'] ?? 0);
    $rows[] = $r;
}
$stmt2->close();

echo json_encode([
    'success' => true,
    'summary' => [
        'delivered_count' => (int)($summary['delivered_count'] ?? 0),
        'cod_delivered' => (float)($summary['cod_delivered'] ?? 0),
        'commission_total' => (float)($summary['commission_total'] ?? 0),
    ],
    'rows' => $rows
]);
exit;


