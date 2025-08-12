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

$list = [];
$res = $conn->query("SELECT id, name_ar FROM parcel_status ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) { $list[] = $row; }
}
echo json_encode(['success' => true, 'data' => $list]);
exit;





