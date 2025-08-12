<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['agent_logged_in']) || !$_SESSION['agent_logged_in']) {
    header('Location: agent_login.php');
    exit;
}

include 'db_connect.php';

$agent_id = $_SESSION['agent_id'];
$agent_company = $_SESSION['agent_company'];

// معالجة تحديث حالة الشحنة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $shipment_id = $_POST['shipment_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? 0;
    
    // التحقق من أن الشحنة تخص هذا الوكيل
    $check_stmt = $conn->prepare("SELECT id FROM parcels WHERE id = ? AND agent_id = ?");
    $check_stmt->bind_param('ii', $shipment_id, $agent_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $update_stmt = $conn->prepare("UPDATE parcels SET status = ? WHERE id = ? AND agent_id = ?");
        $update_stmt->bind_param('iii', $new_status, $shipment_id, $agent_id);
        if ($update_stmt->execute()) {
            $success_message = "تم تحديث حالة الشحنة بنجاح!";
        }
    }
}

// جلب إحصائيات الوكيل المحسنة
function getAgentDashboardStats($conn, $agent_id) {
    $stats = [];
    
    // إحصائيات عامة محسنة
    $general_query = "SELECT 
        COUNT(*) as total_shipments,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as with_courier,
        SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as in_delivery,
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status IN (8,9,10,11,12) THEN 1 ELSE 0 END) as returned,
        SUM(cod_amount) as total_cod,
        SUM(CASE WHEN status = 4 AND payment_status = 'unpaid' THEN cod_amount ELSE 0 END) as unpaid_amount,
        AVG(CASE WHEN status = 4 THEN DATEDIFF(date_created, NOW()) ELSE NULL END) as avg_delivery_time,
        SUM(CASE WHEN status = 4 THEN shipping_fees ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 4 THEN net_profit ELSE 0 END) as total_profit
        FROM parcels WHERE agent_id = ?";
    
    $stmt = $conn->prepare($general_query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // حساب معدل النجاح
    $stats['success_rate'] = $stats['total_shipments'] > 0 ? 
        ($stats['delivered'] / $stats['total_shipments']) * 100 : 0;
    
    // إحصائيات شهرية للرسم البياني
    $monthly_query = "SELECT 
        DATE_FORMAT(date_created, '%Y-%m') as month,
        COUNT(*) as shipments,
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered,
        SUM(cod_amount) as revenue
        FROM parcels 
        WHERE agent_id = ? 
        AND date_created >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_created, '%Y-%m')
        ORDER BY month DESC";
    
    $monthly_stmt = $conn->prepare($monthly_query);
    $monthly_stmt->bind_param('i', $agent_id);
    $monthly_stmt->execute();
    $stats['monthly_data'] = $monthly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return $stats;
}

// جلب أحدث الشحنات مع تحسينات
function getRecentShipments($conn, $agent_id, $limit = 10) {
    $query = "SELECT p.*, ar.name as area_name, c.name as courier_name
              FROM parcels p
              LEFT JOIN areas ar ON p.recipient_area_id = ar.id
              LEFT JOIN couriers c ON p.courier_id = c.id
              WHERE p.agent_id = ?
              ORDER BY p.date_created DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $agent_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// جلب إحصائيات المناطق
function getAreaStats($conn, $agent_id) {
    $query = "SELECT ar.name as area_name, 
              COUNT(*) as shipments,
              SUM(CASE WHEN p.status = 4 THEN 1 ELSE 0 END) as delivered,
              SUM(p.cod_amount) as revenue
              FROM parcels p
              LEFT JOIN areas ar ON p.recipient_area_id = ar.id
              WHERE p.agent_id = ?
              GROUP BY ar.id, ar.name
              ORDER BY shipments DESC
              LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    return $stmt->get_result();
}

// دالة حالة الشحنة المحسنة
function getStatusInfo($status) {
    $statuses = [
        1 => ['name' => 'قيد التنفيذ', 'class' => 'bg-secondary', 'icon' => 'fas fa-clock', 'can_update' => true],
        2 => ['name' => 'تم تسليمها للمندوب', 'class' => 'bg-warning', 'icon' => 'fas fa-user-tie', 'can_update' => true],
        3 => ['name' => 'جاري التوصيل', 'class' => 'bg-info', 'icon' => 'fas fa-truck', 'can_update' => true],
        4 => ['name' => 'تم التسليم بنجاح', 'class' => 'bg-success', 'icon' => 'fas fa-check-circle', 'can_update' => false],
        5 => ['name' => 'تم الدفع بنجاح', 'class' => 'bg-primary', 'icon' => 'fas fa-money-bill-wave', 'can_update' => false],
        8 => ['name' => 'تم الرفض', 'class' => 'bg-danger', 'icon' => 'fas fa-times-circle', 'can_update' => true],
        9 => ['name' => 'مرتجعات', 'class' => 'bg-warning', 'icon' => 'fas fa-undo', 'can_update' => true]
    ];
    
    return $statuses[$status] ?? ['name' => 'غير محدد', 'class' => 'bg-secondary', 'icon' => 'fas fa-question', 'can_update' => false];
}

$stats = getAgentDashboardStats($conn, $agent_id);
$recent_shipments = getRecentShipments($conn, $agent_id);
$area_stats = getAreaStats($conn, $agent_id);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الوكيل - <?php echo htmlspecialchars($agent_company); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            font-family: 'Tajawal', Arial, sans-serif; 
            direction: rtl; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .navbar { 
            background: linear-gradient(135deg, #17a2b8, #20c997);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            margin: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .stats-card { 
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; 
            border-radius: 20px; 
            padding: 2rem; 
            margin-bottom: 1.5rem; 
            text-align: center; 
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), transparent);
            z-index: 1;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        
        .stats-card h3 { 
            font-size: 2.5rem; 
            font-weight: bold; 
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .stats-card p { 
            position: relative;
            z-index: 2;
            font-size: 1.1rem;
        }
        
        .card { 
            border-radius: 20px; 
            box-shadow: 0 8px 30px rgba(0,0,0,0.1); 
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
        }
        
        .card-header { 
            background: linear-gradient(135deg, #17a2b8, #20c997);
            color: white; 
            border-radius: 20px 20px 0 0; 
            padding: 1.5rem;
            font-weight: bold;
        }
        
        .table thead th { 
            background: linear-gradient(135deg, #17a2b8, #20c997);
            color: #fff;
            border: none;
            padding: 1rem;
            font-weight: bold;
        }
        
        .table tbody tr {
            transition: background-color 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(23, 162, 184, 0.1);
        }
        
        .status-badge { 
            padding: 0.5rem 1rem; 
            border-radius: 50rem; 
            font-size: 0.9rem; 
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-update { 
            font-size: 0.8rem; 
            padding: 0.4rem 0.8rem;
            border-radius: 50rem;
            transition: all 0.3s ease;
        }
        
        .btn-update:hover {
            transform: scale(1.05);
        }
        
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .area-stats {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            color: white;
        }
        
        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                padding: 20px;
            }
            
            .stats-card {
                padding: 1.5rem;
            }
            
            .stats-card h3 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar محسن -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="#">
            <i class="fas fa-shipping-fast me-2"></i><?php echo htmlspecialchars($agent_company); ?>
        </a>
        <div class="navbar-nav ms-auto">
            <span class="nav-link text-white">
                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['agent_username']); ?>
            </span>
            <a class="nav-link text-white" href="agent_logout.php">
                <i class="fas fa-sign-out-alt me-1"></i>تسجيل خروج
            </a>
        </div>
    </div>
</nav>

<div class="main-container">
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- إجراءات سريعة -->
    <div class="quick-actions">
        <a href="#" class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addShipmentModal">
            <i class="fas fa-plus-circle"></i>
            <div>إضافة شحنة جديدة</div>
        </a>
        <a href="agent_search.php" class="quick-action-btn">
            <i class="fas fa-search"></i>
            <div>البحث المتقدم</div>
        </a>
        <a href="agent_reports.php" class="quick-action-btn">
            <i class="fas fa-chart-line"></i>
            <div>التقارير الشاملة</div>
        </a>
        <a href="#" class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#settingsModal">
            <i class="fas fa-cog"></i>
            <div>الإعدادات</div>
        </a>
    </div>

    <!-- البطاقات الإحصائية المحسنة -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?php echo number_format($stats['total_shipments']); ?></h3>
                <p><i class="fas fa-boxes me-2"></i>إجمالي الشحنات</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?php echo number_format($stats['delivered']); ?></h3>
                <p><i class="fas fa-check-circle me-2"></i>شحنات مسلمة</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?php echo number_format($stats['success_rate'], 1); ?>%</h3>
                <p><i class="fas fa-chart-line me-2"></i>معدل النجاح</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?php echo number_format($stats['total_revenue'], 2); ?> ج.م</h3>
                <p><i class="fas fa-money-bill-wave me-2"></i>إجمالي الإيرادات</p>
            </div>
        </div>
    </div>

    <!-- الرسوم البيانية -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="chart-container">
                <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>توزيع حالات الشحنات</h5>
                <canvas id="statusChart" width="400" height="200"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-container">
                <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>الأداء الشهري</h5>
                <canvas id="monthlyChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- إحصائيات المناطق -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>أفضل المناطق أداءً</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $top_areas = [];
                        while ($area = $area_stats->fetch_assoc()) {
                            $top_areas[] = $area;
                        }
                        $top_3_areas = array_slice($top_areas, 0, 3);
                        ?>
                        <?php foreach ($top_3_areas as $area): ?>
                        <div class="col-md-4">
                            <div class="area-stats">
                                <h6><?php echo htmlspecialchars($area['area_name']); ?></h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo $area['shipments']; ?></div>
                                        <small>شحنة</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo $area['delivered']; ?></div>
                                        <small>مسلمة</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo number_format($area['revenue'], 0); ?></div>
                                        <small>ج.م</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- تفصيل الحالات المحسن -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>تفصيل حالات الشحنات</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-secondary"><?php echo $stats['pending']; ?></h4>
                                <small><i class="fas fa-clock me-1"></i>قيد التنفيذ</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-warning"><?php echo $stats['with_courier']; ?></h4>
                                <small><i class="fas fa-user-tie me-1"></i>مع المندوب</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-info"><?php echo $stats['in_delivery']; ?></h4>
                                <small><i class="fas fa-truck me-1"></i>جاري التوصيل</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-success"><?php echo $stats['delivered']; ?></h4>
                                <small><i class="fas fa-check-circle me-1"></i>مسلمة</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-primary"><?php echo $stats['paid']; ?></h4>
                                <small><i class="fas fa-money-bill-wave me-1"></i>مدفوعة</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-danger"><?php echo $stats['returned']; ?></h4>
                                <small><i class="fas fa-undo me-1"></i>مرتجعات</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- أحدث الشحنات المحسنة -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>أحدث الشحنات</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>رقم الشحنة</th>
                                    <th>المستلم</th>
                                    <th>المنطقة</th>
                                    <th>المندوب</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_shipments->num_rows > 0): ?>
                                    <?php while ($shipment = $recent_shipments->fetch_assoc()): ?>
                                        <?php $status_info = getStatusInfo($shipment['status']); ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo htmlspecialchars($shipment['reference_number'] ?? $shipment['id']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($shipment['recipient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($shipment['area_name'] ?? 'غير محدد'); ?></td>
                                            <td><?php echo htmlspecialchars($shipment['courier_name'] ?? 'غير محدد'); ?></td>
                                            <td><?php echo number_format($shipment['cod_amount'], 2); ?> ج.م</td>
                                            <td>
                                                <span class="status-badge <?php echo $status_info['class']; ?>">
                                                    <i class="<?php echo $status_info['icon']; ?>"></i>
                                                    <?php echo $status_info['name']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($shipment['date_created'])); ?></td>
                                            <td>
                                                <?php if ($status_info['can_update']): ?>
                                                    <button class="btn btn-sm btn-primary btn-update" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#updateStatusModal"
                                                            data-shipment-id="<?php echo $shipment['id']; ?>"
                                                            data-current-status="<?php echo $shipment['status']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">مكتملة</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">لا توجد شحنات</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal تحديث الحالة المحسن -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>تحديث حالة الشحنة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="shipment_id" name="shipment_id">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">الحالة الجديدة</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="1"><i class="fas fa-clock"></i> قيد التنفيذ</option>
                            <option value="2"><i class="fas fa-user-tie"></i> تم تسليمها للمندوب</option>
                            <option value="3"><i class="fas fa-truck"></i> جاري التوصيل</option>
                            <option value="4"><i class="fas fa-check-circle"></i> تم التسليم بنجاح</option>
                            <option value="8"><i class="fas fa-times-circle"></i> تم الرفض</option>
                            <option value="9"><i class="fas fa-undo"></i> مرتجعات</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>تحديث
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // معالجة modal تحديث الحالة
    const updateButtons = document.querySelectorAll('.btn-update');
    const shipmentIdField = document.getElementById('shipment_id');
    const newStatusField = document.getElementById('new_status');
    
    updateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const shipmentId = this.getAttribute('data-shipment-id');
            const currentStatus = this.getAttribute('data-current-status');
            
            shipmentIdField.value = shipmentId;
            newStatusField.value = currentStatus;
        });
    });

    // رسم بياني دائري لحالات الشحنات
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['قيد التنفيذ', 'مع المندوب', 'جاري التوصيل', 'مسلمة', 'مدفوعة', 'مرتجعات'],
            datasets: [{
                data: [
                    <?php echo $stats['pending']; ?>,
                    <?php echo $stats['with_courier']; ?>,
                    <?php echo $stats['in_delivery']; ?>,
                    <?php echo $stats['delivered']; ?>,
                    <?php echo $stats['paid']; ?>,
                    <?php echo $stats['returned']; ?>
                ],
                backgroundColor: [
                    '#6c757d',
                    '#ffc107',
                    '#17a2b8',
                    '#28a745',
                    '#007bff',
                    '#dc3545'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // رسم بياني خطي للأداء الشهري
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column(array_reverse($stats['monthly_data']), 'month')); ?>,
            datasets: [{
                label: 'عدد الشحنات',
                data: <?php echo json_encode(array_column(array_reverse($stats['monthly_data']), 'shipments')); ?>,
                borderColor: '#17a2b8',
                backgroundColor: 'rgba(23, 162, 184, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});

// دالة تصدير التقارير
function exportReport() {
    alert('سيتم إضافة ميزة تصدير التقارير قريباً!');
}
</script>

</body>
</html>
