<?php
include 'db_connect.php';

$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    echo '<div class="alert alert-danger">معرف العميل غير صالح</div>';
    exit;
}

// Get customer information
$customer_query = "SELECT * FROM customers WHERE id = ?";
$stmt = $conn->prepare($customer_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    echo '<div class="alert alert-danger">العميل غير موجود</div>';
    exit;
}

// Get payment history with filters
$payment_method_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_conditions = ["cp.customer_id = ?"];
$params = [$customer_id];
$param_types = "i";

if (!empty($payment_method_filter)) {
    $where_conditions[] = "cp.payment_method = ?";
    $params[] = $payment_method_filter;
    $param_types .= "s";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(cp.payment_date) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(cp.payment_date) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "cp.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Updated query to match the actual database structure
$payments_query = "
    SELECT 
        cp.*,
        p.tracking_number,
        p.recipient_name,
        cpd.amount as parcel_payment_amount,
        p.cod_amount,
        p.total_to_collect
    FROM customer_payments cp
    LEFT JOIN customer_payment_details cpd ON cp.id = cpd.payment_id
    LEFT JOIN parcels p ON cpd.shipment_id = p.id
    WHERE $where_clause
    ORDER BY cp.payment_date DESC
";

$stmt = $conn->prepare($payments_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result();
$stmt->close();

// Get payment methods for filter (using the actual enum values from the database)
$methods_query = "SELECT DISTINCT payment_method FROM customer_payments WHERE customer_id = ? AND payment_method IS NOT NULL AND payment_method != '' ORDER BY payment_method";
$stmt = $conn->prepare($methods_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$payment_methods = $stmt->get_result();
$stmt->close();

// Calculate totals
$total_payments = 0;
$total_amount = 0;
$payments_data = [];

while ($payment = $payments->fetch_assoc()) {
    $payments_data[] = $payment;
    $total_payments++;
    $total_amount += floatval($payment['amount']);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل مدفوعات العميل - <?php echo htmlspecialchars($customer['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .summary-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .filter-section { background-color: #f8f9fa; border-radius: 10px; padding: 20px; }
        .table-responsive { border-radius: 10px; }
        .btn-export { border-radius: 20px; margin: 5px; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">
                        <i class="fas fa-history text-primary"></i>
                        سجل مدفوعات العميل: <?php echo htmlspecialchars($customer['name']); ?>
                    </h2>
                    <div>
                        <a href="./index.php?page=customer_profile&id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> العودة لملف العميل
                        </a>
                        <a href="./index.php?page=customer_list" class="btn btn-outline-primary">
                            <i class="fas fa-users"></i> قائمة العملاء
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <i class="fas fa-receipt fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo $total_payments; ?></h4>
                        <small>إجمالي المدفوعات</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($total_amount, 2); ?> ج.م</h4>
                        <small>إجمالي المبالغ</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                        <h4 class="mb-1"><?php echo date('Y/m/d'); ?></h4>
                        <small>التاريخ الحالي</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <i class="fas fa-phone fa-2x mb-2"></i>
                        <h6 class="mb-1"><?php echo htmlspecialchars($customer['phone']); ?></h6>
                        <small>رقم الهاتف</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card filter-section mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="fas fa-filter text-primary"></i> فلاتر البحث</h5>
                <form method="GET" class="row g-3">
                    <input type="hidden" name="id" value="<?php echo $customer_id; ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">طريقة الدفع</label>
                        <select name="payment_method" class="form-select">
                            <option value="">جميع الطرق</option>
                            <?php while ($method = $payment_methods->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($method['payment_method']); ?>" 
                                        <?php echo $payment_method_filter === $method['payment_method'] ? 'selected' : ''; ?>>
                                    <?php 
                                    $method_names = [
                                        'cash' => 'نقداً',
                                        'transfer' => 'تحويل بنكي',
                                        'check' => 'شيك',
                                        'online' => 'أونلاين'
                                    ];
                                    echo $method_names[$method['payment_method']] ?? $method['payment_method'];
                                    ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">حالة الدفع</label>
                        <select name="status" class="form-select">
                            <option value="">جميع الحالات</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>مكتمل</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>فشل</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> بحث
                        </button>
                        <a href="./index.php?page=customer_payment_history&id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-refresh"></i> إعادة تعيين
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-end">
                    <button class="btn btn-success btn-export" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> تصدير PDF
                    </button>
                    <button class="btn btn-info btn-export" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> تصدير Excel
                    </button>
                    <button class="btn btn-secondary btn-export" onclick="printPage()">
                        <i class="fas fa-print"></i> طباعة
                    </button>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list text-primary"></i> تفاصيل المدفوعات</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments_data)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">لا توجد مدفوعات</h5>
                        <p class="text-muted">لم يتم العثور على أي مدفوعات لهذا العميل</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>رقم الدفع</th>
                                    <th>التاريخ</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الدفع</th>
                                    <th>رقم التتبع</th>
                                    <th>اسم المستلم</th>
                                    <th>مبلغ COD</th>
                                    <th>الحالة</th>
                                    <th>ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments_data as $payment): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary">#<?php echo $payment['id']; ?></span>
                                        </td>
                                        <td>
                                            <?php echo $payment['payment_date'] ? date('Y/m/d H:i', strtotime($payment['payment_date'])) : 'غير محدد'; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">
                                                <?php echo number_format($payment['amount'], 2); ?> ج.م
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $method_names = [
                                                'cash' => 'نقداً',
                                                'transfer' => 'تحويل بنكي',
                                                'check' => 'شيك',
                                                'online' => 'أونلاين'
                                            ];
                                            $method_name = $method_names[$payment['payment_method']] ?? $payment['payment_method'];
                                            ?>
                                            <span class="badge bg-info"><?php echo $method_name; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($payment['tracking_number']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($payment['tracking_number']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $payment['recipient_name'] ? htmlspecialchars($payment['recipient_name']) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($payment['cod_amount']): ?>
                                                <span class="text-primary">
                                                    <?php echo number_format($payment['cod_amount'], 2); ?> ج.م
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_colors = [
                                                'completed' => 'success',
                                                'pending' => 'warning',
                                                'failed' => 'danger',
                                                'cancelled' => 'secondary'
                                            ];
                                            $status_names = [
                                                'completed' => 'مكتمل',
                                                'pending' => 'قيد الانتظار',
                                                'failed' => 'فشل',
                                                'cancelled' => 'ملغي'
                                            ];
                                            $status_color = $status_colors[$payment['status']] ?? 'secondary';
                                            $status_name = $status_names[$payment['status']] ?? $payment['status'];
                                            ?>
                                            <span class="badge bg-<?php echo $status_color; ?>">
                                                <?php echo $status_name; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($payment['notes']): ?>
                                                <span class="text-muted"><?php echo htmlspecialchars($payment['notes']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToPDF() {
            alert('سيتم إضافة ميزة التصدير إلى PDF قريباً');
        }
        
        function exportToExcel() {
            alert('سيتم إضافة ميزة التصدير إلى Excel قريباً');
        }
        
        function printPage() {
            window.print();
        }
    </script>
</body>
</html>
