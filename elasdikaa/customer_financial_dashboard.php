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

// Get financial summary
$financial_query = "
    SELECT 
        COUNT(p.id) as total_parcels,
        SUM(COALESCE(p.total_to_collect, p.cod_amount, 0)) as total_amount,
        SUM(COALESCE(p.paid_amount, 0)) as total_paid,
        SUM(COALESCE(p.total_to_collect, p.cod_amount, 0) - COALESCE(p.paid_amount, 0)) as total_remaining,
        COUNT(CASE WHEN p.payment_status = 'paid' THEN 1 END) as paid_parcels,
        COUNT(CASE WHEN p.payment_status IN ('partial_paid', 'partially_paid') THEN 1 END) as partial_paid_parcels,
        COUNT(CASE WHEN p.payment_status = 'unpaid' THEN 1 END) as unpaid_parcels
    FROM parcels p
    WHERE p.sender_phone = ?
";
$stmt = $conn->prepare($financial_query);
$stmt->bind_param("s", $customer['phone']);
$stmt->execute();
$financial_summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get monthly payment data for chart
$monthly_payments_query = "
    SELECT 
        DATE_FORMAT(cp.payment_date, '%Y-%m') as month,
        SUM(cp.amount) as total_amount,
        COUNT(cp.id) as payment_count
    FROM customer_payments cp
    WHERE cp.customer_id = ? AND cp.status = 'completed'
    GROUP BY DATE_FORMAT(cp.payment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";
$stmt = $conn->prepare($monthly_payments_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$monthly_payments = $stmt->get_result();
$stmt->close();

// Get payment methods distribution
$payment_methods_query = "
    SELECT 
        cp.payment_method,
        COUNT(cp.id) as count,
        SUM(cp.amount) as total_amount
    FROM customer_payments cp
    WHERE cp.customer_id = ? AND cp.status = 'completed'
    GROUP BY cp.payment_method
    ORDER BY total_amount DESC
";
$stmt = $conn->prepare($payment_methods_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$payment_methods_data = $stmt->get_result();
$stmt->close();

// Get recent payments
$recent_payments_query = "
    SELECT cp.*, p.tracking_number
    FROM customer_payments cp
    LEFT JOIN customer_payment_details cpd ON cp.id = cpd.payment_id
    LEFT JOIN parcels p ON cpd.shipment_id = p.id
    WHERE cp.customer_id = ?
    ORDER BY cp.payment_date DESC
    LIMIT 10
";
$stmt = $conn->prepare($recent_payments_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$recent_payments = $stmt->get_result();
$stmt->close();

// Get overdue parcels
$overdue_query = "
    SELECT p.*, DATEDIFF(NOW(), p.date_created) as days_overdue
    FROM parcels p
    WHERE p.sender_phone = ? 
    AND p.payment_status != 'paid'
    AND DATEDIFF(NOW(), p.date_created) > 30
    ORDER BY p.date_created ASC
";
$stmt = $conn->prepare($overdue_query);
$stmt->bind_param("s", $customer['phone']);
$stmt->execute();
$overdue_parcels = $stmt->get_result();
$stmt->close();

// Calculate performance indicators
$payment_rate = $financial_summary['total_parcels'] > 0 ? 
    round(($financial_summary['paid_parcels'] / $financial_summary['total_parcels']) * 100, 1) : 0;

$average_payment = $financial_summary['total_paid'] > 0 ? 
    round($financial_summary['total_paid'] / $financial_summary['total_parcels'], 2) : 0;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المعلومات المالية - <?php echo htmlspecialchars($customer['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .metric-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .chart-container { position: relative; height: 300px; }
        .overdue-badge { font-size: 0.8rem; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">
                        <i class="fas fa-chart-line text-primary"></i>
                        لوحة المعلومات المالية: <?php echo htmlspecialchars($customer['name']); ?>
                    </h2>
                    <div>
                        <a href="./index.php?page=customer_profile&id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> العودة لملف العميل
                        </a>
                        <a href="./index.php?page=customer_payment_history&id=<?php echo $customer_id; ?>" class="btn btn-info">
                            <i class="fas fa-history"></i> سجل المدفوعات
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <i class="fas fa-shipping-fast fa-2x mb-2"></i>
                        <h3><?php echo $financial_summary['total_parcels']; ?></h3>
                        <small>إجمالي الشحنات</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                        <h3><?php echo number_format($financial_summary['total_amount'], 2); ?> ج.م</h3>
                        <small>إجمالي المطلوب تحصيله</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h3><?php echo number_format($financial_summary['total_paid'], 2); ?> ج.م</h3>
                        <small>إجمالي المدفوع</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <h3><?php echo number_format($financial_summary['total_remaining'], 2); ?> ج.م</h3>
                        <small>المتبقي</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-8 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line text-primary"></i> تطور المدفوعات الشهرية</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie text-primary"></i> توزيع طرق الدفع</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="paymentMethodsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Breakdown and Performance -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list text-primary"></i> حالة الشحنات</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>مدفوع بالكامل:</span>
                            <span class="badge bg-success"><?php echo $financial_summary['paid_parcels']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>مدفوع جزئياً:</span>
                            <span class="badge bg-warning"><?php echo $financial_summary['partial_paid_parcels']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>غير مدفوع:</span>
                            <span class="badge bg-danger"><?php echo $financial_summary['unpaid_parcels']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tachometer-alt text-primary"></i> مؤشرات الأداء</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>معدل الدفع:</span>
                            <span class="badge bg-info"><?php echo $payment_rate; ?>%</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>متوسط الدفع:</span>
                            <span class="badge bg-primary"><?php echo number_format($average_payment, 2); ?> ج.م</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payments and Overdue Parcels -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock text-primary"></i> آخر المدفوعات</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_payments->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>المبلغ</th>
                                            <th>الطريقة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('Y/m/d', strtotime($payment['payment_date'])); ?></td>
                                                <td class="text-success"><?php echo number_format($payment['amount'], 2); ?> ج.م</td>
                                                <td>
                                                    <?php 
                                                    $method_names = [
                                                        'cash' => 'نقداً',
                                                        'transfer' => 'تحويل',
                                                        'check' => 'شيك',
                                                        'online' => 'أونلاين'
                                                    ];
                                                    echo $method_names[$payment['payment_method']] ?? $payment['payment_method'];
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">لا توجد مدفوعات حديثة</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle text-warning"></i> الشحنات المتأخرة</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($overdue_parcels->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>رقم التتبع</th>
                                            <th>المبلغ</th>
                                            <th>الأيام</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($parcel = $overdue_parcels->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary overdue-badge">
                                                        <?php echo htmlspecialchars($parcel['tracking_number']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-danger">
                                                    <?php echo number_format($parcel['total_to_collect'] ?? $parcel['cod_amount'] ?? 0, 2); ?> ج.م
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning"><?php echo $parcel['days_overdue']; ?> يوم</span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-success text-center">لا توجد شحنات متأخرة</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Payment Evolution Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = <?php 
            $chart_data = [];
            while ($row = $monthly_payments->fetch_assoc()) {
                $chart_data[] = [
                    'month' => date('M Y', strtotime($row['month'] . '-01')),
                    'amount' => floatval($row['total_amount']),
                    'count' => intval($row['payment_count'])
                ];
            }
            echo json_encode(array_reverse($chart_data));
        ?>;

        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'إجمالي المدفوعات',
                    data: monthlyData.map(item => item.amount),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Payment Methods Distribution Chart
        const methodsCtx = document.getElementById('paymentMethodsChart').getContext('2d');
        const methodsData = <?php 
            $methods_chart_data = [];
            while ($row = $payment_methods_data->fetch_assoc()) {
                $method_names = [
                    'cash' => 'نقداً',
                    'transfer' => 'تحويل',
                    'check' => 'شيك',
                    'online' => 'أونلاين'
                ];
                $methods_chart_data[] = [
                    'method' => $method_names[$row['payment_method']] ?? $row['payment_method'],
                    'amount' => floatval($row['total_amount'])
                ];
            }
            echo json_encode($methods_chart_data);
        ?>;

        new Chart(methodsCtx, {
            type: 'doughnut',
            data: {
                labels: methodsData.map(item => item.method),
                datasets: [{
                    data: methodsData.map(item => item.amount),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
</body>
</html>
