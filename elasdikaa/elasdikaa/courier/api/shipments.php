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

// إذا تم تمرير ID، جلب شحنة واحدة
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $parcel_id = (int)$_GET['id'];
    
    $single_sql = 'SELECT 
                     p.id,
                     p.tracking_number,
                     p.recipient_name,
                     p.recipient_phone,
                     p.cod_amount,
                     p.total_to_collect,
                     p.status,
                     p.date_created,
                     p.courier_commission,
                     p.delivery_agent_fee,
                     p.company_profit,
                     p.amount_paid_so_far,
                     ps.name_ar as status_name,
                     psr.reason_text as status_reason_text,
                     g.name as governorate_name,
                     a.name as area_name
                    FROM parcels p 
                   LEFT JOIN parcel_status ps ON ps.id = p.status
                   LEFT JOIN parcel_status_reasons psr ON psr.id = p.status_reason_id
                   LEFT JOIN governorates g ON g.id = p.recipient_governorate_id
                   LEFT JOIN areas a ON a.id = p.recipient_area_id
                   WHERE p.id = ? AND p.courier_id = ? LIMIT 1';
    
    $stmt = $conn->prepare($single_sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
        exit;
    }
    
    $stmt->bind_param('ii', $parcel_id, $courier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $parcel = $result->fetch_assoc();
    $stmt->close();
    
    if (!$parcel) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Parcel not found']);
        exit;
    }
    
    // معالجة البيانات
    $parcel['cod_amount'] = (float)($parcel['cod_amount'] ?? 0);
    $parcel['total_to_collect'] = isset($parcel['total_to_collect']) ? (float)$parcel['total_to_collect'] : $parcel['cod_amount'];
    $parcel['status'] = (int)$parcel['status'];
    
    // حساب صافي عمولة المندوب
    $courier_commission = (float)($parcel['courier_commission'] ?? 0);
    $delivery_agent_fee = (float)($parcel['delivery_agent_fee'] ?? 0);
    $company_profit = (float)($parcel['company_profit'] ?? 0);
    
    $parcel['courier_commission_net'] = max(0, 
        ($courier_commission > 0 ? $courier_commission : $delivery_agent_fee) - $company_profit
    );
    
    echo json_encode([
        'success' => true,
        'data' => $parcel
    ]);
    exit;
}

$statusParam = isset($_GET['status']) ? trim($_GET['status']) : '';
$statuses = [];
if ($statusParam !== '') {
    foreach (explode(',', $statusParam) as $s) {
        $s = trim($s);
        if ($s === '') continue;
        if (ctype_digit($s)) { $statuses[] = (int)$s; }
    }
}
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$page_size = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
$offset = ($page - 1) * $page_size;
$date_from = $_GET['date_from'] ?? null;
$date_to   = $_GET['date_to'] ?? null;

$where = ['courier_id = ?'];
$params = [$courier_id];
$types = 'i';

if (!empty($statuses)) {
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $where[] = "status IN ($in)";
    $params = array_merge($params, $statuses);
    $types  .= str_repeat('i', count($statuses));
}

$date_regex = '/^\d{4}-\d{2}-\d{2}$/';
if ($date_from && preg_match($date_regex, $date_from)) {
    $where[] = 'DATE(date_created) >= ?';
    $params[] = $date_from;
    $types   .= 's';
}
if ($date_to && preg_match($date_regex, $date_to)) {
    $where[] = 'DATE(date_created) <= ?';
    $params[] = $date_to;
    $types   .= 's';
}

if ($q !== '') {
    $where[] = '(tracking_number LIKE CONCAT("%", ?, "%") OR recipient_name LIKE CONCAT("%", ?, "%") OR recipient_phone LIKE CONCAT("%", ?, "%"))';
    $params[] = $q; $params[] = $q; $params[] = $q;
    $types   .= 'sss';
}

$where_sql = implode(' AND ', $where);

// إجمالي السجلات
$count_sql = 'SELECT COUNT(*) as cnt FROM parcels WHERE ' . $where_sql;
$stmt = $conn->prepare($count_sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$count_res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = (int)($count_res['cnt'] ?? 0);

// جلب الصفحة
$list_sql = 'SELECT 
               p.id,
               p.tracking_number,
               p.recipient_name,
               p.recipient_phone,
               p.cod_amount,
               p.total_to_collect,
               p.status,
               p.date_created,
               p.courier_commission,
               p.delivery_agent_fee,
               p.company_profit,
               p.amount_paid_so_far,
               ps.name_ar as status_name,
                psr.reason_text as status_reason_text,
               g.name as governorate_name,
               a.name as area_name
              FROM parcels p 
             LEFT JOIN parcel_status ps ON ps.id = p.status
              LEFT JOIN parcel_status_reasons psr ON psr.id = p.status_reason_id
             LEFT JOIN governorates g ON g.id = p.recipient_governorate_id
             LEFT JOIN areas a ON a.id = p.recipient_area_id
             WHERE ' . $where_sql . ' ORDER BY p.date_created DESC LIMIT ? OFFSET ?';

$stmt2 = $conn->prepare($list_sql);
if (!$stmt2) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}

// إضافة نوعي limit/offset
$bind_types = $types . 'ii';
$bind_params = array_merge($params, [$page_size, $offset]);
$stmt2->bind_param($bind_types, ...$bind_params);
$stmt2->execute();
$rs = $stmt2->get_result();
$rows = [];
while ($r = $rs->fetch_assoc()) {
    $r['cod_amount'] = (float)($r['cod_amount'] ?? 0);
    $r['total_to_collect'] = isset($r['total_to_collect']) ? (float)$r['total_to_collect'] : $r['cod_amount'];
    $r['status'] = (int)$r['status'];
    
    // حساب صافي عمولة المندوب
    $courier_commission = (float)($r['courier_commission'] ?? 0);
    $delivery_agent_fee = (float)($r['delivery_agent_fee'] ?? 0);
    $company_profit = (float)($r['company_profit'] ?? 0);
    
    $r['courier_commission_net'] = max(0, 
        ($courier_commission > 0 ? $courier_commission : $delivery_agent_fee) - $company_profit
    );
    
    $rows[] = $r;
}
$stmt2->close();

echo json_encode([
    'success' => true,
    'page' => $page,
    'page_size' => $page_size,
    'total' => $total,
    'data' => $rows
]);
exit;


