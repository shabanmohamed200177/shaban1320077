<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db_connect.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Settings.php';

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

$where = ['courier_id = ?'];
$params = [$courier_id];
$types = 'i';

// التحقق البسيط للتواريخ بصيغة YYYY-MM-DD
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

$sql = 'SELECT 
            COUNT(*) as total_rows,
            SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered_count,
            SUM(IFNULL(IFNULL(total_to_collect, cod_amount),0)) as cod_total,
            SUM(CASE WHEN status = 4 THEN IFNULL(IFNULL(total_to_collect, cod_amount),0) ELSE 0 END) as cod_delivered,
            SUM(CASE WHEN status = 4 THEN IFNULL(IFNULL(courier_commission, delivery_agent_fee),0) ELSE 0 END) as commission_total,
            SUM(CASE WHEN status = 4 THEN IFNULL(company_profit,0) ELSE 0 END) as company_profit_total,
            SUM(CASE WHEN status = 4 THEN IFNULL(IFNULL(courier_commission, delivery_agent_fee),0) ELSE 0 END) as wallet_commission
        FROM parcels
        WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

$total_rows = (int)($row['total_rows'] ?? 0);
$delivered  = (int)($row['delivered_count'] ?? 0);
$in_progress = max(0, $total_rows - $delivered);
$cod_total  = (float)($row['cod_total'] ?? 0);
$cod_delivered = (float)($row['cod_delivered'] ?? 0);
$commission_total = (float)($row['commission_total'] ?? 0);
$company_profit_total = (float)($row['company_profit_total'] ?? 0);
$wallet_like = (float)($row['wallet_commission'] ?? 0);
$uncollected = max(0.0, $cod_total - $cod_delivered);

// إحصائيات الصفحة الرئيسية: الشحنات الجارية فقط (status IN 2,3)
// استرداد إعدادات خصم الشركة من عمولة المندوب (من جدول app_settings المستقل)
$cutType = AppSettings::get($conn, 'commission_cut_type', 'fixed'); // fixed | percent
$cutVal  = (float) AppSettings::get($conn, 'commission_cut_value', '0');

// نبني تعبير الخصم: إذا كان company_profit موجودًا نستخدمه، وإلا نحسب من الإعداد العام
$active_sql = 'SELECT 
                 COUNT(*) as total_active,
                 SUM(IFNULL(IFNULL(total_to_collect, cod_amount),0)) as cod_total_active,
                 SUM(CASE WHEN IFNULL(courier_commission,0) > 0 THEN IFNULL(courier_commission,0) ELSE IFNULL(delivery_agent_fee,0) END) as sum_commission_active,
                 SUM(IFNULL(company_profit,0)) as sum_company_profit_active,
                 SUM(GREATEST(0, (CASE WHEN IFNULL(courier_commission,0) > 0 THEN IFNULL(courier_commission,0) ELSE IFNULL(delivery_agent_fee,0) END) - IFNULL(company_profit,0))) as net_courier_commission_active,
                 SUM(IFNULL(amount_paid_so_far,0)) as collected_active
               FROM parcels
               WHERE ' . implode(' AND ', array_merge($where, ['status IN (2,3)']));

$stmtA = $conn->prepare($active_sql);
if ($stmtA) {
    $stmtA->bind_param($types, ...$params);
    $stmtA->execute();
    $ar = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();
} else {
    $ar = ['total_active'=>0, 'cod_total_active'=>0.0, 'commission_net_active'=>0.0, 'collected_active'=>0.0];
}

// رصيد المندوب (إن وجد) من جدول المحافظ
$wallet_balance = 0.0;
$wq = $conn->prepare("SELECT balance FROM wallets WHERE party_type='courier' AND party_id = ? LIMIT 1");
if ($wq) { $wq->bind_param('i', $courier_id); $wq->execute(); $wr = $wq->get_result()->fetch_assoc(); $wallet_balance = (float)($wr['balance'] ?? 0); $wq->close(); }

echo json_encode([
    'success' => true,
    'data' => [
        'total_assigned' => $total_rows,
        'in_progress' => $in_progress,
        'delivered' => $delivered,
        'cod_total' => $cod_total,
        'cod_delivered' => $cod_delivered,
        'cod_uncollected' => $uncollected,
        'commission_total' => $commission_total,
        'company_profit_total' => $company_profit_total,
        'wallet_balance' => $wallet_balance,
        'wallet_commission' => $wallet_like,

        // active-only metrics for dashboard main view
        'total_active' => (int)($ar['total_active'] ?? 0),
        'cod_total_active' => (float)($ar['cod_total_active'] ?? 0),
        'cod_collected_active' => (float)($ar['collected_active'] ?? 0),
        'cod_uncollected_active' => max(0.0, (float)($ar['cod_total_active'] ?? 0) - (float)($ar['collected_active'] ?? 0)),
        'sum_commission_active' => (float)($ar['sum_commission_active'] ?? 0),
        'sum_company_profit_active' => (float)($ar['sum_company_profit_active'] ?? 0),
        'net_courier_commission_active' => (float)($ar['net_courier_commission_active'] ?? 0)
    ]
]);
exit;


