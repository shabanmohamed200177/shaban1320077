<?php
session_start();
include '../db_connect.php';

// التحقق من تسجيل دخول المندوب
if (!isset($_SESSION['courier_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

$courier_id = $_GET['courier_id'] ?? $_SESSION['courier_id'];

try {
    // جلب إحصائيات الشحنات
    $stats_query = "SELECT 
        COUNT(CASE WHEN status = 'new' THEN 1 END) as new_count,
        COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_count,
        COUNT(CASE WHEN status = 'picked_up' THEN 1 END) as picked_count,
        COUNT(CASE WHEN status = 'in_transit' THEN 1 END) as transit_count,
        COUNT(CASE WHEN status = 'out_for_delivery' THEN 1 END) as out_delivery_count,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_count,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
        COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned_count,
        SUM(CASE WHEN status = 'delivered' THEN shipping_fee END) as total_revenue,
        COUNT(*) as total_parcels
    FROM parcels WHERE courier_id = ?";
    
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bind_param("i", $courier_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    
    // جلب إحصائيات اليوم
    $today_stats_query = "SELECT 
        COUNT(CASE WHEN status = 'delivered' AND DATE(updated_at) = CURDATE() THEN 1 END) as today_delivered,
        SUM(CASE WHEN status = 'delivered' AND DATE(updated_at) = CURDATE() THEN shipping_fee END) as today_revenue
    FROM parcels WHERE courier_id = ?";
    
    $today_stmt = $conn->prepare($today_stats_query);
    $today_stmt->bind_param("i", $courier_id);
    $today_stmt->execute();
    $today_stats = $today_stmt->get_result()->fetch_assoc();
    
    // جلب إحصائيات الأسبوع
    $week_stats_query = "SELECT 
        COUNT(CASE WHEN status = 'delivered' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_delivered,
        SUM(CASE WHEN status = 'delivered' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN shipping_fee END) as week_revenue
    FROM parcels WHERE courier_id = ?";
    
    $week_stmt = $conn->prepare($week_stats_query);
    $week_stmt->bind_param("i", $courier_id);
    $week_stmt->execute();
    $week_stats = $week_stmt->get_result()->fetch_assoc();
    
    // دمج الإحصائيات
    $all_stats = array_merge($stats, $today_stats, $week_stats);
    
    // إضافة نسب مئوية
    $all_stats['delivery_rate'] = $all_stats['total_parcels'] > 0 ? 
        round(($all_stats['delivered_count'] / $all_stats['total_parcels']) * 100, 1) : 0;
    
    $all_stats['success_rate'] = $all_stats['total_parcels'] > 0 ? 
        round((($all_stats['delivered_count'] + $all_stats['returned_count']) / $all_stats['total_parcels']) * 100, 1) : 0;
    
    echo json_encode(['success' => true, 'stats' => $all_stats]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
?>
