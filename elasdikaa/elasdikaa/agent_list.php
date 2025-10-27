<?php
// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// تطبيق الفلاتر
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$commission_type_filter = $_GET['commission_type'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'id';

// بناء الاستعلام مع الفلاتر
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if ($status_filter !== '') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 'i';
}

if (!empty($commission_type_filter)) {
    $where_conditions[] = "commission_type = ?";
    $params[] = $commission_type_filter;
    $types .= 's';
}

// ترتيب النتائج
$order_by = "ORDER BY ";
switch ($sort_by) {
    case 'company_name':
        $order_by .= "company_name ASC";
        break;
    case 'status':
        $order_by .= "status DESC, company_name ASC";
        break;
    default:
        $order_by .= "id DESC";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
$agents_query = "SELECT * FROM agents $where_clause $order_by";

if (!empty($params)) {
    $stmt = $conn->prepare($agents_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $agents = $stmt->get_result();
} else {
    $agents = $conn->query($agents_query);
}

// جلب الإحصائيات العامة
function getAgentsStatistics($conn) {
    $stats = [
        'total_agents' => 0,
        'active_agents' => 0,
        'inactive_agents' => 0,
        'total_shipments' => 0,
        'total_revenue' => 0,
        'top_agents' => []
    ];
    
    // إحصائيات الوكلاء
    $agents_stats = $conn->query("SELECT 
        COUNT(*) as total_agents,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_agents,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_agents
        FROM agents")->fetch_assoc();
    
    $stats = array_merge($stats, $agents_stats);
    
    // إحصائيات الشحنات والإيرادات
    $shipments_stats = $conn->query("SELECT 
        COUNT(*) as total_shipments,
        SUM(cod_amount) as total_revenue
        FROM parcels")->fetch_assoc();
    
    $stats['total_shipments'] = $shipments_stats['total_shipments'] ?? 0;
    $stats['total_revenue'] = $shipments_stats['total_revenue'] ?? 0;
    
    // أفضل 3 وكلاء حسب عدد الشحنات
    $top_agents_query = "SELECT a.company_name, COUNT(p.id) as shipment_count, SUM(p.cod_amount) as total_revenue
                         FROM agents a 
                         LEFT JOIN parcels p ON a.id = p.agent_id 
                         WHERE a.status = 1
                         GROUP BY a.id, a.company_name 
                         ORDER BY shipment_count DESC, total_revenue DESC 
                         LIMIT 3";
    
    $top_agents_result = $conn->query($top_agents_query);
    while ($agent = $top_agents_result->fetch_assoc()) {
        $stats['top_agents'][] = $agent;
    }
    
    return $stats;
}

// دالة لتغيير حالة الوكيل
function getStatusBadge($status) {
    if ($status == 1) {
        return '<span class="badge bg-success">نشط</span>';
    } else {
        return '<span class="badge bg-secondary">موقوف</span>';
    }
}

// دالة لجلب إحصائيات سريعة للوكيل
function getAgentQuickStats($conn, $agent_id) {
    $stats = $conn->query("SELECT 
        COUNT(*) as total_shipments,
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered_shipments,
        SUM(cod_amount) as total_cod,
        SUM(CASE WHEN status = 4 AND payment_status IN ('unpaid', 'partially_paid') THEN cod_amount ELSE 0 END) as unpaid_amount
        FROM parcels WHERE agent_id = $agent_id")->fetch_assoc();
    
    return $stats;
}

$statistics = getAgentsStatistics($conn);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قائمة الوكلاء</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; direction: rtl; background-color: #f4f7f6; }
        .card { border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: none; }
        .card-header { background-color: #17a2b8; color: white; border-radius: 1rem 1rem 0 0; padding: 1.5rem; text-align: center; }
        .table thead th { background-color: #17a2b8; color: #fff; border-color: #17a2b8; }
        .table tbody tr:hover { background-color: #e9ecef; }
        .action-buttons .btn { margin-left: 5px; }
        .filter-container { background-color: #e9ecef; border-radius: 1rem; padding: 20px; margin-bottom: 25px; }
        .status-badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 50rem; font-weight: bold; }
        .status-active { background-color: #28a745; color: white; }
        .status-inactive { background-color: #dc3545; color: white; }
        
        /* بطاقات الإحصائيات */
        .stats-card { 
            background-color: #17a2b8; 
            color: white !important; 
            border-radius: 1rem; 
            padding: 1.5rem; 
            margin-bottom: 1rem; 
            text-align: center; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
        }
        .stats-card h3 { 
            font-size: 2rem; 
            font-weight: bold; 
            margin-bottom: 0.5rem; 
            color: white !important;
        }
        .stats-card p { 
            margin-bottom: 0; 
            opacity: 1; 
            color: white !important;
            font-weight: 500;
        }
        
        /* بطاقة أفضل الوكلاء */
        .top-agents-card {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        /* تحسين عرض الجدول */
        .table td {
            vertical-align: middle;
            padding: 0.75rem 0.5rem;
            text-align: center;
        }
        
        .table th {
            font-weight: bold;
            font-size: 0.9rem;
            text-align: center;
        }
        
        /* تحسين الأزرار */
        .d-flex.gap-1 .btn {
            margin: 2px;
            min-width: 32px;
        }
        
        /* تحسين النصوص */
        .fw-bold {
            font-size: 0.95rem;
        }
        
        .text-muted {
            font-size: 0.75rem;
        }
        
        /* تحسين عرض الخلايا */
        .table td:nth-child(2) {
            text-align: right;
        }
        
        .table td:nth-child(3),
        .table td:nth-child(4),
        .table td:nth-child(5) {
            text-align: center;
        }
        
        /* تحسين responsive */
        @media (max-width: 992px) {
            .table th, .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.8rem;
            }
            .btn-sm {
                padding: 0.2rem 0.4rem;
                font-size: 0.7rem;
            }
        }
        
        @media print {
            .no-print { display: none !important; }
            .table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .stats-card { background-color: #17a2b8 !important; }
        }
        
        @media (max-width: 768px) {
            .stats-card h3 { font-size: 1.5rem; }
            .action-buttons .btn { margin: 1px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="container-fluid py-4" style="background-color: #f4f7f6; min-height: 100vh;">
    
    <!-- بطاقات الإحصائيات -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <h3><?php echo number_format($statistics['total_agents'] ?? 0); ?></h3>
                <p><i class="fas fa-users me-2"></i>إجمالي الوكلاء</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h3><?php echo number_format($statistics['active_agents'] ?? 0); ?></h3>
                <p><i class="fas fa-user-check me-2"></i>وكلاء نشطين</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h3><?php echo number_format($statistics['total_shipments'] ?? 0); ?></h3>
                <p><i class="fas fa-shipping-fast me-2"></i>إجمالي الشحنات</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h3><?php echo number_format($statistics['total_revenue'] ?? 0, 2); ?> ج.م</h3>
                <p><i class="fas fa-chart-line me-2"></i>إجمالي الإيرادات</p>
            </div>
        </div>
    </div>

    <!-- أفضل الوكلاء -->
    <?php if (!empty($statistics['top_agents'])): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card top-agents-card">
                <div class="card-header" style="background-color: transparent; border: none;">
                    <h5 class="mb-0 text-white"><i class="fas fa-trophy me-2"></i>أفضل الوكلاء (حسب عدد الشحنات)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($statistics['top_agents'] as $index => $top_agent): ?>
                        <div class="col-md-4">
                            <div class="text-center text-white">
                                <h4><i class="fas fa-medal me-2"></i><?php echo $index + 1; ?></h4>
                                <h6><?php echo htmlspecialchars($top_agent['company_name']); ?></h6>
                                <p class="mb-0">
                                    <small><?php echo number_format($top_agent['shipment_count'] ?? 0); ?> شحنة</small><br>
                                    <small><?php echo number_format($top_agent['total_revenue'] ?? 0, 2); ?> ج.م</small>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0 text-center"><i class="fas fa-users me-2"></i> قائمة الوكلاء</h4>
        </div>
        <div class="card-body">
            
            <!-- فلاتر البحث -->
            <div class="filter-container">
                <form id="filterForm" class="row g-3" method="GET">
                    <input type="hidden" name="page" value="agent_list">
                    
                    <div class="col-md-3">
                        <label for="search_input" class="form-label">بحث سريع</label>
                        <input type="text" class="form-control" id="search_input" name="search" placeholder="اسم الشركة أو المسؤول" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="status_filter" class="form-label">الحالة</label>
                        <select class="form-select" id="status_filter" name="status">
                            <option value="">كل الحالات</option>
                            <option value="1" <?php echo (isset($_GET['status']) && $_GET['status'] == '1') ? 'selected' : ''; ?>>نشط</option>
                            <option value="0" <?php echo (isset($_GET['status']) && $_GET['status'] == '0') ? 'selected' : ''; ?>>غير نشط</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="commission_filter" class="form-label">نوع العمولة</label>
                        <select class="form-select" id="commission_filter" name="commission_type">
                            <option value="">كل الأنواع</option>
                            <option value="percent" <?php echo (isset($_GET['commission_type']) && $_GET['commission_type'] == 'percent') ? 'selected' : ''; ?>>نسبة مئوية</option>
                            <option value="fixed" <?php echo (isset($_GET['commission_type']) && $_GET['commission_type'] == 'fixed') ? 'selected' : ''; ?>>مبلغ ثابت</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="sort_filter" class="form-label">ترتيب حسب</label>
                        <select class="form-select" id="sort_filter" name="sort_by">
                            <option value="id" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'id') ? 'selected' : ''; ?>>الأحدث</option>
                            <option value="company_name" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'company_name') ? 'selected' : ''; ?>>اسم الشركة</option>
                            <option value="status" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'status') ? 'selected' : ''; ?>>الحالة</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-1"><i class="fas fa-filter me-1"></i>بحث</button>
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()"><i class="fas fa-times me-1"></i>مسح</button>
                    </div>
                </form>
            </div>

            <div class="d-flex justify-content-start mb-3">
                <a href="index.php?page=new_agent" class="btn btn-primary"><i class="fas fa-plus me-2"></i> إضافة وكيل جديد</a>
            </div>
            <?php if ($agents->num_rows > 0): ?>
            <div class="table-responsive" id="agentsTableContainer">
                <table class="table table-hover table-bordered text-center" id="agentsTable">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">الشركة</th>
                            <th width="15%">المسؤول</th>
                            <th width="12%">رقم الجوال</th>
                            <th width="15%">البريد الإلكتروني</th>
                            <th width="10%">العمولة</th>
                            <th width="8%">الحالة</th>
                            <th width="10%">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $agents->fetch_assoc()): 
                            $quick_stats = getAgentQuickStats($conn, $row['id']);
                        ?>
                        <tr id="agent-row-<?php echo $row['id']; ?>">
                            <td><?php echo $i++; ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($row['company_name']); ?></div>
                                <small class="text-muted"><?php echo number_format($quick_stats['total_shipments'] ?? 0); ?> شحنة</small>
                            </td>
                            <td><?php echo htmlspecialchars($row['contact_person']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><small><?php echo htmlspecialchars($row['email']); ?></small></td>
                            <td>
                                <?php 
                                if($row['commission_type'] == 'percent') {
                                    echo '<span class="badge bg-info">' . htmlspecialchars($row['commission_value']) . ' %</span>';
                                } elseif($row['commission_type'] == 'fixed') {
                                    echo '<span class="badge bg-success">' . htmlspecialchars($row['commission_value']) . ' ج.م</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">-</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if($row['status'] == 1): ?>
                                    <span class="badge status-badge status-active"><i class="fas fa-check-circle me-1"></i>نشط</span>
                                <?php else: ?>
                                    <span class="badge status-badge status-inactive"><i class="fas fa-pause-circle me-1"></i>غير نشط</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1 justify-content-center">
                                    <a href="index.php?page=agent_profile&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="عرض الملف الشخصي">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="index.php?page=agent_shipments&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" title="شحنات الوكيل">
                                        <i class="fas fa-shipping-fast"></i>
                                    </a>
                                    <a href="index.php?page=agent_finance&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" title="الحسابات المالية">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </a>
                                    <a href="index.php?page=new_agent&edit=1&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info text-center">لا يوجد وكلاء مسجلون حاليًا.</div>
            <?php endif; ?>
        </div>
    </div>
</div>



<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// تم حذف كود Modal التعديل لأنه تم الانتقال لصفحة منفصلة

// كود jQuery لتغيير حالة الوكيل
$(document).on('click', '.toggle-status-btn', function() {
    var agentId = $(this).data('id');
    var currentStatus = $(this).data('status');
    var newStatus = (currentStatus == 1) ? 0 : 1;

    if (confirm("هل أنت متأكد من تغيير حالة هذا الوكيل؟")) {
        $.ajax({
            url: 'toggle_agent_status.php',
            type: 'POST',
            data: { id: agentId, status: newStatus },
            success: function(response) {
                alert(response);
                location.reload();
            },
            error: function(xhr, status, error) {
                alert("حدث خطأ: " + error);
            }
        });
    }
});

// Function to clear filters
function clearFilters() {
    window.location.href = window.location.pathname + '?page=agent_list';
}

// تم إزالة دوال الطباعة والتصدير

// كود jQuery لحذف الوكيل
$(document).on('click', '.delete-agent-btn', function() {
    var agentId = $(this).data('id');

    if (confirm("تحذير! هل أنت متأكد من حذف هذا الوكيل نهائيًا؟ سيتم حذف جميع بياناته المرتبطة.")) {
        $.ajax({
            url: 'delete_agent.php',
            type: 'POST',
            data: { id: agentId },
            success: function(response) {
                alert(response);
                location.reload();
            },
            error: function(xhr, status, error) {
                alert("حدث خطأ: " + error);
            }
        });
    }
});
</script>

</body>
</html>