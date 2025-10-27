<?php
session_start();
include 'db_connect.php';

// التحقق من تسجيل دخول المندوب
if (!isset($_SESSION['courier_id'])) {
    header("Location: courier_login.php");
    exit();
}

$courier_id = $_SESSION['courier_id'];

// جلب معلومات المندوب
        $courier_query = "SELECT c.*, b.branch_code, b.city as branch_city, g.name as governorate_name, a.name as area_name 
                  FROM couriers c 
                  LEFT JOIN branches b ON c.branch_id = b.id 
                  LEFT JOIN governorates g ON c.governorate_id = g.id 
                  LEFT JOIN areas a ON c.area_id = a.id 
                  WHERE c.id = ?";
$courier_stmt = $conn->prepare($courier_query);
$courier_stmt->bind_param("i", $courier_id);
$courier_stmt->execute();
$courier_result = $courier_stmt->get_result();
$courier = $courier_result->fetch_assoc();

// جلب إحصائيات الشحنات
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 1 THEN 1 END) as new_count,
    COUNT(CASE WHEN status = 2 THEN 1 END) as assigned_count,
    COUNT(CASE WHEN status = 3 THEN 1 END) as picked_count,
    COUNT(CASE WHEN status = 3 THEN 1 END) as transit_count,
    COUNT(CASE WHEN status = 3 THEN 1 END) as out_delivery_count,
    COUNT(CASE WHEN status = 4 THEN 1 END) as delivered_count,
    SUM(CASE WHEN status = 4 THEN shipping_fees END) as total_revenue
FROM parcels WHERE courier_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $courier_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// جلب الشحنات مع فلترة
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

$parcels_query = "SELECT p.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address
                  FROM parcels p 
                  LEFT JOIN customers c ON p.customer_id = c.id 
                  WHERE p.courier_id = ?";

$params = [$courier_id];
$types = "i";

if ($status_filter) {
    $parcels_query .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search_filter) {
    $parcels_query .= " AND (p.tracking_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if ($date_filter) {
    $parcels_query .= " AND DATE(p.date_created) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$parcels_query .= " ORDER BY p.date_created DESC LIMIT 50";

$parcels_stmt = $conn->prepare($parcels_query);
$parcels_stmt->bind_param($types, ...$params);
$parcels_stmt->execute();
$parcels_result = $parcels_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المندوب - <?php echo htmlspecialchars($courier['name']); ?></title>
    
    <!-- Bootstrap RTL CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #ecf0f1;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-1 { background-color: #e3f2fd; color: #1976d2; }
        .status-2 { background-color: #fff3e0; color: #f57c00; }
        .status-3 { background-color: #e8f5e8; color: #388e3c; }
        .status-4 { background-color: #e8f5e8; color: #388e3c; }
        .status-5 { background-color: #e8f5e8; color: #388e3c; }
        .status-6 { background-color: #fff8e1; color: #fbc02d; }
        .status-7 { background-color: #f3e5f5; color: #7b1fa2; }
        .status-8 { background-color: #ffebee; color: #d32f2f; }
        .status-9 { background-color: #fce4ec; color: #c2185b; }
        .status-10 { background-color: #fce4ec; color: #c2185b; }
        
        .btn-action {
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            transform: scale(1.05);
        }
        
        .search-box {
            border-radius: 25px;
            border: 2px solid #e0e0e0;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .search-box:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .parcel-card {
            border-left: 4px solid var(--secondary-color);
            margin-bottom: 15px;
        }
        
        .loading {
            display: none;
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-shipping-fast me-2"></i>
                نظام إدارة الشحنات
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user me-1"></i>
                    <?php echo htmlspecialchars($courier['name']); ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="courier_logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    تسجيل خروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- معلومات المندوب -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="card-title mb-2">
                                    <i class="fas fa-user-circle me-2 text-primary"></i>
                                    معلومات المندوب
                                </h5>
                                <div class="row">
                                    <div class="col-md-2">
                                        <strong>الاسم:</strong> <?php echo htmlspecialchars($courier['name']); ?>
                                    </div>
                                    <div class="col-md-2">
                                        <strong>الهاتف:</strong> <?php echo htmlspecialchars($courier['phone']); ?>
                                    </div>
                                    <div class="col-md-2">
                                        <strong>الفرع:</strong> <?php echo htmlspecialchars($courier['branch_city'] ?? $courier['branch_code'] ?? 'غير محدد'); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>المحافظة:</strong> <?php echo htmlspecialchars($courier['governorate_name'] ?? 'غير محدد'); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>المنطقة:</strong> <?php echo htmlspecialchars($courier['area_name'] ?? 'غير محدد'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-primary btn-action" onclick="refreshData()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    تحديث البيانات
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الإحصائيات -->
        <div class="row mb-4">
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-box fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo $stats['new_count']; ?></h4>
                        <small>شحنات جديدة</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-hand-paper fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo $stats['assigned_count']; ?></h4>
                        <small>مُسندة</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-truck fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo $stats['picked_count']; ?></h4>
                        <small>تم الاستلام</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-route fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo $stats['transit_count']; ?></h4>
                        <small>قيد النقل</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-shipping-fast fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo $stats['out_delivery_count']; ?></h4>
                        <small>خارج للتوصيل</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo $stats['delivered_count']; ?></h4>
                        <small>تم التوصيل</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- فلترة وبحث -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form id="filterForm" class="row g-3">
                            <div class="col-md-3">
                                <select class="form-select search-box" name="status" id="statusFilter">
                                    <option value="">جميع الحالات</option>
                                    <option value="1" <?php echo $status_filter == '1' ? 'selected' : ''; ?>>قيد التنفيذ</option>
                                    <option value="2" <?php echo $status_filter == '2' ? 'selected' : ''; ?>>تم تسليمها للمندوب</option>
                                    <option value="3" <?php echo $status_filter == '3' ? 'selected' : ''; ?>>جاري التوصيل</option>
                                    <option value="4" <?php echo $status_filter == '4' ? 'selected' : ''; ?>>تم التسليم بنجاح</option>
                                    <option value="5" <?php echo $status_filter == '5' ? 'selected' : ''; ?>>تم الدفع بنجاح</option>
                                    <option value="6" <?php echo $status_filter == '6' ? 'selected' : ''; ?>>تم الدفع جزئي</option>
                                    <option value="7" <?php echo $status_filter == '7' ? 'selected' : ''; ?>>تم تأجيل الطلب</option>
                                    <option value="8" <?php echo $status_filter == '8' ? 'selected' : ''; ?>>تم الرفض</option>
                                    <option value="9" <?php echo $status_filter == '9' ? 'selected' : ''; ?>>مرتجع للفرع</option>
                                    <option value="10" <?php echo $status_filter == '10' ? 'selected' : ''; ?>>مرتجع للعميل</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control search-box" name="search" id="searchInput" 
                                       placeholder="بحث في رقم التتبع، اسم العميل، الهاتف..." 
                                       value="<?php echo htmlspecialchars($search_filter); ?>">
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control search-box" name="date" id="dateFilter" 
                                       value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-action w-100">
                                    <i class="fas fa-search me-1"></i>
                                    بحث
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- قائمة الشحنات -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            قائمة الشحنات
                        </h5>
                        <span class="badge bg-primary fs-6"><?php echo $parcels_result->num_rows; ?> شحنة</span>
                    </div>
                    <div class="card-body">
                        <div id="parcelsContainer">
                            <?php if ($parcels_result->num_rows > 0): ?>
                                <?php while ($parcel = $parcels_result->fetch_assoc()): ?>
                                    <div class="card parcel-card mb-3" data-parcel-id="<?php echo $parcel['id']; ?>">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-2">
                                                    <strong>رقم التتبع:</strong><br>
                                                    <span class="text-primary"><?php echo htmlspecialchars($parcel['tracking_number']); ?></span>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong>العميل:</strong><br>
                                                    <span><?php echo htmlspecialchars($parcel['customer_name']); ?></span>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong>الهاتف:</strong><br>
                                                    <span><?php echo htmlspecialchars($parcel['customer_phone']); ?></span>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong>العنوان:</strong><br>
                                                    <small><?php echo htmlspecialchars($parcel['customer_address']); ?></small>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong>الحالة:</strong><br>
                                                    <span class="status-badge status-<?php echo $parcel['status']; ?>">
                                                        <?php echo getStatusText($parcel['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-2 text-end">
                                                    <div class="btn-group-vertical">
                                                        <?php if (in_array($parcel['status'], [1, 2])): ?>
                                                            <button class="btn btn-success btn-sm btn-action mb-1" 
                                                                    onclick="changeStatus(<?php echo $parcel['id']; ?>, 3)">
                                                                <i class="fas fa-hand-paper me-1"></i>
                                                                استلام
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($parcel['status'] == 3): ?>
                                                            <button class="btn btn-info btn-sm btn-action mb-1" 
                                                                    onclick="changeStatus(<?php echo $parcel['id']; ?>, 4)">
                                                                <i class="fas fa-route me-1"></i>
                                                                بدء النقل
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($parcel['status'] == 3): ?>
                                                            <button class="btn btn-warning btn-sm btn-action mb-1" 
                                                                    onclick="changeStatus(<?php echo $parcel['id']; ?>, 4)">
                                                                <i class="fas fa-shipping-fast me-1"></i>
                                                                خارج للتوصيل
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($parcel['status'] == 3): ?>
                                                            <button class="btn btn-success btn-sm btn-action mb-1" 
                                                                    onclick="changeStatus(<?php echo $parcel['id']; ?>, 4)">
                                                                <i class="fas fa-check me-1"></i>
                                                                تم التوصيل
                                                            </button>
                                                            <button class="btn btn-danger btn-sm btn-action mb-1" 
                                                                    onclick="changeStatus(<?php echo $parcel['id']; ?>, 8)">
                                                                <i class="fas fa-times me-1"></i>
                                                                فشل التوصيل
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button class="btn btn-secondary btn-sm btn-action" 
                                                                onclick="showParcelDetails(<?php echo $parcel['id']; ?>)">
                                                            <i class="fas fa-eye me-1"></i>
                                                            تفاصيل
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">لا توجد شحنات</h5>
                                    <p class="text-muted">لم يتم العثور على شحنات تطابق معايير البحث</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال تفاصيل الشحنة -->
<div class="modal fade" id="parcelDetailsModal" tabindex="-1" aria-labelledby="parcelDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="parcelDetailsModalLabel">تفاصيل الشحنة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body" id="parcelDetailsContent">
        <!-- التفاصيل يتم تحميلها من ajax -->
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <div>جارٍ تحميل التفاصيل...</div>
        </div>
      </div>
      <div class="modal-footer">
        <select id="statusSelect" class="form-select w-auto me-auto">
          <!-- خيارات الحالات -->
          <option value="" disabled selected>تغيير حالة الشحنة</option>
          <option value="1">جديد</option>
          <option value="2">مُسند</option>
          <option value="3">تم الاستلام</option>
          <option value="4">قيد النقل</option>
          <option value="5">خارج للتوصيل</option>
          <option value="6">تم التوصيل</option>
          <option value="7">فشل التوصيل</option>
          <option value="8">مرتجع</option>
          <option value="9">ملغي</option>
          <option value="10">معلق</option>
        </select>
        <button id="btnUpdateStatus" class="btn btn-success" disabled>
          <i class="fas fa-sync-alt me-1"></i> تحديث الحالة
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
      </div>
    </div>
  </div>
</div>

    <!-- Toast Notifications -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Loading Spinner -->
    <div class="loading position-fixed top-50 start-50 translate-middle" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">جاري التحميل...</span>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        // تحديث البيانات
        function refreshData() {
            location.reload();
        }

        // تغيير حالة الشحنة
        function changeStatus(parcelId, newStatus) {
            if (!confirm('هل أنت متأكد من تغيير حالة هذه الشحنة؟')) {
                return;
            }

            showLoading(true);

            fetch('ajax/change_parcel_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    parcel_id: parcelId,
                    new_status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    showToast('تم تغيير الحالة بنجاح', 'success');
                    // تحديث الواجهة
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('حدث خطأ: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showToast('حدث خطأ في الاتصال', 'error');
                console.error('Error:', error);
            });
        }

        let currentParcelId = null;

// فتح المودال وتحميل التفاصيل
function showParcelDetails(parcelId) {
  currentParcelId = parcelId;
  document.getElementById('statusSelect').value = "";
  document.getElementById('btnUpdateStatus').disabled = true;

  const contentDiv = document.getElementById('parcelDetailsContent');
  contentDiv.innerHTML = `
    <div class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <div>جارٍ تحميل التفاصيل...</div>
    </div>
  `;

  const modal = new bootstrap.Modal(document.getElementById('parcelDetailsModal'));
  modal.show();

  fetch(`ajax/get_parcel_details.php?parcel_id=${parcelId}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        contentDiv.innerHTML = data.html;
      } else {
        contentDiv.innerHTML = `<p class="text-danger text-center">خطأ: ${data.message}</p>`;
      }
    })
    .catch(() => {
      contentDiv.innerHTML = `<p class="text-danger text-center">حدث خطأ في تحميل البيانات</p>`;
    });
}

// تفعيل زر التحديث عند اختيار حالة جديدة
document.getElementById('statusSelect').addEventListener('change', function() {
  document.getElementById('btnUpdateStatus').disabled = !this.value;
});

// حدث تحديث الحالة
document.getElementById('btnUpdateStatus').addEventListener('click', function() {
  const newStatus = document.getElementById('statusSelect').value;
  if (!newStatus || !currentParcelId) return;

  this.disabled = true;
  this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري التحديث...`;

  fetch('ajax/change_parcel_status.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ parcel_id: currentParcelId, new_status: parseInt(newStatus) })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(data.message, 'success');
      // إعادة تحميل تفاصيل الشحنة لعرض الحالة الجديدة
      showParcelDetails(currentParcelId);
    } else {
      showToast(data.message, 'error');
    }
  })
  .catch(() => showToast('حدث خطأ في الاتصال', 'error'))
  .finally(() => {
    this.disabled = false;
    this.innerHTML = `<i class="fas fa-sync-alt me-1"></i> تحديث الحالة`;
    document.getElementById('statusSelect').value = "";
    this.disabled = true;
  });
});

// دالة عرض الإشعارات (toast)
function showToast(message, type = 'info') {
  const toastContainer = document.getElementById('toastContainer');
  if (!toastContainer) {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.position = 'fixed';
    container.style.top = '1rem';
    container.style.left = '50%';
    container.style.transform = 'translateX(-50%)';
    container.style.zIndex = '1055';
    document.body.appendChild(container);
  }

  const toastId = 'toast-' + Date.now();
  const toastHtml = `
    <div class="toast show align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0 mb-2" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  `;
  document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);

  setTimeout(() => {
    const toast = document.getElementById(toastId);
    if (toast) toast.remove();
  }, 4000);
}

        // عرض/إخفاء Loading
        function showLoading(show) {
            document.getElementById('loadingSpinner').style.display = show ? 'block' : 'none';
        }

        // عرض Toast
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const toastHtml = `
                <div class="toast show" id="${toastId}" role="alert">
                    <div class="toast-header bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} text-white">
                        <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                        <strong class="me-auto">إشعار</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            // إزالة Toast بعد 5 ثواني
            setTimeout(() => {
                const toast = document.getElementById(toastId);
                if (toast) {
                    toast.remove();
                }
            }, 5000);
        }

        // تحديث تلقائي كل 30 ثانية
        setInterval(() => {
            // تحديث الإحصائيات فقط
            fetch('ajax/get_courier_stats.php?courier_id=<?php echo $courier_id; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // تحديث الإحصائيات في الواجهة
                    updateStats(data.stats);
                }
            })
            .catch(error => console.error('Error updating stats:', error));
        }, 30000);

        // تحديث الإحصائيات
        function updateStats(stats) {
            // تحديث الأرقام في البطاقات
            const statElements = document.querySelectorAll('.stats-card h4');
            if (statElements.length >= 6) {
                statElements[0].textContent = stats.new_count;
                statElements[1].textContent = stats.assigned_count;
                statElements[2].textContent = stats.picked_count;
                statElements[3].textContent = stats.transit_count;
                statElements[4].textContent = stats.out_delivery_count;
                statElements[5].textContent = stats.delivered_count;
            }
        }

        // إضافة ملاحظة جديدة
        function addNote(parcelId) {
  const noteText = document.getElementById('newNote').value.trim();
  if (!noteText) {
    showToast('يرجى كتابة الملاحظة', 'error');
    return;
  }

  fetch('ajax/add_parcel_note.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({parcel_id: parcelId, note: noteText})
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast('تمت إضافة الملاحظة', 'success');
      document.getElementById('newNote').value = '';
      // إعادة تحميل التفاصيل لعرض الملاحظة الجديدة
      showParcelDetails(parcelId);
    } else {
      showToast(data.message, 'error');
    }
  })
  .catch(() => showToast('حدث خطأ في الاتصال', 'error'));
}

        // فلترة تلقائية عند تغيير القيم
        document.getElementById('statusFilter').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('dateFilter').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        // البحث مع تأخير
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    </script>
</body>
</html>

<?php
// دالة مساعدة لتحويل حالة الشحنة إلى نص عربي
function getStatusText($status) {
    $statusMap = [
        1 => 'قيد التنفيذ',
        2 => 'تم تسليمها للمندوب',
        3 => 'جاري التوصيل',
        4 => 'تم التسليم بنجاح',
        5 => 'تم الدفع بنجاح',
        6 => 'تم الدفع جزئي',
        7 => 'تم تأجيل الطلب',
        8 => 'تم الرفض',
        9 => 'مرتجع للفرع',
        10 => 'مرتجع للعميل'
    ];
    
    return $statusMap[$status] ?? $status;
}
?>