<?php
// agent_finance.php - صفحة إدارة الحسابات المالية مع الوكلاء

// تفعيل عرض الأخطاء للمساعدة في تصحيح أي مشاكل
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// التحقق من ID الوكيل من الرابط أو الجلسة
$agent_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['login_id']) ? intval($_SESSION['login_id']) : 0);

// إذا لم يتم العثور على ID صالح، يتم إيقاف الصفحة برسالة خطأ واضحة
if ($agent_id == 0) {
    echo '<div class="container mt-5"><div class="alert alert-danger text-center">رقم الوكيل غير صحيح.</div></div>';
    exit;
}

// جلب بيانات الوكيل للتأكد من وجوده
$agent_qry = $conn->query("SELECT * FROM agents WHERE id = $agent_id");
if ($agent_qry->num_rows > 0) {
    $agent_data = $agent_qry->fetch_assoc();
} else {
    echo '<div class="container mt-5"><div class="alert alert-danger text-center">الوكيل غير موجود.</div></div>';
    exit;
}

// معالجة تأكيد الدفع للشحنات المرسلة للوكيل (استلام مدفوعات)
if (isset($_POST['confirm_to_agent_payment'])) {
    $selected_shipments = isset($_POST['selected_to_agent_shipments']) ? $_POST['selected_to_agent_shipments'] : [];
    
    if (!empty($selected_shipments)) {
        // إنشاء رقم العملية
        $transaction_number = 'TXN' . date('YmdHis') . rand(1000, 9999);
        
        // حساب المبلغ الإجمالي
        $shipment_ids = implode(',', array_map('intval', $selected_shipments));
        $amount_query = "SELECT SUM(cod_amount) as total_amount FROM parcels WHERE id IN ($shipment_ids)";
        $amount_result = $conn->query($amount_query);
        $total_amount = $amount_result->fetch_assoc()['total_amount'];
        
        // تحديث حالة الدفع إلى "مدفوع" وحالة الشحنة إلى "تم الدفع بنجاح"
        $update_query = "UPDATE parcels SET status = 5, payment_status = 'paid' WHERE id IN ($shipment_ids) AND agent_id = $agent_id";
        $conn->query($update_query);
        
        // التحقق من وجود جدول payments_log قبل التسجيل
        $check_table = $conn->query("SHOW TABLES LIKE 'payments_log'");
        if ($check_table->num_rows > 0) {
            // تسجيل عملية الدفع في جدول payments_log
            $shipments_list = implode(',', $selected_shipments);
            $user_id = isset($_SESSION['login_id']) ? $_SESSION['login_id'] : 1;
            $payment_date = date('Y-m-d H:i:s');
            
            $insert_query = "INSERT INTO payments_log (transaction_number, agent_id, shipments, amount, payment_date, user_id) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('sisdsi', $transaction_number, $agent_id, $shipments_list, $total_amount, $payment_date, $user_id);
            $stmt->execute();
        }
        
        $success_message = "تم تأكيد استلام المدفوعات من الوكيل بنجاح. رقم العملية: $transaction_number";
    }
}

// معالجة تأكيد الدفع للشحنات المستلمة من الوكيل (دفع للوكيل)
if (isset($_POST['confirm_from_agent_payment'])) {
    $selected_shipments = isset($_POST['selected_from_agent_shipments']) ? $_POST['selected_from_agent_shipments'] : [];
    
    if (!empty($selected_shipments)) {
        // إنشاء رقم العملية
        $transaction_number = 'TXN' . date('YmdHis') . rand(1000, 9999);
        
        // حساب المبلغ الإجمالي (صافي للوكيل)
        $shipment_ids = implode(',', array_map('intval', $selected_shipments));
        $amount_query = "SELECT SUM(cod_amount - delivery_agent_fee) as total_amount FROM parcels WHERE id IN ($shipment_ids)";
        $amount_result = $conn->query($amount_query);
        $total_amount = $amount_result->fetch_assoc()['total_amount'];
        
        // تحديث حالة الدفع إلى "مدفوع" وحالة الشحنة إلى "تم الدفع بنجاح"
        $update_query = "UPDATE parcels SET status = 5, payment_status = 'paid' WHERE id IN ($shipment_ids) AND agent_id = $agent_id";
        $conn->query($update_query);
        
        // التحقق من وجود جدول payments_log قبل التسجيل
        $check_table = $conn->query("SHOW TABLES LIKE 'payments_log'");
        if ($check_table->num_rows > 0) {
            // تسجيل عملية الدفع في جدول payments_log
            $shipments_list = implode(',', $selected_shipments);
            $user_id = isset($_SESSION['login_id']) ? $_SESSION['login_id'] : 1;
            $payment_date = date('Y-m-d H:i:s');
            
            $insert_query = "INSERT INTO payments_log (transaction_number, agent_id, shipments, amount, payment_date, user_id) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('sisdsi', $transaction_number, $agent_id, $shipments_list, $total_amount, $payment_date, $user_id);
            $stmt->execute();
        }
        
        $success_message = "تم تأكيد الدفع للوكيل بنجاح. رقم العملية: $transaction_number";
    }
}

// جلب البيانات المالية للوكيل
function getAgentFinancialData($conn, $agent_id) {
    $financial_data = [
        // شحنات مرسلة للوكيل
        'to_agent_shipments' => 0,
        'to_agent_delivered' => 0,
        'to_agent_total_cod' => 0,
        'to_agent_shipping_fees' => 0,
        'to_agent_agent_cost' => 0,
        'to_agent_company_profit' => 0,
        'to_agent_unpaid' => 0,
        
        // شحنات مستلمة من الوكيل
        'from_agent_shipments' => 0,
        'from_agent_delivered' => 0,
        'from_agent_total_cod' => 0,
        'from_agent_courier_fees' => 0,
        'from_agent_company_profit' => 0,
        'from_agent_agent_due' => 0,
        'from_agent_unpaid' => 0,
        
        // الإجماليات
        'total_shipments' => 0,
        'total_company_profit' => 0,
        'net_balance' => 0
    ];
    
    // بيانات الشحنات المرسلة للوكيل (to_agent)
    $to_agent_query = "SELECT 
                        COUNT(*) as total_shipments,
                        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered_shipments,
                        SUM(cod_amount) as total_cod_amount,
                        SUM(shipping_fees) as total_shipping_fees,
                        SUM(agent_share) as total_agent_cost,
                        SUM(company_profit) as total_company_profit,
                        SUM(CASE WHEN status = 4 AND payment_status IN ('unpaid', 'partially_paid') THEN cod_amount ELSE 0 END) as unpaid_amount
                    FROM parcels 
                    WHERE agent_id = ? AND shipment_direction = 'to_agent'";
    
    $stmt = $conn->prepare($to_agent_query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $to_agent_result = $stmt->get_result();
    $to_agent_stats = $to_agent_result->fetch_assoc();
    
    $financial_data['to_agent_shipments'] = $to_agent_stats['total_shipments'] ?? 0;
    $financial_data['to_agent_delivered'] = $to_agent_stats['delivered_shipments'] ?? 0;
    $financial_data['to_agent_total_cod'] = $to_agent_stats['total_cod_amount'] ?? 0;
    $financial_data['to_agent_shipping_fees'] = $to_agent_stats['total_shipping_fees'] ?? 0;
    $financial_data['to_agent_agent_cost'] = $to_agent_stats['total_agent_cost'] ?? 0;
    $financial_data['to_agent_company_profit'] = $to_agent_stats['total_company_profit'] ?? 0;
    $financial_data['to_agent_unpaid'] = $to_agent_stats['unpaid_amount'] ?? 0;
    
    // بيانات الشحنات المستلمة من الوكيل (from_agent)
    $from_agent_query = "SELECT 
                        COUNT(*) as total_shipments,
                        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered_shipments,
                        SUM(cod_amount) as total_cod_amount,
                        SUM(delivery_agent_fee) as total_courier_fees,
                        SUM(CASE WHEN status = 4 THEN (cod_amount - delivery_agent_fee) ELSE 0 END) as agent_due_amount,
                        SUM(CASE WHEN status = 4 AND payment_status IN ('unpaid', 'partially_paid') THEN cod_amount ELSE 0 END) as unpaid_amount
                    FROM parcels 
                    WHERE agent_id = ? AND shipment_direction = 'from_agent'";
    
    $stmt = $conn->prepare($from_agent_query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $from_agent_result = $stmt->get_result();
    $from_agent_stats = $from_agent_result->fetch_assoc();
    
    $financial_data['from_agent_shipments'] = $from_agent_stats['total_shipments'] ?? 0;
    $financial_data['from_agent_delivered'] = $from_agent_stats['delivered_shipments'] ?? 0;
    $financial_data['from_agent_total_cod'] = $from_agent_stats['total_cod_amount'] ?? 0;
    $financial_data['from_agent_courier_fees'] = $from_agent_stats['total_courier_fees'] ?? 0;
    $financial_data['from_agent_agent_due'] = $from_agent_stats['agent_due_amount'] ?? 0;
    $financial_data['from_agent_unpaid'] = $from_agent_stats['unpaid_amount'] ?? 0;
    
    // ربح الشركة من الشحنات المستلمة (3 ج.م لكل شحنة مسلمة)
    $financial_data['from_agent_company_profit'] = $financial_data['from_agent_delivered'] * 3;
    
    // الإجماليات
    $financial_data['total_shipments'] = $financial_data['to_agent_shipments'] + $financial_data['from_agent_shipments'];
    $financial_data['total_company_profit'] = $financial_data['to_agent_company_profit'] + $financial_data['from_agent_company_profit'];
    
    // صافي الرصيد = المال المستحق من الوكيل - المال المستحق للوكيل
    $financial_data['net_balance'] = $financial_data['to_agent_unpaid'] - $financial_data['from_agent_agent_due'];
    
    return $financial_data;
}

// جلب الشحنات الجاهزة للدفع (الشحنات المرسلة للوكيل والمسلمة بنجاح)
function getUnpaidToAgentShipments($conn, $agent_id) {
    $query = "SELECT p.*, ar.name as recipient_area_name, g.name as governorate_name
              FROM parcels p 
              LEFT JOIN areas ar ON p.recipient_area_id = ar.id 
              LEFT JOIN governorates g ON p.recipient_governorate_id = g.id
              WHERE p.agent_id = ? AND p.shipment_direction = 'to_agent' AND p.status = 4 AND p.payment_status IN ('unpaid', 'partially_paid')
              ORDER BY p.date_created DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    return $stmt->get_result();
}

// جلب الشحنات التي تحتاج دفع للوكيل (الشحنات المستلمة من الوكيل والمسلمة بنجاح)
function getUnpaidFromAgentShipments($conn, $agent_id) {
    $query = "SELECT p.*, ar.name as recipient_area_name, g.name as governorate_name, c.name as courier_name
              FROM parcels p 
              LEFT JOIN areas ar ON p.recipient_area_id = ar.id 
              LEFT JOIN governorates g ON p.recipient_governorate_id = g.id
              LEFT JOIN couriers c ON p.courier_id = c.id
              WHERE p.agent_id = ? AND p.shipment_direction = 'from_agent' AND p.status = 4 AND p.payment_status IN ('unpaid', 'partially_paid')
              ORDER BY p.date_created DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    return $stmt->get_result();
}

// جلب سجل المدفوعات مع تفاصيل الشحنات
function getPaymentLog($conn, $agent_id) {
    // التحقق من وجود جدول payments_log
    $check_table = $conn->query("SHOW TABLES LIKE 'payments_log'");
    if ($check_table->num_rows == 0) {
        // إذا لم يكن الجدول موجود، إرجاع نتيجة فارغة
        return null;
    }
    
    $query = "SELECT pl.*, a.company_name as agent_name, CONCAT(u.firstname, ' ', u.lastname) as user_name
              FROM payments_log pl
              LEFT JOIN agents a ON pl.agent_id = a.id
              LEFT JOIN users u ON pl.user_id = u.id
              WHERE pl.agent_id = ?
              ORDER BY pl.payment_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // إضافة تفاصيل الشحنات لكل عملية دفع
    $payments = [];
    while ($payment = $result->fetch_assoc()) {
        if (!empty($payment['shipments'])) {
            $shipment_ids = $payment['shipments'];
            $shipments_query = "SELECT id, reference_number, recipient_name 
                               FROM parcels 
                               WHERE id IN ($shipment_ids)";
            $shipments_result = $conn->query($shipments_query);
            
            $payment_shipments = [];
            while ($shipment = $shipments_result->fetch_assoc()) {
                $payment_shipments[] = $shipment;
            }
            $payment['shipment_details'] = $payment_shipments;
        } else {
            $payment['shipment_details'] = [];
        }
        $payments[] = $payment;
    }
    
    return $payments;
}

// جلب الشحنات المرسلة للوكيل
function getToAgentShipments($conn, $agent_id) {
    $query = "SELECT p.*, ar.name as recipient_area_name, g.name as governorate_name
              FROM parcels p 
              LEFT JOIN areas ar ON p.recipient_area_id = ar.id 
              LEFT JOIN governorates g ON p.recipient_governorate_id = g.id
              WHERE p.agent_id = ? AND p.shipment_direction = 'to_agent'
              ORDER BY p.date_created DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    return $stmt->get_result();
}

// جلب الشحنات المستلمة من الوكيل
function getFromAgentShipments($conn, $agent_id) {
    $query = "SELECT p.*, ar.name as recipient_area_name, g.name as governorate_name, c.name as courier_name
              FROM parcels p 
              LEFT JOIN areas ar ON p.recipient_area_id = ar.id 
              LEFT JOIN governorates g ON p.recipient_governorate_id = g.id
              LEFT JOIN couriers c ON p.courier_id = c.id
              WHERE p.agent_id = ? AND p.shipment_direction = 'from_agent'
              ORDER BY p.date_created DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    return $stmt->get_result();
}

// جلب البيانات المالية
$financial_data = getAgentFinancialData($conn, $agent_id);
$unpaid_to_agent_shipments = getUnpaidToAgentShipments($conn, $agent_id);
$unpaid_from_agent_shipments = getUnpaidFromAgentShipments($conn, $agent_id);
$payment_log = getPaymentLog($conn, $agent_id);
$to_agent_shipments = getToAgentShipments($conn, $agent_id);
$from_agent_shipments = getFromAgentShipments($conn, $agent_id);

// تعريف مصفوفة حالات الشحنات
$status_arr = array(
    1  => 'قيد التنفيذ',
    2  => 'تم تسليمها للمندوب',
    3  => 'جاري التوصيل',
    4  => 'تم التسليم بنجاح',
    5  => 'تم الدفع بنجاح',
    6  => 'تم الدفع جزئي',
    7  => 'تم تأجيل الطلب',
    8  => 'تم الرفض',
    9  => 'المرتجعات',
    10 => 'مرتجع للمخزن',
    11 => 'مرتجع للفرع',
    12 => 'مرتجع للعميل'
);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الحسابات المالية - <?php echo htmlspecialchars($agent_data['company_name'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap">
    <style>
        body { 
            font-family: 'Tajawal', Arial, sans-serif; 
            direction: rtl; 
            background-color: #f4f7f6; 
        }
        .card { 
            border-radius: 1rem; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            border: none; 
            margin-bottom: 1.5rem;
        }
        .card-header { 
            background-color: #17a2b8; 
            color: white; 
            border-radius: 1rem 1rem 0 0; 
            padding: 1.5rem; 
            text-align: center; 
        }
        .table thead th { 
            background-color: #17a2b8; 
            color: #fff; 
            border-color: #17a2b8; 
        }
        .table tbody tr:hover { background-color: #e9ecef; }
        .form-control, .btn { border-radius: 0.5rem; }
        .btn-info { background-color: #17a2b8; border-color: #17a2b8; }
        .btn-info:hover { background-color: #138496; border-color: #138496; }
        .status-badge { 
            font-size: 0.8rem; 
            padding: 0.4em 0.8em; 
            border-radius: 50rem; 
            font-weight: bold; 
        }
        
        /* ألوان مخصصة لكل حالة */
        .status-1 { background-color: #6c757d; color: #fff; }
        .status-2 { background-color: #ffc107; color: #333; }
        .status-3 { background-color: #0d6efd; color: #fff; }
        .status-4 { background-color: #28a745; color: #fff; }
        .status-5 { background-color: #17a2b8; color: #fff; }
        .status-6 { background-color: #fd7e14; color: #fff; }
        .status-7 { background-color: #6f42c1; color: #fff; }
        .status-8 { background-color: #dc3545; color: #fff; }
        .status-9 { background-color: #007bff; color: #fff; }
        .status-10 { background-color: #6610f2; color: #fff; }
        .status-11 { background-color: #20c997; color: #fff; }
        .status-12 { background-color: #e83e8c; color: #fff; }
        
        .stats-card {
            background-color: #17a2b8;
            color: white;
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
        }
        .stats-card p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        .amount-display {
            font-size: 1.2rem;
            font-weight: bold;
            color: #495057;
        }
        .profit-positive { color: #28a745; }
        .profit-negative { color: #dc3545; }
        
        .btn {
            border-radius: 0.5rem;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #333;
        }
        
        .card {
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: none;
        }
        .card-header {
            background-color: #17a2b8;
            color: white;
            border-radius: 1rem 1rem 0 0;
            padding: 1.5rem;
            text-align: center;
        }
        
        .table thead th {
            background-color: #17a2b8;
            color: #fff;
            border-color: #17a2b8;
        }
        .table tbody tr:hover {
            background-color: #e9ecef;
        }
        
        .shipment-details {
            max-width: 200px;
        }
        
        .shipment-details .mb-1 {
            padding: 2px 0;
        }
        
        .shipment-details hr {
            margin: 3px 0;
            opacity: 0.3;
        }

        
        .action-buttons .btn { margin-left: 5px; }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .card-body { padding: 15px; }
            .stats-card { padding: 1rem; }
            .stats-card h3 { font-size: 1.5rem; }
            .table-responsive { font-size: 12px; }
            .table th, .table td { padding: 6px 4px; }
            .btn { font-size: 12px; padding: 6px 12px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- عنوان الصفحة -->
        <div class="row mb-4">
            <div class="col-12">
    <div class="card">
        <div class="card-header">
                        <h2 class="mb-0">
                            <i class="fas fa-calculator me-2"></i>
                            إدارة الحسابات المالية - <?php echo htmlspecialchars($agent_data['company_name'] ?? ''); ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- البطاقات الإحصائية الرئيسية -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo number_format($financial_data['total_shipments']); ?></h3>
                    <p>إجمالي الشحنات</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo number_format($financial_data['to_agent_shipments']); ?></h3>
                    <p>شحنات مرسلة للوكيل</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo number_format($financial_data['from_agent_shipments']); ?></h3>
                    <p>شحنات مستلمة من الوكيل</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo number_format($financial_data['total_company_profit'], 2); ?> ج.م</h3>
                    <p>إجمالي أرباح الشركة</p>
                </div>
            </div>
        </div>

                <!-- صافي الرصيد -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card" data-bs-toggle="tooltip" data-bs-placement="top" 
                     title="صافي الرصيد: موجب = الوكيل مدين لك | سالب = أنت مدين للوكيل">
                    <div class="card-header">
                        <h5 class="mb-0">صافي الرصيد مع الوكيل</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="amount-display <?php echo $financial_data['net_balance'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo number_format($financial_data['net_balance'], 2); ?> ج.م
                        </div>
                        <small class="text-muted">
                            <?php if ($financial_data['net_balance'] > 0): ?>
                                الوكيل مدين لك بهذا المبلغ
                            <?php elseif ($financial_data['net_balance'] < 0): ?>
                                أنت مدين للوكيل بمبلغ <?php echo number_format(abs($financial_data['net_balance']), 2); ?> ج.م
                            <?php else: ?>
                                لا توجد مديونية متبادلة
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- تفاصيل الشحنات المرسلة والمستلمة -->
        <div class="row mb-4">
                        <div class="col-md-6">
    <div class="card">
        <div class="card-header">
                        <h5 class="mb-0">الشحنات المرسلة للوكيل</h5>
        </div>
        <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <h6>إجمالي التحصيل</h6>
                                    <div class="amount-display"><?php echo number_format($financial_data['to_agent_total_cod'], 2); ?> ج.م</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <h6>غير مدفوع</h6>
                                    <div class="amount-display profit-negative"><?php echo number_format($financial_data['to_agent_unpaid'], 2); ?> ج.م</div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <h6>رسوم الشحن</h6>
                                    <div class="amount-display"><?php echo number_format($financial_data['to_agent_shipping_fees'], 2); ?> ج.م</div>
                    </div>
                </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <h6>ربح الشركة</h6>
                                    <div class="amount-display profit-positive"><?php echo number_format($financial_data['to_agent_company_profit'], 2); ?> ج.م</div>
                                </div>
                    </div>
                </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">الشحنات المستلمة من الوكيل</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <h6>إجمالي التحصيل</h6>
                                    <div class="amount-display"><?php echo number_format($financial_data['from_agent_total_cod'], 2); ?> ج.م</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <h6>مستحق للوكيل</h6>
                                    <div class="amount-display profit-negative"><?php echo number_format($financial_data['from_agent_agent_due'], 2); ?> ج.م</div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <h6>عمولة المناديب</h6>
                                    <div class="amount-display"><?php echo number_format($financial_data['from_agent_courier_fees'], 2); ?> ج.م</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <h6>ربح الشركة</h6>
                                    <div class="amount-display profit-positive"><?php echo number_format($financial_data['from_agent_company_profit'], 2); ?> ج.م</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            
        <!-- التبويبات -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="financeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="to-agent-tab" data-bs-toggle="tab" data-bs-target="#to-agent" type="button" role="tab">
                                    شحنات مرسلة للوكيل
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="from-agent-tab" data-bs-toggle="tab" data-bs-target="#from-agent" type="button" role="tab">
                                    شحنات مستلمة من الوكيل
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">
                                    إدارة المدفوعات
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="log-tab" data-bs-toggle="tab" data-bs-target="#log" type="button" role="tab">
                                    سجل المدفوعات
                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="financeTabsContent">
                            <!-- تبويب الشحنات المرسلة للوكيل -->
                            <div class="tab-pane fade show active" id="to-agent" role="tabpanel">
                                <h5 class="mb-3">
                                    <i class="fas fa-arrow-right text-primary me-2"></i>
                                    الشحنات المرسلة للوكيل
                                </h5>
            <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                                                <th>رقم التتبع</th>
                                                <th>الراسل</th>
                                                <th>المستلم</th>
                                                <th>المنطقة</th>
                                                <th>مبلغ التحصيل</th>
                                                <th>رسوم الشحن</th>
                                                <th>نصيب الوكيل</th>
                                                <th>ربح الشركة</th>
                                                <th>الحالة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                                            <?php if ($to_agent_shipments && $to_agent_shipments->num_rows > 0): ?>
                                                <?php while ($shipment = $to_agent_shipments->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($shipment['tracking_number'] ?? ''); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($shipment['sender_name'] ?? ''); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($shipment['sender_phone'] ?? ''); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($shipment['recipient_name'] ?? ''); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($shipment['recipient_phone'] ?? ''); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($shipment['recipient_area_name'] ?? 'غير محدد'); ?></td>
                                                <td><?php echo number_format($shipment['cod_amount'] ?? 0, 2); ?> ج.م</td>
                                                <td><?php echo number_format($shipment['shipping_fees'] ?? 0, 2); ?> ج.م</td>
                                                <td><?php echo number_format($shipment['agent_share'] ?? 0, 2); ?> ج.م</td>
                                                <td class="<?php echo ($shipment['company_profit'] ?? 0) > 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo number_format($shipment['company_profit'] ?? 0, 2); ?> ج.م
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $shipment['status']; ?>">
                                                        <?php echo $status_arr[$shipment['status']] ?? 'غير محدد'; ?>
                                                    </span>
                            </td>
                                                <td><?php echo date('Y-m-d', strtotime($shipment['date_created'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center">لا توجد شحنات مرسلة للوكيل</td>
                                                </tr>
                                            <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
            
                            <!-- تبويب الشحنات المستلمة من الوكيل -->
                            <div class="tab-pane fade" id="from-agent" role="tabpanel">
                                <h5 class="mb-3">
                                    <i class="fas fa-arrow-left text-primary me-2"></i>
                                    الشحنات المستلمة من الوكيل
                                </h5>
            <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                                                <th>رقم التتبع</th>
                                                <th>الراسل</th>
                                                <th>المستلم</th>
                                                <th>المنطقة</th>
                                                <th>المندوب</th>
                                                <th>مبلغ التحصيل</th>
                                                <th>عمولة المندوب</th>
                                                <th>المبلغ الصافي للعملية (المدفوع للوكيل)</th>
                                                <th>ربح الشركة</th>
                                                <th>الحالة</th>
                            <th>التاريخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($from_agent_shipments && $from_agent_shipments->num_rows > 0): ?>
                                                <?php while ($shipment = $from_agent_shipments->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($shipment['tracking_number'] ?? ''); ?></td>
                                                        <td>
                                                            <?php echo htmlspecialchars($shipment['sender_name'] ?? ''); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($shipment['sender_phone'] ?? ''); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($shipment['recipient_name'] ?? ''); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($shipment['recipient_phone'] ?? ''); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($shipment['recipient_area_name'] ?? 'غير محدد'); ?></td>
                                                        <td><?php echo htmlspecialchars($shipment['courier_name'] ?? 'غير محدد'); ?></td>
                                                        <td><?php echo number_format($shipment['cod_amount'] ?? 0, 2); ?> ج.م</td>
                                                        <td><?php echo number_format($shipment['delivery_agent_fee'] ?? 0, 2); ?> ج.م</td>
                                                        <td class="text-warning">
                                                            <?php echo number_format(($shipment['cod_amount'] ?? 0) - ($shipment['delivery_agent_fee'] ?? 0), 2); ?> ج.م
                                                        </td>
                                                        <td class="text-success">3.00 ج.م</td>
                                                        <td>
                                                            <span class="status-badge status-<?php echo $shipment['status']; ?>">
                                                                <?php echo $status_arr[$shipment['status']] ?? 'غير محدد'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('Y-m-d', strtotime($shipment['date_created'])); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
            <?php else: ?>
                                                <tr>
                                                    <td colspan="11" class="text-center">لا توجد شحنات مستلمة من الوكيل</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- تبويب إدارة المدفوعات -->
                            <div class="tab-pane fade" id="payment" role="tabpanel">
                                <h5 class="mb-3">
                                    <i class="fas fa-credit-card text-warning me-2"></i>
                                    إدارة المدفوعات
                                </h5>
                                
                                <!-- قسم المدفوعات المستحقة من الوكيل -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-arrow-down text-success me-2"></i>
                                            الأموال المستحقة من الوكيل (شحنات مرسلة له)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $has_unpaid_to_agent = $unpaid_to_agent_shipments && $unpaid_to_agent_shipments->num_rows > 0;
                                        ?>
                                        <?php if ($has_unpaid_to_agent): ?>
                                            <p class="text-muted mb-3">اختر الشحنات التي تم تسليمها بنجاح وتريد تأكيد استلام مدفوعاتها من الوكيل:</p>
                                
                                <form method="POST" id="paymentForm">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                                    </th>
                                                    <th>رقم التتبع</th>
                                                    <th>المستلم</th>
                                                    <th>المنطقة</th>
                                                    <th>مبلغ التحصيل</th>
                                                    <th>تاريخ التسليم</th>
                        </tr>
                    </thead>
                    <tbody>
                                                <?php 
                                                $total_unpaid_to_agent = 0;
                                                while ($shipment = $unpaid_to_agent_shipments->fetch_assoc()): 
                                                    $total_unpaid_to_agent += floatval($shipment['cod_amount'] ?? 0);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="selected_to_agent_shipments[]" 
                                                               value="<?php echo $shipment['id']; ?>" 
                                                               class="form-check-input to-agent-checkbox"
                                                               data-amount="<?php echo floatval($shipment['cod_amount'] ?? 0); ?>">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($shipment['tracking_number'] ?? ''); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($shipment['recipient_name'] ?? ''); ?><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($shipment['recipient_phone'] ?? ''); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($shipment['recipient_area_name'] ?? 'غير محدد'); ?></td>
                                                    <td><?php echo number_format($shipment['cod_amount'] ?? 0, 2); ?> ج.م</td>
                                                    <td><?php echo date('Y-m-d', strtotime($shipment['date_created'])); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                            <div class="mt-3">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>إجمالي المبلغ المحدد: <span id="toAgentSelectedTotal" class="text-success">0.00</span> ج.م</h6>
                                                    </div>
                                                    <div class="col-md-6 text-end">
                                        <button type="submit" name="confirm_to_agent_payment" class="btn btn-success" id="confirmToAgentPaymentBtn" disabled>
                                            تأكيد استلام المدفوعات
                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                <?php else: ?>
                                            <div class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle me-2"></i>
                                                لا توجد شحنات جاهزة للدفع من الوكيل
                                            </div>
                                <?php endif; ?>
                                    </div>
                                </div>

                                <!-- قسم المدفوعات المستحقة للوكيل -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-arrow-up text-danger me-2"></i>
                                            الأموال المستحقة للوكيل (شحنات مستلمة منه)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $has_unpaid_from_agent = $unpaid_from_agent_shipments && $unpaid_from_agent_shipments->num_rows > 0;
                                        ?>
                                        <?php if ($has_unpaid_from_agent): ?>
                                            <p class="text-muted mb-3">الشحنات التي تم تسليمها بنجاح ويجب دفع المبلغ الصافي للعملية للوكيل:</p>
                                            
                                            <form method="POST" id="paymentFromAgentForm">
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>
                                                                    <input type="checkbox" id="selectAllFromAgent" class="form-check-input">
                                                                </th>
                                                                <th>رقم التتبع</th>
                                                                <th>المستلم</th>
                                                                <th>المنطقة</th>
                                                                <th>إجمالي التحصيل</th>
                                                                <th>عمولة المندوب</th>
                                                                <th>المبلغ الصافي للعملية (المدفوع للوكيل)</th>
                                                                <th>تاريخ التسليم</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $total_due_to_agent = 0;
                                                            while ($shipment = $unpaid_from_agent_shipments->fetch_assoc()): 
                                                                $net_for_agent = ($shipment['cod_amount'] ?? 0) - ($shipment['delivery_agent_fee'] ?? 0);
                                                                $total_due_to_agent += $net_for_agent;
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <input type="checkbox" name="selected_from_agent_shipments[]" 
                                                                           value="<?php echo $shipment['id']; ?>" 
                                                                           class="form-check-input from-agent-checkbox"
                                                                           data-amount="<?php echo $net_for_agent; ?>">
                                                                </td>
                                                                <td><?php echo htmlspecialchars($shipment['tracking_number'] ?? ''); ?></td>
                                                                <td>
                                                                    <?php echo htmlspecialchars($shipment['recipient_name'] ?? ''); ?><br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($shipment['recipient_phone'] ?? ''); ?></small>
                            </td>
                                                                <td><?php echo htmlspecialchars($shipment['recipient_area_name'] ?? 'غير محدد'); ?></td>
                                                                <td><?php echo number_format($shipment['cod_amount'] ?? 0, 2); ?> ج.م</td>
                                                                <td><?php echo number_format($shipment['delivery_agent_fee'] ?? 0, 2); ?> ج.م</td>
                                                                <td class="text-warning"><?php echo number_format($net_for_agent, 2); ?> ج.م</td>
                                                                <td><?php echo date('Y-m-d', strtotime($shipment['date_created'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
                                                
                                                <div class="mt-3">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>إجمالي المبلغ المحدد: <span id="fromAgentSelectedTotal" class="text-warning">0.00</span> ج.م</h6>
                                                        </div>
                                                        <div class="col-md-6 text-end">
                                            <button type="submit" name="confirm_from_agent_payment" class="btn btn-warning" id="confirmFromAgentPaymentBtn" disabled>
                                                تأكيد الدفع للوكيل
                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
            <?php else: ?>
                                            <div class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle me-2"></i>
                                                لا توجد شحنات تحتاج دفع للوكيل
                                            </div>
            <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($has_unpaid_to_agent): ?>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title">إجمالي المبلغ المحدد</h6>
                                                    <div class="amount-display" id="selectedTotal">0.00 ج.م</div>
                                                    <input type="hidden" name="total_amount" id="totalAmount" value="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title">إجمالي الشحنات غير المدفوعة</h6>
                                                    <div class="amount-display"><?php echo number_format($total_unpaid, 2); ?> ج.م</div>
            </div>
        </div>
    </div>
</div>

                                    <div class="text-center mt-3">
                                        <button type="submit" name="confirm_payment" class="btn btn-success btn-lg" id="confirmPaymentBtn" disabled>
                                            <i class="fas fa-check-circle me-2"></i>
                                            تأكيد الدفع
                                        </button>
            </div>
                                    <?php endif; ?>
                </form>
                            </div>

                            <!-- تبويب سجل المدفوعات -->
                            <div class="tab-pane fade" id="log" role="tabpanel">
                                <h5 class="mb-3">
                                    <i class="fas fa-history text-info me-2"></i>
                                    سجل المدفوعات
                                </h5>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>رقم العملية</th>
                                                <th>الوكيل</th>
                                                <th>تفاصيل الشحنات</th>
                                                <th>المبلغ الكلي</th>
                                                <th>تاريخ الدفع</th>
                                                <th>المستخدم المنفذ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($payment_log && !empty($payment_log)): ?>
                                                <?php foreach ($payment_log as $payment): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($payment['transaction_number']); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($payment['agent_name'] ?? 'غير محدد'); ?></td>
                                                    <td>
                                                        <?php if (!empty($payment['shipment_details'])): ?>
                                                            <div class="shipment-details">
                                                                <?php foreach ($payment['shipment_details'] as $shipment): ?>
                                                                    <div class="mb-1">
                                                                        <small class="text-primary fw-bold">
                                                                            #<?php echo htmlspecialchars($shipment['reference_number'] ?? $shipment['id']); ?>
                                                                        </small>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            <?php echo htmlspecialchars($shipment['recipient_name'] ?? 'غير محدد'); ?>
                                                                        </small>
                                                                    </div>
                                                                    <?php if (count($payment['shipment_details']) > 1): ?>
                                                                        <hr class="my-1">
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">لا توجد تفاصيل</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="amount-display">
                                                        <?php echo number_format($payment['amount'], 2); ?> ج.م
                                                    </td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($payment['payment_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['user_name'] ?? 'غير محدد'); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        لا توجد عمليات دفع مسجلة حتى الآن
                                                    </td>
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تفعيل Tooltips (للتلميحات المهمة فقط)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // إدارة الشحنات المرسلة للوكيل (الأموال المستحقة من الوكيل)
    const selectAllToAgent = document.getElementById('selectAll');
    const toAgentCheckboxes = document.querySelectorAll('.to-agent-checkbox');
    const toAgentSelectedTotal = document.getElementById('toAgentSelectedTotal');
    const confirmToAgentPaymentBtn = document.getElementById('confirmToAgentPaymentBtn');

    if (selectAllToAgent && toAgentCheckboxes.length > 0) {
        // اختيار/إلغاء اختيار الكل للشحنات المرسلة للوكيل
        selectAllToAgent.addEventListener('change', function() {
            toAgentCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateToAgentTotal();
        });

        // تحديث المجموع عند تغيير الاختيار
        toAgentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateToAgentTotal();
                updateToAgentSelectAllState();
            });
        });

        function updateToAgentTotal() {
            let total = 0;
            let selectedCount = 0;
            
            toAgentCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    total += parseFloat(checkbox.dataset.amount);
                    selectedCount++;
                }
            });
            
            toAgentSelectedTotal.textContent = total.toFixed(2);
            
            // تفعيل/إلغاء تفعيل زر التأكيد
            if (confirmToAgentPaymentBtn) {
                confirmToAgentPaymentBtn.disabled = selectedCount === 0;
            }
        }

        function updateToAgentSelectAllState() {
            const checkedCount = document.querySelectorAll('.to-agent-checkbox:checked').length;
            const totalCount = toAgentCheckboxes.length;
            
            selectAllToAgent.checked = checkedCount === totalCount;
            selectAllToAgent.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        }
    }

    // إدارة الشحنات المستلمة من الوكيل (الأموال المستحقة للوكيل)
    const selectAllFromAgent = document.getElementById('selectAllFromAgent');
    const fromAgentCheckboxes = document.querySelectorAll('.from-agent-checkbox');
    const fromAgentSelectedTotal = document.getElementById('fromAgentSelectedTotal');
    const confirmFromAgentPaymentBtn = document.getElementById('confirmFromAgentPaymentBtn');

    if (selectAllFromAgent && fromAgentCheckboxes.length > 0) {
        // اختيار/إلغاء اختيار الكل للشحنات المستلمة من الوكيل
        selectAllFromAgent.addEventListener('change', function() {
            fromAgentCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateFromAgentTotal();
        });

        // تحديث المجموع عند تغيير الاختيار
        fromAgentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateFromAgentTotal();
                updateFromAgentSelectAllState();
            });
        });

        function updateFromAgentTotal() {
            let total = 0;
            let selectedCount = 0;
            
            fromAgentCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    total += parseFloat(checkbox.dataset.amount);
                    selectedCount++;
                }
            });
            
            fromAgentSelectedTotal.textContent = total.toFixed(2);
            
            // تفعيل/إلغاء تفعيل زر التأكيد
            if (confirmFromAgentPaymentBtn) {
                confirmFromAgentPaymentBtn.disabled = selectedCount === 0;
            }
        }

        function updateFromAgentSelectAllState() {
            const checkedCount = document.querySelectorAll('.from-agent-checkbox:checked').length;
            const totalCount = fromAgentCheckboxes.length;
            
            selectAllFromAgent.checked = checkedCount === totalCount;
            selectAllFromAgent.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        }
    }

    // تأكيد دفع الشحنات المرسلة للوكيل
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.to-agent-checkbox:checked').length;
            if (selectedCount === 0) {
                e.preventDefault();
                alert('يرجى اختيار شحنات واحدة على الأقل للدفع.');
                return;
            }
            
            if (!confirm('هل أنت متأكد من تأكيد استلام المدفوعات من الوكيل للشحنات المحددة؟')) {
    e.preventDefault();
            }
        });
    }

    // تأكيد دفع الشحنات المستلمة من الوكيل
    const paymentFromAgentForm = document.getElementById('paymentFromAgentForm');
    if (paymentFromAgentForm) {
        paymentFromAgentForm.addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.from-agent-checkbox:checked').length;
            if (selectedCount === 0) {
                e.preventDefault();
                alert('يرجى اختيار شحنات واحدة على الأقل للدفع.');
        return;
    }
    
            if (!confirm('هل أنت متأكد من تأكيد الدفع للوكيل للشحنات المحددة؟')) {
                e.preventDefault();
            }
        });
    }
});
</script>
</body>
</html>