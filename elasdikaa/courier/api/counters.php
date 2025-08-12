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

// نطاق التاريخ اختياري
$date_from = $_GET['date_from'] ?? null;
$date_to   = $_GET['date_to'] ?? null;
$date_regex = '/^\d{4}-\d{2}-\d{2}$/';

$where = ['courier_id = ?'];
$params = [$courier_id];
$types = 'i';

if ($date_from && preg_match($date_regex, $date_from)) { $where[] = 'DATE(date_created) >= ?'; $params[] = $date_from; $types.='s'; }
if ($date_to && preg_match($date_regex, $date_to)) { $where[] = 'DATE(date_created) <= ?'; $params[] = $date_to; $types.='s'; }

$where_sql = implode(' AND ', $where);

// دالة مساعدة للعد بحالة معينة (أو مجموعة حالات)
function countByStatus(mysqli $conn, string $where_sql, string $types, array $params, array $statuses): int {
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $sql = "SELECT COUNT(*) c FROM parcels WHERE $where_sql AND status IN ($in)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $t = $types . str_repeat('i', count($statuses));
    $p = array_merge($params, $statuses);
    $stmt->bind_param($t, ...$p);
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c;
}

// عدادات مطلوبة
$all = 0; $sql_all = "SELECT COUNT(*) c FROM parcels WHERE $where_sql"; $st = $conn->prepare($sql_all); $st->bind_param($types, ...$params); $st->execute(); $all = (int)($st->get_result()->fetch_assoc()['c'] ?? 0); $st->close();

$returned = countByStatus($conn, $where_sql, $types, $params, [9,10]);
$in_delivery = countByStatus($conn, $where_sql, $types, $params, [3]);
$partial = countByStatus($conn, $where_sql, $types, $params, [6]);
$postponed = countByStatus($conn, $where_sql, $types, $params, [7]);
$rejected = countByStatus($conn, $where_sql, $types, $params, [8]);
$delivered = countByStatus($conn, $where_sql, $types, $params, [4]);

echo json_encode([
    'success' => true,
    'data' => [
        'returned' => $returned,
        'in_delivery' => $in_delivery,
        'partial' => $partial,
        'postponed' => $postponed,
        'rejected' => $rejected,
        'delivered' => $delivered,
        'all' => $all,
    ]
]);
exit;





