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

// معالجة طلبات التقرير
$report_type = $_GET['type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // أول يوم من الشهر الحالي
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // اليوم الحالي

// دالة جلب إحصائيات عامة
function getOverviewStats($conn, $agent_id, $date_from, $date_to) {
    $query = "SELECT 
        COUNT(*) as total_shipments,
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status IN (8,9,10,11,12) THEN 1 ELSE 0 END) as returned,
        SUM(cod_amount) as total_amount,
        SUM(CASE WHEN status = 4 THEN cod_amount ELSE 0 END) as delivered_amount,
        SUM(CASE WHEN status = 5 THEN cod_amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN status = 4 THEN shipping_fees ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 4 THEN net_profit ELSE 0 END) as total_profit,
        AVG(CASE WHEN status = 4 THEN DATEDIFF(date_created, NOW()) ELSE NULL END) as avg_delivery_time
        FROM parcels 
        WHERE agent_id = ? 
        AND DATE(date_created) BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $agent_id, $date_from, $date_to);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// دالة جلب إحصائيات المناطق
function getAreaStats($conn, $agent_id, $date_from, $date_to) {
    $query = "SELECT 
        ar.name as area_name,
        COUNT(*) as shipments,
        SUM(CASE WHEN p.status = 4 THEN 1 ELSE 0 END) as delivered,
        SUM(p.cod_amount) as total_amount,
        SUM(CASE WHEN p.status = 4 THEN p.cod_amount ELSE 0 END) as delivered_amount,
        SUM(CASE WHEN p.status = 4 THEN p.shipping_fees ELSE 0 END) as revenue
        FROM parcels p
        LEFT JOIN areas ar ON p.recipient_area_id = ar.id
        WHERE p.agent_id = ? 
        AND DATE(p.date_created) BETWEEN ? AND ?
        GROUP BY ar.id, ar.name
        ORDER BY shipments DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $agent_id, $date_from, $date_to);
    $stmt->execute();
    return $stmt->get_result();
}

// دالة جلب إحصائيات المندوبين
function getCourierStats($conn, $agent_id, $date_from, $date_to) {
    $query = "SELECT 
        c.name as courier_name,
        COUNT(*) as shipments,
        SUM(CASE WHEN p.status = 4 THEN 1 ELSE 0 END) as delivered,
        SUM(p.cod_amount) as total_amount,
        SUM(CASE WHEN p.status = 4 THEN p.cod_amount ELSE 0 END) as delivered_amount
        FROM parcels p
        LEFT JOIN couriers c ON p.courier_id = c.id
        WHERE p.agent_id = ? 
        AND DATE(p.date_created) BETWEEN ? AND ?
        GROUP BY c.id, c.name
        ORDER BY shipments DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $agent_id, $date_from, $date_to);
    $stmt->execute();
    return $stmt->get_result();
}

// دالة جلب إحصائيات يومية
function getDailyStats($conn, $agent_id, $date_from, $date_to) {
    $query = "SELECT 
        DATE(date_created) as date,
        COUNT(*) as shipments,
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered,
        SUM(cod_amount) as amount,
        SUM(CASE WHEN status = 4 THEN cod_amount ELSE 0 END) as delivered_amount
        FROM parcels 
        WHERE agent_id = ? 
        AND DATE(date_created) BETWEEN ? AND ?
        GROUP BY DATE(date_created)
        ORDER BY date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $agent_id, $date_from, $date_to);
    $stmt->execute();
    return $stmt->get_result();
}

// دالة جلب إحصائيات الحالات
function getStatusStats($conn, $agent_id, $date_from, $date_to) {
    $query = "SELECT 
        status,
        COUNT(*) as count,
        SUM(cod_amount) as amount
        FROM parcels 
        WHERE agent_id = ? 
        AND DATE(date_created) BETWEEN ? AND ?
        GROUP BY status
        ORDER BY count DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $agent_id, $date_from, $date_to);
    $stmt->execute();
    return $stmt->get_result();
}

// جلب البيانات حسب نوع التقرير
$overview_stats = getOverviewStats($conn, $agent_id, $date_from, $date_to);
$area_stats = getAreaStats($conn, $agent_id, $date_from, $date_to);
$courier_stats = getCourierStats($conn, $agent_id, $date_from, $date_to);
$daily_stats = getDailyStats($conn, $agent_id, $date_from, $date_to);
$status_stats = getStatusStats($conn, $agent_id, $date_from, $date_to);

// دالة حالة الشحنة
function getStatusInfo($status) {
    $statuses = [
        1 => ['name' => 'قيد التنفيذ', 'class' => 'bg-secondary', 'icon' => 'fas fa-clock'],
        2 => ['name' => 'مع المندوب', 'class' => 'bg-warning', 'icon' => 'fas fa-user-tie'],
        3 => ['name' => 'جاري التوصيل', 'class' => 'bg-info', 'icon' => 'fas fa-truck'],
        4 => ['name' => 'تم التسليم', 'class' => 'bg-success', 'icon' => 'fas fa-check-circle'],
        5 => ['name' => 'تم الدفع', 'class' => 'bg-primary', 'icon' => 'fas fa-money-bill-wave'],
        8 => ['name' => 'تم الرفض', 'class' => 'bg-danger', 'icon' => 'fas fa-times-circle'],
        9 => ['name' => 'مرتجعات', 'class' => 'bg-warning', 'icon' => 'fas fa-undo']
    ];
    
    return $statuses[$status] ?? ['name' => 'غير محدد', 'class' => 'bg-secondary', 'icon' => 'fas fa-question'];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير الشاملة - <?php echo htmlspecialchars($agent_company); ?></title>
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
        
        .report-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
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
        
        .table {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #17a2b8, #20c997);
            color: white;
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
        
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .btn-export {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            margin-right: 0.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #17a2b8, #20c997);
            color: white;
        }
        
        .progress-custom {
            height: 8px;
            border-radius: 10px;
            background: rgba(255,255,255,0.3);
        }
        
        .progress-custom .progress-bar {
            border-radius: 10px;
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
            
            .export-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="agent_dashboard.php">
            <i class="fas fa-arrow-left me-2"></i><?php echo htmlspecialchars($agent_company); ?>
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
    
    <!-- عنوان الصفحة -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line me-2"></i>التقارير الشاملة</h2>
        <a href="agent_dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i>العودة للوحة التحكم
        </a>
    </div>

    <!-- رأس التقرير -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3><i class="fas fa-chart-bar me-2"></i>تقرير الأداء الشامل</h3>
                <p class="mb-0">من <?php echo date('Y-m-d', strtotime($date_from)); ?> إلى <?php echo date('Y-m-d', strtotime($date_to)); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                    <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo $date_from; ?>">
                    <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo $date_to; ?>">
                    <button type="submit" class="btn btn-light btn-sm">
                        <i class="fas fa-filter"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- أزرار التصدير -->
    <div class="export-buttons">
        <button class="btn btn-success btn-export" onclick="exportToExcel()">
            <i class="fas fa-file-excel me-1"></i>تصدير Excel
        </button>
        <button class="btn btn-danger btn-export" onclick="exportToPDF()">
            <i class="fas fa-file-pdf me-1"></i>تصدير PDF
        </button>
        <button class="btn btn-info btn-export" onclick="printReport()">
            <i class="fas fa-print me-1"></i>طباعة التقرير
        </button>
        <button class="btn btn-warning btn-export" onclick="shareReport()">
            <i class="fas fa-share me-1"></i>مشاركة التقرير
        </button>
    </div>

    <!-- البطاقات الإحصائية -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?php echo number_format($overview_stats['total_shipments']); ?></h3>
                <p><i class="fas fa-boxes me-2"></i>إجمالي الشحنات</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?php echo number_format($overview_stats['delivered']); ?></h3>
                <p><i class="fas fa-check-circle me-2"></i>شحنات مسلمة</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?php echo number_format($overview_stats['total_amount'], 2); ?> ج.م</h3>
                <p><i class="fas fa-money-bill-wave me-2"></i>إجمالي المبالغ</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?php echo number_format($overview_stats['total_profit'], 2); ?> ج.م</h3>
                <p><i class="fas fa-chart-line me-2"></i>إجمالي الأرباح</p>
            </div>
        </div>
    </div>

    <!-- تبويبات التقارير -->
    <ul class="nav nav-tabs" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                <i class="fas fa-chart-pie me-1"></i>نظرة عامة
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="areas-tab" data-bs-toggle="tab" data-bs-target="#areas" type="button" role="tab">
                <i class="fas fa-map-marker-alt me-1"></i>المناطق
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="couriers-tab" data-bs-toggle="tab" data-bs-target="#couriers" type="button" role="tab">
                <i class="fas fa-user-tie me-1"></i>المندوبين
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab">
                <i class="fas fa-calendar-alt me-1"></i>الأداء اليومي
            </button>
        </li>
    </ul>

    <div class="tab-content" id="reportTabsContent">
        <!-- تبويب النظرة العامة -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <div class="row mt-4">
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>توزيع حالات الشحنات</h5>
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>الأداء المالي</h5>
                        <canvas id="financialChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- إحصائيات مفصلة -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>إحصائيات مفصلة</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <div class="border rounded p-3 bg-light">
                                        <h4 class="text-success"><?php echo number_format($overview_stats['delivered']); ?></h4>
                                        <small>شحنات مسلمة</small>
                                        <div class="progress-custom mt-2">
                                            <div class="progress-bar bg-success" style="width: <?php echo $overview_stats['total_shipments'] > 0 ? ($overview_stats['delivered'] / $overview_stats['total_shipments']) * 100 : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="border rounded p-3 bg-light">
                                        <h4 class="text-primary"><?php echo number_format($overview_stats['paid']); ?></h4>
                                        <small>شحنات مدفوعة</small>
                                        <div class="progress-custom mt-2">
                                            <div class="progress-bar bg-primary" style="width: <?php echo $overview_stats['total_shipments'] > 0 ? ($overview_stats['paid'] / $overview_stats['total_shipments']) * 100 : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="border rounded p-3 bg-light">
                                        <h4 class="text-danger"><?php echo number_format($overview_stats['returned']); ?></h4>
                                        <small>مرتجعات</small>
                                        <div class="progress-custom mt-2">
                                            <div class="progress-bar bg-danger" style="width: <?php echo $overview_stats['total_shipments'] > 0 ? ($overview_stats['returned'] / $overview_stats['total_shipments']) * 100 : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="border rounded p-3 bg-light">
                                        <h4 class="text-info"><?php echo number_format($overview_stats['avg_delivery_time'], 1); ?></h4>
                                        <small>متوسط وقت التسليم (أيام)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- تبويب المناطق -->
        <div class="tab-pane fade" id="areas" role="tabpanel">
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>أداء المناطق</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>المنطقة</th>
                                            <th>عدد الشحنات</th>
                                            <th>مسلمة</th>
                                            <th>نسبة النجاح</th>
                                            <th>إجمالي المبالغ</th>
                                            <th>الإيرادات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($area = $area_stats->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($area['area_name']); ?></strong></td>
                                                <td><?php echo number_format($area['shipments']); ?></td>
                                                <td><?php echo number_format($area['delivered']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($area['shipments'] > 0 && ($area['delivered'] / $area['shipments']) >= 0.8) ? 'success' : (($area['delivered'] / $area['shipments']) >= 0.6 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $area['shipments'] > 0 ? number_format(($area['delivered'] / $area['shipments']) * 100, 1) : 0; ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($area['total_amount'], 2); ?> ج.م</td>
                                                <td><?php echo number_format($area['revenue'], 2); ?> ج.م</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- تبويب المندوبين -->
        <div class="tab-pane fade" id="couriers" role="tabpanel">
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>أداء المندوبين</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>المندوب</th>
                                            <th>عدد الشحنات</th>
                                            <th>مسلمة</th>
                                            <th>نسبة النجاح</th>
                                            <th>إجمالي المبالغ</th>
                                            <th>المبالغ المسلمة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($courier = $courier_stats->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($courier['courier_name']); ?></strong></td>
                                                <td><?php echo number_format($courier['shipments']); ?></td>
                                                <td><?php echo number_format($courier['delivered']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($courier['shipments'] > 0 && ($courier['delivered'] / $courier['shipments']) >= 0.8) ? 'success' : (($courier['delivered'] / $courier['shipments']) >= 0.6 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $courier['shipments'] > 0 ? number_format(($courier['delivered'] / $courier['shipments']) * 100, 1) : 0; ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($courier['total_amount'], 2); ?> ج.م</td>
                                                <td><?php echo number_format($courier['delivered_amount'], 2); ?> ج.م</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- تبويب الأداء اليومي -->
        <div class="tab-pane fade" id="daily" role="tabpanel">
            <div class="row mt-4">
                <div class="col-12">
                    <div class="chart-container">
                        <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>الأداء اليومي</h5>
                        <canvas id="dailyChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // رسم بياني دائري لحالات الشحنات
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['مسلمة', 'مدفوعة', 'مرتجعات', 'أخرى'],
            datasets: [{
                data: [
                    <?php echo $overview_stats['delivered']; ?>,
                    <?php echo $overview_stats['paid']; ?>,
                    <?php echo $overview_stats['returned']; ?>,
                    <?php echo $overview_stats['total_shipments'] - $overview_stats['delivered'] - $overview_stats['paid'] - $overview_stats['returned']; ?>
                ],
                backgroundColor: ['#28a745', '#007bff', '#dc3545', '#6c757d']
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

    // رسم بياني خطي للأداء المالي
    const financialCtx = document.getElementById('financialChart').getContext('2d');
    new Chart(financialCtx, {
        type: 'line',
        data: {
            labels: ['إجمالي المبالغ', 'المبالغ المسلمة', 'الإيرادات', 'الأرباح'],
            datasets: [{
                label: 'القيم المالية',
                data: [
                    <?php echo $overview_stats['total_amount']; ?>,
                    <?php echo $overview_stats['delivered_amount']; ?>,
                    <?php echo $overview_stats['total_revenue']; ?>,
                    <?php echo $overview_stats['total_profit']; ?>
                ],
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

    // رسم بياني خطي للأداء اليومي
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?php 
                $daily_labels = [];
                $daily_data = [];
                while ($daily = $daily_stats->fetch_assoc()) {
                    $daily_labels[] = $daily['date'];
                    $daily_data[] = $daily['shipments'];
                }
                echo json_encode(array_reverse($daily_labels));
            ?>,
            datasets: [{
                label: 'عدد الشحنات',
                data: <?php echo json_encode(array_reverse($daily_data)); ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
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

// تصدير إلى Excel
function exportToExcel() {
    alert('سيتم إضافة ميزة التصدير إلى Excel قريباً!');
}

// تصدير إلى PDF
function exportToPDF() {
    alert('سيتم إضافة ميزة التصدير إلى PDF قريباً!');
}

// طباعة التقرير
function printReport() {
    window.print();
}

// مشاركة التقرير
function shareReport() {
    alert('سيتم إضافة ميزة مشاركة التقرير قريباً!');
}
</script>

</body>
</html>












