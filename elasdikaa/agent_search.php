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

// معالجة البحث
$search_results = null;
$total_results = 0;
$search_performed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['search'])) {
    $search_performed = true;
    
    // جمع معايير البحث
    $reference_number = trim($_POST['reference_number'] ?? $_GET['reference_number'] ?? '');
    $recipient_name = trim($_POST['recipient_name'] ?? $_GET['recipient_name'] ?? '');
    $status = $_POST['status'] ?? $_GET['status'] ?? '';
    $date_from = $_POST['date_from'] ?? $_GET['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? $_GET['date_to'] ?? '';
    $area_id = $_POST['area_id'] ?? $_GET['area_id'] ?? '';
    $courier_id = $_POST['courier_id'] ?? $_GET['courier_id'] ?? '';
    $amount_min = $_POST['amount_min'] ?? $_GET['amount_min'] ?? '';
    $amount_max = $_POST['amount_max'] ?? $_GET['amount_max'] ?? '';
    $sort_by = $_POST['sort_by'] ?? $_GET['sort_by'] ?? 'date_created';
    $sort_order = $_POST['sort_order'] ?? $_GET['sort_order'] ?? 'DESC';
    $limit = 50; // عدد النتائج في الصفحة
    
    // بناء استعلام البحث
    $query = "SELECT p.*, ar.name as area_name, c.name as courier_name
              FROM parcels p
              LEFT JOIN areas ar ON p.recipient_area_id = ar.id
              LEFT JOIN couriers c ON p.courier_id = c.id
              WHERE p.agent_id = ?";
    
    $params = [$agent_id];
    $types = 'i';
    
    // إضافة شروط البحث
    if (!empty($reference_number)) {
        $query .= " AND (p.reference_number LIKE ? OR p.id LIKE ?)";
        $params[] = "%$reference_number%";
        $params[] = "%$reference_number%";
        $types .= 'ss';
    }
    
    if (!empty($recipient_name)) {
        $query .= " AND p.recipient_name LIKE ?";
        $params[] = "%$recipient_name%";
        $types .= 's';
    }
    
    if (!empty($status)) {
        $query .= " AND p.status = ?";
        $params[] = $status;
        $types .= 'i';
    }
    
    if (!empty($date_from)) {
        $query .= " AND DATE(p.date_created) >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(p.date_created) <= ?";
        $params[] = $date_to;
        $types .= 's';
    }
    
    if (!empty($area_id)) {
        $query .= " AND p.recipient_area_id = ?";
        $params[] = $area_id;
        $types .= 'i';
    }
    
    if (!empty($courier_id)) {
        $query .= " AND p.courier_id = ?";
        $params[] = $courier_id;
        $types .= 'i';
    }
    
    if (!empty($amount_min)) {
        $query .= " AND p.cod_amount >= ?";
        $params[] = $amount_min;
        $types .= 'd';
    }
    
    if (!empty($amount_max)) {
        $query .= " AND p.cod_amount <= ?";
        $params[] = $amount_max;
        $types .= 'd';
    }
    
    // إضافة الترتيب
    $query .= " ORDER BY p.$sort_by $sort_order LIMIT $limit";
    
    // تنفيذ البحث
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $search_results = $stmt->get_result();
    $total_results = $search_results->num_rows;
}

// جلب المناطق للقائمة المنسدلة
$areas_query = "SELECT id, name FROM areas ORDER BY name";
$areas_result = $conn->query($areas_query);

// جلب المندوبين للقائمة المنسدلة
$couriers_query = "SELECT id, name FROM couriers WHERE status = 1 ORDER BY name";
$couriers_result = $conn->query($couriers_query);

// دالة حالة الشحنة
function getStatusInfo($status) {
    $statuses = [
        1 => ['name' => 'قيد التنفيذ', 'class' => 'bg-secondary', 'icon' => 'fas fa-clock'],
        2 => ['name' => 'تم تسليمها للمندوب', 'class' => 'bg-warning', 'icon' => 'fas fa-user-tie'],
        3 => ['name' => 'جاري التوصيل', 'class' => 'bg-info', 'icon' => 'fas fa-truck'],
        4 => ['name' => 'تم التسليم بنجاح', 'class' => 'bg-success', 'icon' => 'fas fa-check-circle'],
        5 => ['name' => 'تم الدفع بنجاح', 'class' => 'bg-primary', 'icon' => 'fas fa-money-bill-wave'],
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
    <title>البحث المتقدم - <?php echo htmlspecialchars($agent_company); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap">
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
        
        .search-panel {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .search-panel h3 {
            margin-bottom: 1.5rem;
            font-weight: bold;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: none;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
            background: white;
        }
        
        .btn-search {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .results-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50rem;
            font-size: 0.9rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .btn-export {
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
        }
        
        .stats-summary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stats-summary h4 {
            margin-bottom: 1rem;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        
        .stat-item .number {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-item .label {
            font-size: 0.9rem;
            opacity: 0.9;
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
        <h2><i class="fas fa-search me-2"></i>البحث المتقدم في الشحنات</h2>
        <a href="agent_dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i>العودة للوحة التحكم
        </a>
    </div>

    <!-- نموذج البحث -->
    <div class="search-panel">
        <h3><i class="fas fa-filter me-2"></i>معايير البحث</h3>
        <form method="POST" id="searchForm">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">رقم الشحنة</label>
                    <input type="text" class="form-control" name="reference_number" 
                           value="<?php echo htmlspecialchars($_POST['reference_number'] ?? ''); ?>" 
                           placeholder="ابحث برقم الشحنة">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">اسم المستلم</label>
                    <input type="text" class="form-control" name="recipient_name" 
                           value="<?php echo htmlspecialchars($_POST['recipient_name'] ?? ''); ?>" 
                           placeholder="ابحث باسم المستلم">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">حالة الشحنة</label>
                    <select class="form-select" name="status">
                        <option value="">جميع الحالات</option>
                        <option value="1" <?php echo ($_POST['status'] ?? '') == '1' ? 'selected' : ''; ?>>قيد التنفيذ</option>
                        <option value="2" <?php echo ($_POST['status'] ?? '') == '2' ? 'selected' : ''; ?>>مع المندوب</option>
                        <option value="3" <?php echo ($_POST['status'] ?? '') == '3' ? 'selected' : ''; ?>>جاري التوصيل</option>
                        <option value="4" <?php echo ($_POST['status'] ?? '') == '4' ? 'selected' : ''; ?>>تم التسليم</option>
                        <option value="5" <?php echo ($_POST['status'] ?? '') == '5' ? 'selected' : ''; ?>>تم الدفع</option>
                        <option value="8" <?php echo ($_POST['status'] ?? '') == '8' ? 'selected' : ''; ?>>تم الرفض</option>
                        <option value="9" <?php echo ($_POST['status'] ?? '') == '9' ? 'selected' : ''; ?>>مرتجعات</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">المنطقة</label>
                    <select class="form-select" name="area_id">
                        <option value="">جميع المناطق</option>
                        <?php while ($area = $areas_result->fetch_assoc()): ?>
                            <option value="<?php echo $area['id']; ?>" 
                                    <?php echo ($_POST['area_id'] ?? '') == $area['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" class="form-control" name="date_from" 
                           value="<?php echo htmlspecialchars($_POST['date_from'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" class="form-control" name="date_to" 
                           value="<?php echo htmlspecialchars($_POST['date_to'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">المندوب</label>
                    <select class="form-select" name="courier_id">
                        <option value="">جميع المندوبين</option>
                        <?php while ($courier = $couriers_result->fetch_assoc()): ?>
                            <option value="<?php echo $courier['id']; ?>" 
                                    <?php echo ($_POST['courier_id'] ?? '') == $courier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($courier['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">ترتيب النتائج</label>
                    <select class="form-select" name="sort_by">
                        <option value="date_created" <?php echo ($_POST['sort_by'] ?? '') == 'date_created' ? 'selected' : ''; ?>>تاريخ الإنشاء</option>
                        <option value="recipient_name" <?php echo ($_POST['sort_by'] ?? '') == 'recipient_name' ? 'selected' : ''; ?>>اسم المستلم</option>
                        <option value="cod_amount" <?php echo ($_POST['sort_by'] ?? '') == 'cod_amount' ? 'selected' : ''; ?>>المبلغ</option>
                        <option value="status" <?php echo ($_POST['sort_by'] ?? '') == 'status' ? 'selected' : ''; ?>>الحالة</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">المبلغ من</label>
                    <input type="number" class="form-control" name="amount_min" 
                           value="<?php echo htmlspecialchars($_POST['amount_min'] ?? ''); ?>" 
                           placeholder="الحد الأدنى">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">المبلغ إلى</label>
                    <input type="number" class="form-control" name="amount_max" 
                           value="<?php echo htmlspecialchars($_POST['amount_max'] ?? ''); ?>" 
                           placeholder="الحد الأقصى">
                </div>
                <div class="col-md-6 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-search me-2">
                        <i class="fas fa-search me-1"></i>بحث
                    </button>
                    <button type="button" class="btn btn-outline-light" onclick="clearForm()">
                        <i class="fas fa-eraser me-1"></i>مسح
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($search_performed): ?>
        <!-- ملخص النتائج -->
        <div class="stats-summary">
            <h4><i class="fas fa-chart-bar me-2"></i>ملخص النتائج</h4>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="number"><?php echo $total_results; ?></span>
                    <span class="label">إجمالي النتائج</span>
                </div>
                <div class="stat-item">
                    <span class="number"><?php echo number_format($total_results / 50, 1); ?></span>
                    <span class="label">صفحات</span>
                </div>
                <div class="stat-item">
                    <span class="number"><?php echo date('Y-m-d H:i'); ?></span>
                    <span class="label">وقت البحث</span>
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
            <button class="btn btn-info btn-export" onclick="printResults()">
                <i class="fas fa-print me-1"></i>طباعة
            </button>
        </div>

        <!-- نتائج البحث -->
        <div class="results-card">
            <h4><i class="fas fa-list me-2"></i>نتائج البحث (<?php echo $total_results; ?> نتيجة)</h4>
            
            <?php if ($total_results > 0): ?>
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
                            <?php while ($shipment = $search_results->fetch_assoc()): ?>
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
                                        <button class="btn btn-sm btn-primary" onclick="viewDetails(<?php echo $shipment['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="updateStatus(<?php echo $shipment['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">لم يتم العثور على نتائج</h5>
                    <p class="text-muted">جرب تغيير معايير البحث</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// مسح النموذج
function clearForm() {
    document.getElementById('searchForm').reset();
}

// تصدير إلى Excel
function exportToExcel() {
    if (<?php echo $total_results; ?> > 0) {
        // هنا سيتم إضافة كود التصدير
        alert('سيتم إضافة ميزة التصدير إلى Excel قريباً!');
    } else {
        alert('لا توجد نتائج للتصدير');
    }
}

// تصدير إلى PDF
function exportToPDF() {
    if (<?php echo $total_results; ?> > 0) {
        // هنا سيتم إضافة كود التصدير
        alert('سيتم إضافة ميزة التصدير إلى PDF قريباً!');
    } else {
        alert('لا توجد نتائج للتصدير');
    }
}

// طباعة النتائج
function printResults() {
    if (<?php echo $total_results; ?> > 0) {
        window.print();
    } else {
        alert('لا توجد نتائج للطباعة');
    }
}

// عرض تفاصيل الشحنة
function viewDetails(shipmentId) {
    // هنا سيتم إضافة كود عرض التفاصيل
    alert('سيتم إضافة ميزة عرض التفاصيل قريباً!');
}

// تحديث حالة الشحنة
function updateStatus(shipmentId) {
    // هنا سيتم إضافة كود تحديث الحالة
    alert('سيتم إضافة ميزة تحديث الحالة قريباً!');
}

// حفظ معايير البحث في localStorage
document.getElementById('searchForm').addEventListener('submit', function() {
    const formData = new FormData(this);
    const searchParams = {};
    
    for (let [key, value] of formData.entries()) {
        if (value) {
            searchParams[key] = value;
        }
    }
    
    localStorage.setItem('lastSearchParams', JSON.stringify(searchParams));
});

// استعادة معايير البحث المحفوظة
window.addEventListener('load', function() {
    const savedParams = localStorage.getItem('lastSearchParams');
    if (savedParams && !<?php echo $search_performed ? 'true' : 'false'; ?>) {
        const params = JSON.parse(savedParams);
        for (let key in params) {
            const element = document.querySelector(`[name="${key}"]`);
            if (element) {
                element.value = params[key];
            }
        }
    }
});
</script>

</body>
</html>












