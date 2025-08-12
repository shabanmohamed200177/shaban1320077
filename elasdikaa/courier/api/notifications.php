<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db_connect.php';

SecurityMiddleware::checkSession();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['courier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$courier_id = (int)$_SESSION['courier_id'];
$action = $_GET['action'] ?? 'get';

switch($action) {
    case 'get':
        // فحص الطلبات الجديدة
        $check_new = isset($_GET['check_new']) && $_GET['check_new'] == '1';
        
        if ($check_new) {
            // فحص الطلبات الجديدة فقط
            $sql = 'SELECT p.id, p.tracking_number, p.recipient_name, p.status, ps.name_ar as status_name,
                            p.date_created, p.cod_amount, p.courier_commission_net, g.name as governorate_name, a.name as area_name
                     FROM parcels p
                     LEFT JOIN parcel_status ps ON ps.id = p.status
                     LEFT JOIN governorates g ON g.id = p.recipient_governorate_id
                     LEFT JOIN areas a ON a.id = p.recipient_area_id
                     WHERE p.courier_id = ? AND p.status IN (2,3)
                     AND p.date_created >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                     ORDER BY p.date_created DESC
                     LIMIT 5';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $courier_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $new_shipments = [];
            
            while ($row = $res->fetch_assoc()) {
                $new_shipments[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'new_shipments' => $new_shipments
            ]);
        } else {
            // جلب الإشعارات العادية
            $sql = 'SELECT p.id, p.tracking_number, p.recipient_name, p.status, ps.name_ar as status_name,
                            p.date_created, p.cod_amount, g.name as governorate_name, a.name as area_name
                     FROM parcels p
                     LEFT JOIN parcel_status ps ON ps.id = p.status
                     LEFT JOIN governorates g ON g.id = p.recipient_governorate_id
                     LEFT JOIN areas a ON a.id = p.recipient_area_id
                     WHERE p.courier_id = ? AND p.status IN (2,3)
                     AND p.date_created >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     ORDER BY p.date_created DESC
                     LIMIT 10';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $courier_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $notifications = [];
            
            while ($row = $res->fetch_assoc()) {
                $notifications[] = [
                    'id' => $row['id'],
                    'type' => 'new_shipment',
                    'title' => 'شحنة جديدة',
                    'message' => 'تم تعيين شحنة جديدة لك: ' . $row['tracking_number'],
                    'data' => $row,
                    'time' => $row['date_created'],
                    'read' => false
                ];
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
        }
        break;
        
    case 'mark_read':
        // تحديد الإشعار كمقروء
        $notification_id = (int)$_POST['notification_id'] ?? 0;
        if ($notification_id > 0) {
            // يمكن إضافة جدول للإشعارات المقروءة هنا
            echo json_encode(['success' => true, 'message' => 'تم تحديد الإشعار كمقروء']);
        } else {
            echo json_encode(['success' => false, 'message' => 'معرف الإشعار غير صحيح']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
}

exit;
