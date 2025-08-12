<?php
// ملف: customer_profile.php - صفحة ملف العميل الاحترافية
// تم إنشاؤها لتكون متطابقة مع تصميم باقي النظام

// تفعيل عرض الأخطاء للمساعدة في التطوير
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التأكد من وجود ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// التحقق من وجود معرف العميل
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($customer_id <= 0) {
    echo '<div class="alert alert-danger">معرف العميل غير صحيح</div>';
    exit;
}

// جلب بيانات العميل الأساسية مع المعلومات المحدثة
$customer_query = "
    SELECT 
        c.*,
        g.name as governorate_name, 
        a.name as area_name,
        ca.account_number,
        ca.balance as account_balance,
        ca.credit_limit,
        ca.status as account_status,
        cr.payment_reliability,
        cr.volume_rating,
        cr.cooperation_rating,
        cr.overall_rating,
        cr.risk_level,
        cps.preferred_payment_method,
        cps.payment_reminder_days,
        cps.minimum_payment_amount
    FROM customers c
    LEFT JOIN governorates g ON c.governorate_id = g.id
    LEFT JOIN areas a ON c.area_id = a.id
    LEFT JOIN customer_accounts ca ON c.id = ca.customer_id
    LEFT JOIN customer_ratings cr ON c.id = cr.customer_id
    LEFT JOIN customer_payment_settings cps ON c.id = cps.customer_id
    WHERE c.id = ?
";

$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();

if ($customer_result->num_rows === 0) {
    echo '<div class="alert alert-danger">لم يتم العثور على العميل</div>';
    exit;
}

$customer = $customer_result->fetch_assoc();

// جلب إحصائيات الشحنات
$stats_query = "
    SELECT 
        COUNT(*) as total_shipments,
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered_shipments,
        SUM(CASE WHEN payment_status = 'partial_paid' THEN 1 ELSE 0 END) as partial_paid_shipments,
        SUM(CASE WHEN status IN (9, 10, 11, 12) THEN 1 ELSE 0 END) as returned_shipments,
        SUM(CASE WHEN status = 4 THEN cod_amount ELSE 0 END) as delivered_amount,
        SUM(CASE WHEN payment_status = 'partial_paid' THEN (cod_amount - COALESCE(paid_amount, 0)) ELSE 0 END) as partial_remaining_amount,
        SUM(CASE WHEN status IN (9, 10, 11, 12) THEN return_value ELSE 0 END) as returned_amount,
        SUM(CASE WHEN status = 4 THEN COALESCE(paid_amount, 0) ELSE 0 END) as total_paid_amount
    FROM parcels 
    WHERE client_id_fk = ?
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $customer_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// جلب معلومات الرصيد من جدول customer_accounts
$balance_query = "SELECT balance FROM customer_accounts WHERE customer_id = ?";
$balance_stmt = $conn->prepare($balance_query);
$balance_stmt->bind_param("i", $customer_id);
$balance_stmt->execute();
$balance_result = $balance_stmt->get_result();
$account_balance = $balance_result->num_rows > 0 ? $balance_result->fetch_assoc()['balance'] : 0;

// حساب الرصيد الحالي
$current_balance = floatval($account_balance);

// جلب آخر 5 معاملات مالية
$recent_transactions_query = "
    SELECT 
        cb.type,
        cb.amount,
        cb.description,
        cb.balance_after,
        cb.created_at,
        cb.reference_type,
        cb.reference_id
    FROM customer_balances cb
    WHERE cb.customer_id = ?
    ORDER BY cb.created_at DESC
    LIMIT 5
";

$transactions_stmt = $conn->prepare($recent_transactions_query);
$transactions_stmt->bind_param("i", $customer_id);
$transactions_stmt->execute();
$recent_transactions = $transactions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// جلب آخر 5 أنشطة
$recent_activities_query = "
    SELECT 
        cal.activity_type,
        cal.description,
        cal.created_at,
        cal.ip_address
    FROM customer_activity_log cal
    WHERE cal.customer_id = ?
    ORDER BY cal.created_at DESC
    LIMIT 5
";

$activities_stmt = $conn->prepare($recent_activities_query);
$activities_stmt->bind_param("i", $customer_id);
$activities_stmt->execute();
$recent_activities = $activities_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملف العميل - <?php echo htmlspecialchars($customer['name']); ?></title>
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    
    <style>
        body { 
            font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            direction: rtl; 
        }
        
        .main-header {
            background: linear-gradient(135deg, #1976d2, #28a745);
        }
        
        .content-header h1 {
            color: #1976d2;
            font-weight: bold;
        }
        
        .card-outline-primary {
            border-top: 3px solid #1976d2;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #1976d2, #28a745);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, #1976d2, #28a745);
            color: white;
            box-shadow: 0 3px 10px rgba(25, 118, 210, 0.3);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .balance-display {
            font-size: 1.5rem;
            font-weight: bold;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            margin: 1rem 0;
        }
        
        .balance-positive {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 2px solid #28a745;
        }
        
        .balance-negative {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 2px solid #dc3545;
        }
        
        .balance-zero {
            background: linear-gradient(135deg, #e2e3e5, #d6d8db);
            color: #383d41;
            border: 2px solid #6c757d;
        }
        
        .customer-info-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .customer-info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-radius: 8px;
            background: rgba(248, 249, 250, 0.5);
        }
        
        .info-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1976d2, #28a745);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 1rem;
            font-size: 0.9rem;
        }
        
        .info-content h6 {
            margin: 0;
            color: #1976d2;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .info-content p {
            margin: 0;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .info-item-small {
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            border-radius: 6px;
            background: rgba(248, 249, 250, 0.3);
        }
        
        .info-item-small label {
            display: block;
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        
        .rating-stars {
            display: flex;
            gap: 2px;
        }
        
        .rating-stars .fas {
            font-size: 0.8rem;
        }
        
        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            margin-left: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .activity-icon .fas {
            font-size: 0.6rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #1976d2;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .activity-description {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        
        .activity-meta {
            font-size: 0.75rem;
        }
        
        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 0.75rem 1rem;
        }
        
        .nav-tabs .nav-link.active {
            color: #1976d2;
            background: transparent;
            border-bottom: 2px solid #1976d2;
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #1976d2, #28a745);
            color: white;
            font-weight: 600;
            border: none;
            padding: 0.75rem;
            font-size: 0.85rem;
        }
        
        .btn-sm {
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .stat-card { padding: 1rem; }
            .stat-icon { width: 50px; height: 50px; font-size: 1.2rem; }
            .stat-value { font-size: 1.5rem; }
            .customer-info-card { padding: 1rem; }
            .content-header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>
                        <i class="fas fa-user-circle text-primary me-2"></i>
                        ملف العميل: <?php echo htmlspecialchars($customer['name']); ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-left">
                        <li class="breadcrumb-item"><a href="./">الرئيسية</a></li>
                        <li class="breadcrumb-item active">ملف العميل</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

    <!-- معلومات العميل الأساسية -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="customer-info-card">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-content">
                                <h6>اسم العميل</h6>
                                <p><?php echo htmlspecialchars($customer['name']); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-content">
                                <h6>رقم الهاتف</h6>
                                <p><?php echo htmlspecialchars($customer['phone']); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <h6>البريد الإلكتروني</h6>
                                <p><?php echo htmlspecialchars($customer['email'] ?? 'غير محدد'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="info-content">
                                <h6>رقم الحساب</h6>
                                <p><?php echo htmlspecialchars($customer['account_number'] ?? 'غير محدد'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <h6>العنوان</h6>
                                <p>
                                    <?php echo htmlspecialchars($customer['address'] ?? 'غير محدد'); ?>
                                    <?php if ($customer['governorate_name']): ?>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($customer['governorate_name']); ?>
                                            <?php if ($customer['area_name']): ?>
                                                - <?php echo htmlspecialchars($customer['area_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="info-content">
                                <h6>تاريخ التسجيل</h6>
                                <p><?php echo date('Y-m-d', strtotime($customer['date_created'] ?? 'now')); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="info-content">
                                <h6>طريقة الدفع المفضلة</h6>
                                <p><?php echo htmlspecialchars($customer['preferred_payment_method'] ?? 'نقداً'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="info-content">
                                <h6>مستوى المخاطر</h6>
                                <p>
                                    <?php 
                                    $risk_level = $customer['risk_level'] ?? 'low';
                                    $risk_text = [
                                        'low' => 'منخفض',
                                        'medium' => 'متوسط',
                                        'high' => 'عالي'
                                    ];
                                    echo $risk_text[$risk_level] ?? 'غير محدد';
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- الرصيد الحالي -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="balance-display <?php echo $current_balance > 0 ? 'balance-positive' : ($current_balance < 0 ? 'balance-negative' : 'balance-zero'); ?>">
                <i class="fas fa-wallet mr-3"></i>
                الرصيد الحالي: 
                <strong><?php echo number_format($current_balance, 2); ?> جنيه</strong>
                <?php if ($current_balance > 0): ?>
                    <br><small>مستحق للعميل</small>
                <?php elseif ($current_balance < 0): ?>
                    <br><small>مدين للشركة</small>
                <?php else: ?>
                    <br><small>متوازن</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- معلومات الحساب والتصنيفات -->
    <div class="row mb-4">
        <!-- معلومات الحساب -->
        <div class="col-md-6">
            <div class="card card-outline-primary">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-credit-card text-primary mr-2"></i>
                        معلومات الحساب
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="info-item-small">
                                <label>حد الائتمان:</label>
                                <span class="badge badge-info"><?php echo number_format($customer['credit_limit'] ?? 0, 2); ?> جنيه</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-item-small">
                                <label>حالة الحساب:</label>
                                <span class="badge badge-<?php echo ($customer['account_status'] ?? 'active') === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo ($customer['account_status'] ?? 'active') === 'active' ? 'نشط' : 'معلق'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <div class="info-item-small">
                                <label>أيام تذكير الدفع:</label>
                                <span class="badge badge-secondary"><?php echo $customer['payment_reminder_days'] ?? 3; ?> أيام</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-item-small">
                                <label>الحد الأدنى للدفع:</label>
                                <span class="badge badge-warning"><?php echo number_format($customer['minimum_payment_amount'] ?? 0, 2); ?> جنيه</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- التصنيفات -->
        <div class="col-md-6">
            <div class="card card-outline-primary">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-star text-warning mr-2"></i>
                        تقييم العميل
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="info-item-small">
                                <label>موثوقية الدفع:</label>
                                <div class="rating-stars">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= ($customer['payment_reliability'] ?? 5) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-item-small">
                                <label>حجم التعاملات:</label>
                                <div class="rating-stars">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= ($customer['volume_rating'] ?? 3) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <div class="info-item-small">
                                <label>التعاون:</label>
                                <div class="rating-stars">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= ($customer['cooperation_rating'] ?? 5) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-item-small">
                                <label>التقييم العام:</label>
                                <span class="badge badge-<?php 
                                    $overall = $customer['overall_rating'] ?? 0;
                                    if ($overall >= 4) echo 'success';
                                    elseif ($overall >= 3) echo 'warning';
                                    else echo 'danger';
                                ?>">
                                    <?php echo number_format($overall, 1); ?>/5
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- آخر المعاملات المالية والأنشطة -->
    <div class="row mb-4">
        <!-- آخر المعاملات المالية -->
        <div class="col-md-6">
            <div class="card card-outline-primary">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-exchange-alt text-primary mr-2"></i>
                        آخر المعاملات المالية
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_transactions)): ?>
                        <p class="text-muted text-center">لا توجد معاملات مالية حديثة</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>النوع</th>
                                        <th>المبلغ</th>
                                        <th>الرصيد بعد</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php echo $transaction['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                                    <?php echo $transaction['type'] === 'credit' ? 'إيداع' : 'سحب'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($transaction['amount'], 2); ?> جنيه</td>
                                            <td><?php echo number_format($transaction['balance_after'], 2); ?> جنيه</td>
                                            <td><?php echo date('Y-m-d', strtotime($transaction['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- آخر الأنشطة -->
        <div class="col-md-6">
            <div class="card card-outline-primary">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-history text-info mr-2"></i>
                        آخر الأنشطة
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-muted text-center">لا توجد أنشطة حديثة</p>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-circle text-primary"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?php echo htmlspecialchars($activity['activity_type'] ?? 'نشاط'); ?></div>
                                        <div class="activity-description"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                        <div class="activity-meta">
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d H:i', strtotime($activity['created_at'])); ?>
                                                <?php if ($activity['ip_address']): ?>
                                                    - IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- الإحصائيات -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_shipments'] ?? 0; ?></div>
                <div class="stat-label">إجمالي الشحنات</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['delivered_shipments'] ?? 0; ?></div>
                <div class="stat-label">الشحنات المسلمة</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo $stats['partial_paid_shipments'] ?? 0; ?></div>
                <div class="stat-label">الشحنات الجزئية</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-undo"></i>
                </div>
                <div class="stat-value"><?php echo $stats['returned_shipments'] ?? 0; ?></div>
                <div class="stat-label">الشحنات المرتجعة</div>
            </div>
        </div>
    </div>

    <!-- تفاصيل الشحنات -->
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-alt mr-2"></i>
                        تفاصيل الشحنات
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Tabs للشحنات -->
                    <ul class="nav nav-tabs" id="shipmentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="delivered-tab" data-toggle="tab" href="#delivered" role="tab">
                                <i class="fas fa-check-circle text-success mr-2"></i>
                                الشحنات المسلمة
                                <span class="badge badge-success ml-2"><?php echo $stats['delivered_shipments'] ?? 0; ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="partial-tab" data-toggle="tab" href="#partial" role="tab">
                                <i class="fas fa-money-bill-wave text-warning mr-2"></i>
                                الشحنات الجزئية
                                <span class="badge badge-warning ml-2"><?php echo $stats['partial_paid_shipments'] ?? 0; ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="returned-tab" data-toggle="tab" href="#returned" role="tab">
                                <i class="fas fa-undo text-danger mr-2"></i>
                                الشحنات المرتجعة
                                <span class="badge badge-danger ml-2"><?php echo $stats['returned_shipments'] ?? 0; ?></span>
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="shipmentTabsContent">
                        <!-- الشحنات المسلمة -->
                        <div class="tab-pane active" id="delivered" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="deliveredTable">
                                    <thead>
                                        <tr>
                                            <th>رقم التتبع</th>
                                            <th>المستلم</th>
                                            <th>التاريخ</th>
                                            <th>القيمة</th>
                                            <th>صافي المستحق</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- سيتم تحميل البيانات عبر AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- الشحنات الجزئية -->
                        <div class="tab-pane" id="partial" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="partialTable">
                                    <thead>
                                        <tr>
                                            <th>رقم التتبع</th>
                                            <th>المستلم</th>
                                            <th>التاريخ</th>
                                            <th>القيمة</th>
                                            <th>المبلغ المدفوع</th>
                                            <th>المبلغ المتبقي</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- سيتم تحميل البيانات عبر AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- الشحنات المرتجعة -->
                        <div class="tab-pane" id="returned" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="returnedTable">
                                    <thead>
                                        <tr>
                                            <th>رقم التتبع</th>
                                            <th>المستلم</th>
                                            <th>التاريخ</th>
                                            <th>القيمة</th>
                                            <th>سبب الإرجاع</th>
                                            <th>قيمة الإرجاع</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- سيتم تحميل البيانات عبر AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal تأكيد الدفع -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card text-primary me-2"></i>
                    تأكيد الدفع
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">رقم الشحنة:</label>
                            <input type="text" class="form-control" id="paymentParcelId" readonly>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">رقم التتبع:</label>
                            <input type="text" class="form-control" id="paymentTrackingNumber" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">المبلغ المستحق:</label>
                            <input type="text" class="form-control" id="paymentAmount" readonly>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">المبلغ المدفوع:</label>
                            <input type="number" class="form-control" id="paymentCollectedAmount" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">ملاحظات:</label>
                    <textarea class="form-control" id="paymentNotes" rows="3" placeholder="أضف ملاحظات حول الدفع..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-2"></i>إلغاء
                </button>
                <button type="button" class="btn btn-primary" id="confirmPaymentBtn">
                    <i class="fas fa-check me-2"></i>تأكيد الدفع
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    // تهيئة DataTables
    const deliveredTable = $('#deliveredTable').DataTable({
        language: {
            url: './js/Arabic.json'
        },
        pageLength: 10,
        order: [[2, 'desc']],
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "الكل"]]
    });
    
    const partialTable = $('#partialTable').DataTable({
        language: {
            url: './js/Arabic.json'
        },
        pageLength: 10,
        order: [[2, 'desc']],
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "الكل"]]
    });
    
    const returnedTable = $('#returnedTable').DataTable({
        language: {
            url: './js/Arabic.json'
        },
        pageLength: 10,
        order: [[2, 'desc']],
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "الكل"]]
    });
    
    // تحميل البيانات
    loadShipmentData();
    
    // معالج تأكيد الدفع
    $('#confirmPaymentBtn').click(function() {
        const parcelId = $('#paymentParcelId').val();
        const amount = parseFloat($('#paymentCollectedAmount').val());
        const notes = $('#paymentNotes').val();
        
        if (!amount || amount <= 0) {
            alert('يرجى إدخال مبلغ صحيح');
            return;
        }
        
        // إرسال طلب الدفع
        $.ajax({
            url: 'process_customer_payment.php',
            method: 'POST',
            data: {
                action: 'process_payment',
                parcel_id: parcelId,
                amount: amount,
                notes: notes
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('تم تسجيل الدفع بنجاح!');
                    $('#paymentModal').modal('hide');
                    loadShipmentData(); // إعادة تحميل البيانات
                } else {
                    alert('خطأ: ' + (response.message || 'حدث خطأ أثناء تسجيل الدفع'));
                }
            },
            error: function() {
                alert('حدث خطأ في الاتصال بالخادم');
            }
        });
    });
    
    // دالة تحميل بيانات الشحنات
    function loadShipmentData() {
        // تحميل الشحنات المسلمة
        $.ajax({
            url: 'customer_profile_ajax.php',
            method: 'POST',
            data: {
                action: 'get_delivered_shipments',
                customer_id: <?php echo $customer_id; ?>
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    deliveredTable.clear();
                    response.data.forEach(function(shipment) {
                        deliveredTable.row.add([
                            shipment.tracking_number,
                            shipment.recipient_name,
                            shipment.delivery_date,
                            shipment.cod_amount + ' جنيه',
                            shipment.net_for_client + ' جنيه',
                            `<button class="btn btn-success btn-sm" onclick="showPaymentModal(${shipment.id}, '${shipment.tracking_number}', ${shipment.remaining_amount})">
                                <i class="fas fa-credit-card mr-2"></i>دفع
                            </button>`
                        ]);
                    });
                    deliveredTable.draw();
                }
            }
        });
        
        // تحميل الشحنات الجزئية
        $.ajax({
            url: 'customer_profile_ajax.php',
            method: 'POST',
            data: {
                action: 'get_partial_shipments',
                customer_id: <?php echo $customer_id; ?>
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    partialTable.clear();
                    response.data.forEach(function(shipment) {
                        partialTable.row.add([
                            shipment.tracking_number,
                            shipment.recipient_name,
                            shipment.delivery_date,
                            shipment.cod_amount + ' جنيه',
                            shipment.paid_amount + ' جنيه',
                            shipment.remaining_amount + ' جنيه',
                            `<button class="btn btn-warning btn-sm" onclick="showPaymentModal(${shipment.id}, '${shipment.tracking_number}', ${shipment.remaining_amount})">
                                <i class="fas fa-credit-card mr-2"></i>دفع المتبقي
                            </button>`
                        ]);
                    });
                    partialTable.draw();
                }
            }
        });
        
        // تحميل الشحنات المرتجعة
        $.ajax({
            url: 'customer_profile_ajax.php',
            method: 'POST',
            data: {
                action: 'get_returned_shipments',
                customer_id: <?php echo $customer_id; ?>
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    returnedTable.clear();
                    response.data.forEach(function(shipment) {
                        returnedTable.row.add([
                            shipment.tracking_number,
                            shipment.recipient_name,
                            shipment.return_date,
                            shipment.cod_amount + ' جنيه',
                            shipment.return_reason || 'غير محدد',
                            shipment.return_value + ' جنيه'
                        ]);
                    });
                    returnedTable.draw();
                }
            }
        });
    }
});

// دالة عرض modal الدفع
function showPaymentModal(parcelId, trackingNumber, amount) {
    $('#paymentParcelId').val(parcelId);
    $('#paymentTrackingNumber').val(trackingNumber);
    $('#paymentAmount').val(amount + ' جنيه');
    $('#paymentCollectedAmount').val(amount);
    $('#paymentNotes').val('');
    $('#paymentModal').modal('show');
}
</script>

</body>
</html>
