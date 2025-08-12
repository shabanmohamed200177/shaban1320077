<?php
// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// التحقق من وجود معرف الوكيل
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $agent_id = intval($_GET['id']);
    
    // جلب بيانات الوكيل
    $agent_query = "SELECT * FROM agents WHERE id = ?";
    $stmt = $conn->prepare($agent_query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $agent_result = $stmt->get_result();
    
    if ($agent_result->num_rows === 0) {
    echo '<div class="container mt-5"><div class="alert alert-danger text-center">الوكيل غير موجود.</div></div>';
    exit;
}

    $agent = $agent_result->fetch_assoc();
} else {
    echo '<div class="container mt-5"><div class="alert alert-danger text-center">معرف الوكيل غير صحيح.</div></div>';
  exit;
}

// جلب الإحصائيات الشاملة للوكيل
function getAgentStatistics($conn, $agent_id) {
    $stats = [
        'total_shipments' => 0,
        'to_agent_shipments' => 0,
        'from_agent_shipments' => 0,
        'delivered_shipments' => 0,
        'pending_shipments' => 0,
        'returned_shipments' => 0,
        'total_revenue' => 0,
        'total_cod' => 0,
        'total_shipping_fees' => 0,
        'company_profit' => 0,
        'agent_commission' => 0,
        'unpaid_amount' => 0,
        'recent_activity' => []
    ];
    
    // إحصائيات عامة
    $general_query = "SELECT 
                        COUNT(*) as total_shipments,
                        SUM(CASE WHEN shipment_direction = 'to_agent' THEN 1 ELSE 0 END) as to_agent_shipments,
                        SUM(CASE WHEN shipment_direction = 'from_agent' THEN 1 ELSE 0 END) as from_agent_shipments,
                        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered_shipments,
                        SUM(CASE WHEN status IN (1,2,3) THEN 1 ELSE 0 END) as pending_shipments,
                        SUM(CASE WHEN status IN (8,9,10,11,12) THEN 1 ELSE 0 END) as returned_shipments,
                        SUM(cod_amount) as total_cod,
                        SUM(shipping_fees) as total_shipping_fees
                      FROM parcels WHERE agent_id = ?";
    
    $stmt = $conn->prepare($general_query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $stats = array_merge($stats, $result);
    
    // حساب العمولة والأرباح
    $financial_query = "SELECT 
                          SUM(agent_share) as agent_commission,
                          SUM(company_profit) as company_profit,
                          SUM(CASE WHEN status = 4 AND payment_status IN ('unpaid', 'partially_paid') THEN cod_amount ELSE 0 END) as unpaid_amount
                        FROM parcels WHERE agent_id = ?";
    
    $stmt = $conn->prepare($financial_query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $financial_result = $stmt->get_result()->fetch_assoc();
    
    $stats = array_merge($stats, $financial_result);
    
    // آخر 5 شحنات
    $recent_query = "SELECT id, reference_number, recipient_name, status, date_created, shipment_direction
                     FROM parcels WHERE agent_id = ? 
                     ORDER BY date_created DESC LIMIT 5";
    
    $stmt = $conn->prepare($recent_query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $recent_result = $stmt->get_result();
    
    while ($row = $recent_result->fetch_assoc()) {
        $stats['recent_activity'][] = $row;
    }
    
    return $stats;
}

// جلب آخر عمليات الدفع
function getRecentPayments($conn, $agent_id) {
    $check_table = $conn->query("SHOW TABLES LIKE 'payments_log'");
    if ($check_table->num_rows == 0) {
        return [];
    }
    
    $query = "SELECT pl.*, CONCAT(u.firstname, ' ', u.lastname) as user_name
              FROM payments_log pl
              LEFT JOIN users u ON pl.user_id = u.id
              WHERE pl.agent_id = ?
              ORDER BY pl.payment_date DESC LIMIT 3";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($payment = $result->fetch_assoc()) {
        $payments[] = $payment;
    }
    
    return $payments;
}

// دالة لحالة الشحنة
function getStatusInfo($status) {
    $statuses = [
        1 => ['name' => 'قيد التنفيذ', 'class' => 'bg-secondary'],
        2 => ['name' => 'تم تسليمها للمندوب', 'class' => 'bg-warning'],
        3 => ['name' => 'جاري التوصيل', 'class' => 'bg-info'],
        4 => ['name' => 'تم التسليم بنجاح', 'class' => 'bg-success'],
        5 => ['name' => 'تم الدفع بنجاح', 'class' => 'bg-primary'],
        6 => ['name' => 'تم الدفع جزئي', 'class' => 'bg-warning'],
        7 => ['name' => 'تم تأجيل الطلب', 'class' => 'bg-secondary'],
        8 => ['name' => 'تم الرفض', 'class' => 'bg-danger'],
        9 => ['name' => 'المرتجعات', 'class' => 'bg-info'],
        10 => ['name' => 'مرتجع للمخزن', 'class' => 'bg-dark'],
        11 => ['name' => 'مرتجع للفرع', 'class' => 'bg-success'],
        12 => ['name' => 'مرتجع للعميل', 'class' => 'bg-danger']
    ];
    
    return $statuses[$status] ?? ['name' => 'غير محدد', 'class' => 'bg-secondary'];
}

$statistics = getAgentStatistics($conn, $agent_id);
$recent_payments = getRecentPayments($conn, $agent_id);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملف الوكيل - <?php echo htmlspecialchars($agent['company_name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; direction: rtl; background-color: #f4f7f6; }
        .card { border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: none; }
        .card-header { background-color: #17a2b8; color: white; border-radius: 1rem 1rem 0 0; padding: 1.5rem; text-align: center; }
        .table thead th { background-color: #17a2b8; color: #fff; border-color: #17a2b8; }
        .table tbody tr:hover { background-color: #e9ecef; }
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
        .amount-display { font-size: 1.2rem; font-weight: bold; color: #495057; }
        .profit-positive { color: #28a745; }
        .profit-negative { color: #dc3545; }
        .info-item { 
            border-bottom: 1px solid #e9ecef; 
            padding: 0.75rem 0; 
        }
        .info-item:last-child { border-bottom: none; }
        .info-label { 
            font-weight: bold; 
            color: #495057; 
            font-size: 0.9rem;
        }
        .info-value { 
            color: #6c757d; 
            font-size: 0.9rem;
            margin-top: 0.2rem;
        }
        .nav-tabs .nav-link { 
            border-radius: 0.5rem 0.5rem 0 0; 
            color: #495057 !important;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active { 
            background-color: #17a2b8 !important; 
            color: white !important; 
            border-color: #17a2b8 !important;
        }
        .status-badge { 
            padding: 0.3rem 0.8rem; 
            border-radius: 50rem; 
            font-size: 0.8rem; 
            font-weight: bold; 
        }
        
        /* تحسين التباين */
        .card-header h3, .card-header h5 {
            color: white !important;
        }
        .card-header p {
            color: rgba(255,255,255,0.9) !important;
        }
        
        /* تحسين الجداول */
        .table {
            background-color: white;
        }
        .table td, .table th {
            vertical-align: middle;
            padding: 0.75rem;
        }
        
        /* تحسين النصوص الصغيرة */
        .text-muted {
            color: #6c757d !important;
        }
        
        /* تحسين الباكجات */
        .bg-light {
            background-color: #f8f9fa !important;
        }
    </style>
</head>
<body>

<!-- Header with Agent Info -->
<div class="container-fluid" style="background-color: #f4f7f6; min-height: 100vh; padding: 20px;">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-0" style="color: white !important; font-weight: bold;">
                                <i class="fas fa-user-tie me-2"></i>
                                <?php echo htmlspecialchars($agent['company_name']); ?>
                            </h3>
                            <p class="mb-0 mt-2" style="color: rgba(255,255,255,0.9) !important; font-size: 1rem;">
                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($agent['contact_person']); ?>
                                <span class="ms-3">
                                    <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($agent['phone']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge <?php echo $agent['status'] == 1 ? 'bg-success' : 'bg-warning'; ?> fs-5 p-3" style="color: white !important; font-weight: bold;">
                                <i class="fas <?php echo $agent['status'] == 1 ? 'fa-check-circle' : 'fa-pause-circle'; ?> me-2"></i>
                                <?php echo $agent['status'] == 1 ? 'نشط' : 'غير نشط'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <h3><?php echo number_format($statistics['total_shipments']); ?></h3>
                <p>إجمالي الشحنات</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h3><?php echo number_format($statistics['delivered_shipments']); ?></h3>
                <p>شحنات مسلمة</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h3><?php echo number_format($statistics['pending_shipments']); ?></h3>
                <p>شحنات قيد التنفيذ</p>
        </div>
                </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h3><?php echo number_format($statistics['total_cod'], 2); ?> ج.م</h3>
                <p>إجمالي التحصيل</p>
                </div>
              </div>
            </div>

    <!-- Main Content -->
    <div class="row">
        <!-- Agent Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">معلومات الوكيل</h5>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <div class="info-label">الشركة</div>
                        <div class="info-value"><?php echo htmlspecialchars($agent['company_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">المسؤول</div>
                        <div class="info-value"><?php echo htmlspecialchars($agent['contact_person']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">رقم الهاتف</div>
                        <div class="info-value"><?php echo htmlspecialchars($agent['phone']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">البريد الإلكتروني</div>
                        <div class="info-value"><?php echo htmlspecialchars($agent['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">النطاق الجغرافي</div>
                        <div class="info-value"><?php echo htmlspecialchars($agent['geo_area']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">العنوان</div>
                        <div class="info-value"><?php echo htmlspecialchars($agent['address'] ?? 'غير محدد'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">نوع التعاون</div>
                        <div class="info-value"><?php echo htmlspecialchars($agent['coop_type'] ?? 'غير محدد'); ?></div>
                </div>
                    <div class="info-item">
                        <div class="info-label">العمولة</div>
                        <div class="info-value">
                      <?php
                            if($agent['commission_type'] == 'percent') {
                                echo htmlspecialchars($agent['commission_value']) . ' %';
                            } elseif($agent['commission_type'] == 'fixed') {
                                echo htmlspecialchars($agent['commission_value']) . ' جنيه';
                            } else {
                                echo 'غير محدد';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">الملخص المالي</h5>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <div class="info-label">إجمالي العمولة</div>
                        <div class="amount-display profit-positive"><?php echo number_format($statistics['agent_commission'], 2); ?> ج.م</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ربح الشركة</div>
                        <div class="amount-display profit-positive"><?php echo number_format($statistics['company_profit'], 2); ?> ج.م</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">مبالغ غير مدفوعة</div>
                        <div class="amount-display profit-negative"><?php echo number_format($statistics['unpaid_amount'], 2); ?> ج.م</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity and Details -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                النشاط الأخير
                            </button>
                    </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                                آخر المدفوعات
                            </button>
                    </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="breakdown-tab" data-bs-toggle="tab" data-bs-target="#breakdown" type="button" role="tab">
                                تفصيل الشحنات
                            </button>
                    </li>
                  </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="profileTabsContent">
                        <!-- Recent Activity -->
                        <div class="tab-pane fade show active" id="activity" role="tabpanel">
                            <h6 class="mb-3">آخر 5 شحنات</h6>
                            <?php if (!empty($statistics['recent_activity'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>رقم الشحنة</th>
                                                <th>المستلم</th>
                                                <th>النوع</th>
                                                <th>الحالة</th>
                                                <th>التاريخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($statistics['recent_activity'] as $activity): ?>
                                                <?php $status_info = getStatusInfo($activity['status']); ?>
                                                <tr>
                                                    <td>
                                                        <small class="text-primary">
                                                            #<?php echo htmlspecialchars($activity['reference_number'] ?? $activity['id']); ?>
                                                        </small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($activity['recipient_name'] ?? 'غير محدد'); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $activity['shipment_direction'] == 'to_agent' ? 'bg-info' : 'bg-warning'; ?>">
                                                            <?php echo $activity['shipment_direction'] == 'to_agent' ? 'مرسلة' : 'مستلمة'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $status_info['class']; ?>">
                                                            <?php echo $status_info['name']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('Y-m-d', strtotime($activity['date_created'])); ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    لا توجد شحنات حتى الآن
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Recent Payments -->
                        <div class="tab-pane fade" id="payments" role="tabpanel">
                            <h6 class="mb-3">آخر 3 عمليات دفع</h6>
                            <?php if (!empty($recent_payments)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>رقم العملية</th>
                                                <th>المبلغ</th>
                                                <th>التاريخ</th>
                                                <th>المستخدم</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_payments as $payment): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo htmlspecialchars($payment['transaction_number']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="amount-display">
                                                        <?php echo number_format($payment['amount'], 2); ?> ج.م
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('Y-m-d H:i', strtotime($payment['payment_date'])); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($payment['user_name'] ?? 'غير محدد'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    لا توجد عمليات دفع مسجلة
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Shipment Breakdown -->
                        <div class="tab-pane fade" id="breakdown" role="tabpanel">
                            <h6 class="mb-3">تفصيل الشحنات حسب النوع</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5>شحنات مرسلة للوكيل</h5>
                                            <h3 class="text-info"><?php echo number_format($statistics['to_agent_shipments']); ?></h3>
                                            <p class="text-muted">الوكيل يستلم ويوصل</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5>شحنات مستلمة من الوكيل</h5>
                                            <h3 class="text-warning"><?php echo number_format($statistics['from_agent_shipments']); ?></h3>
                                            <p class="text-muted">أنت تستلم وتوصل</p>
                </div>
              </div>
            </div>
          </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6>شحنات مرتجعة</h6>
                                        <h4 class="text-danger"><?php echo number_format($statistics['returned_shipments']); ?></h4>
                                    </div>
                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6>رسوم الشحن</h6>
                                        <h4 class="text-primary"><?php echo number_format($statistics['total_shipping_fees'], 2); ?> ج.م</h4>
                      </div>
                    </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6>معدل النجاح</h6>
                                        <h4 class="text-success">
                                            <?php 
                                            $success_rate = $statistics['total_shipments'] > 0 ? 
                                                ($statistics['delivered_shipments'] / $statistics['total_shipments']) * 100 : 0;
                                            echo number_format($success_rate, 1);
                                            ?>%
                                        </h4>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
            </div>
          </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="mb-3">إجراءات سريعة</h6>
                    <a href="index.php?page=agent_shipments&id=<?php echo $agent_id; ?>" class="btn btn-success me-2">
                        <i class="fas fa-shipping-fast me-2"></i>عرض الشحنات
                    </a>
                    <a href="index.php?page=agent_finance&id=<?php echo $agent_id; ?>" class="btn btn-warning me-2">
                        <i class="fas fa-file-invoice-dollar me-2"></i>الحسابات المالية
                    </a>
                    <a href="index.php?page=new_parcel&agent_id=<?php echo $agent_id; ?>" class="btn btn-primary me-2">
                        <i class="fas fa-plus me-2"></i>شحنة جديدة
                    </a>
                    <a href="index.php?page=agent_list" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>العودة للقائمة
                    </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>