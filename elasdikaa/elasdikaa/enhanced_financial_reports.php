<?php
/**
 * Enhanced Financial Reports
 * التقارير المالية المحدثة
 */

// تأكد من بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['login_id'])) {
    header("location: login.php");
    exit;
}

include 'db_connect.php';
include 'enhanced_payment_system.php';

// الحصول على المعاملات من URL
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

$payment_system = new EnhancedPaymentSystem($conn);

// جلب الإحصائيات المالية
$financial_stats = $payment_system->getFinancialStats($date_from, $date_to);

// حساب الإيرادات والمرتجعات والأرصدة المستحقة
$revenue_sql = "SELECT CalculateRevenue(?, ?) as total_revenue";
$returns_sql = "SELECT CalculateReturns(?, ?) as total_returns";
$outstanding_sql = "SELECT CalculateOutstandingBalances(?, ?) as outstanding_balances";

$stmt = $conn->prepare($revenue_sql);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$revenue_result = $stmt->get_result()->fetch_assoc();
$total_revenue = $revenue_result['total_revenue'] ?? 0;
$stmt->close();

$stmt = $conn->prepare($returns_sql);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$returns_result = $stmt->get_result()->fetch_assoc();
$total_returns = $returns_result['total_returns'] ?? 0;
$stmt->close();

$stmt = $conn->prepare($outstanding_sql);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$outstanding_result = $stmt->get_result()->fetch_assoc();
$outstanding_balances = $outstanding_result['outstanding_balances'] ?? 0;
$stmt->close();

// تقرير تفصيلي للشحنات
$detailed_report = [];
if ($report_type == 'detailed') {
    $detailed_sql = "SELECT 
        id, tracking_number, sender_phone, recipient_name,
        total_shipment_value, delivered_value, returned_value,
        amount_paid_so_far, remaining_balance, detailed_payment_status,
        date_created, status
        FROM parcels 
        WHERE DATE(date_created) BETWEEN ? AND ?
        ORDER BY date_created DESC";
    
    $stmt = $conn->prepare($detailed_sql);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $detailed_report[] = $row;
    }
    $stmt->close();
}

// تقرير حسب العميل
$customer_report = [];
if ($report_type == 'customers') {
    $customer_sql = "SELECT 
        c.id, c.name, c.phone,
        COUNT(p.id) as total_shipments,
        SUM(p.total_shipment_value) as total_shipment_value,
        SUM(p.delivered_value) as total_delivered_value,
        SUM(p.returned_value) as total_returned_value,
        SUM(p.amount_paid_so_far) as total_amount_paid,
        SUM(p.remaining_balance) as total_outstanding
        FROM customers c
        LEFT JOIN parcels p ON c.id = p.client_id_fk 
        AND DATE(p.date_created) BETWEEN ? AND ?
        GROUP BY c.id, c.name, c.phone
        HAVING total_shipments > 0
        ORDER BY total_delivered_value DESC";
    
    $stmt = $conn->prepare($customer_sql);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $customer_report[] = $row;
    }
    $stmt->close();
}

// تقرير الدفعات اليومية
$daily_payments = [];
if ($report_type == 'daily_payments') {
    $daily_sql = "SELECT 
        DATE(pph.payment_date) as payment_date,
        COUNT(*) as payments_count,
        SUM(pph.payment_amount) as total_payments,
        AVG(pph.payment_amount) as avg_payment
        FROM parcel_payment_history pph
        WHERE DATE(pph.payment_date) BETWEEN ? AND ?
        GROUP BY DATE(pph.payment_date)
        ORDER BY payment_date DESC";
    
    $stmt = $conn->prepare($daily_sql);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $daily_payments[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>التقارير المالية المحدثة | <?php echo $_SESSION['system']['name'] ?? 'نظام الشحن' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stats-card.revenue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stats-card.returns { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stats-card.outstanding { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        .stats-card.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stats-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .report-tabs {
            margin-bottom: 20px;
        }
        
        .report-tabs .nav-link {
            border-radius: 20px;
            margin: 0 5px;
        }
        
        .report-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: transparent;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .export-buttons {
            margin-bottom: 20px;
        }
        
        .export-buttons .btn {
            margin: 0 5px;
            border-radius: 20px;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-unpaid { background-color: #dc3545; color: white; }
        .status-partially-paid { background-color: #ffc107; color: #212529; }
        .status-fully-paid { background-color: #28a745; color: white; }
        
        .amount-positive { color: #28a745; font-weight: bold; }
        .amount-negative { color: #dc3545; font-weight: bold; }
        .amount-neutral { color: #6c757d; font-weight: bold; }
        
        @media print {
            .no-print { display: none !important; }
            .card { border: 1px solid #ddd !important; }
            .stats-card { background: #f8f9fa !important; color: #333 !important; }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4 no-print">
                    <i class="fas fa-chart-line me-2"></i>
                    التقارير المالية المحدثة
                </h2>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filter-section no-print">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">من تاريخ:</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">إلى تاريخ:</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">نوع التقرير:</label>
                    <select class="form-select" name="report_type">
                        <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>ملخص عام</option>
                        <option value="detailed" <?php echo $report_type == 'detailed' ? 'selected' : ''; ?>>تفصيلي للشحنات</option>
                        <option value="customers" <?php echo $report_type == 'customers' ? 'selected' : ''; ?>>تقرير العملاء</option>
                        <option value="daily_payments" <?php echo $report_type == 'daily_payments' ? 'selected' : ''; ?>>الدفعات اليومية</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>تحديث التقرير
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card revenue">
                    <div class="stats-number"><?php echo number_format($total_revenue, 0); ?></div>
                    <div class="stats-label">إجمالي الإيرادات (جنيه)</div>
                    <small>من القيم المسلمة فقط</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card returns">
                    <div class="stats-number"><?php echo number_format($total_returns, 0); ?></div>
                    <div class="stats-label">إجمالي المرتجعات (جنيه)</div>
                    <small>منفصلة عن الإيرادات</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card outstanding">
                    <div class="stats-number"><?php echo number_format($outstanding_balances, 0); ?></div>
                    <div class="stats-label">الأرصدة المستحقة (جنيه)</div>
                    <small>المبالغ غير المحصلة</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card total">
                    <div class="stats-number"><?php echo $financial_stats['total_shipments']; ?></div>
                    <div class="stats-label">إجمالي الشحنات</div>
                    <small>في الفترة المحددة</small>
                </div>
            </div>
        </div>

        <!-- Payment Status Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><?php echo $financial_stats['unpaid_count']; ?></h5>
                        <p class="card-text">شحنات غير مدفوعة</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><?php echo $financial_stats['partially_paid_count']; ?></h5>
                        <p class="card-text">شحنات مدفوعة جزئياً</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?php echo $financial_stats['fully_paid_count']; ?></h5>
                        <p class="card-text">شحنات مدفوعة بالكامل</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <?php if ($report_type == 'summary'): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>توزيع حالات الدفع</h5>
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>المبالغ المالية</h5>
                    <canvas id="financialChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Export Buttons -->
        <div class="export-buttons no-print text-center">
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-2"></i>تصدير Excel
            </button>
            <button class="btn btn-danger" onclick="exportToPDF()">
                <i class="fas fa-file-pdf me-2"></i>تصدير PDF
            </button>
            <button class="btn btn-info" onclick="printReport()">
                <i class="fas fa-print me-2"></i>طباعة
            </button>
        </div>

        <!-- Report Content -->
        <?php if ($report_type == 'detailed'): ?>
        <!-- Detailed Report -->
        <div class="table-container">
            <h5 class="mb-3"><i class="fas fa-list me-2"></i>التقرير التفصيلي للشحنات</h5>
            <div class="table-responsive">
                <table class="table table-striped" id="detailedTable">
                    <thead>
                        <tr>
                            <th>رقم التتبع</th>
                            <th>المستلم</th>
                            <th>هاتف المرسل</th>
                            <th>إجمالي الشحنة</th>
                            <th>القيمة المسلمة</th>
                            <th>القيمة المرتجعة</th>
                            <th>المبلغ المدفوع</th>
                            <th>الرصيد المتبقي</th>
                            <th>حالة الدفع</th>
                            <th>تاريخ الإنشاء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailed_report as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['tracking_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['recipient_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['sender_phone']); ?></td>
                            <td class="amount-neutral"><?php echo number_format($row['total_shipment_value'], 2); ?></td>
                            <td class="amount-positive"><?php echo number_format($row['delivered_value'], 2); ?></td>
                            <td class="amount-negative"><?php echo number_format($row['returned_value'], 2); ?></td>
                            <td class="amount-positive"><?php echo number_format($row['amount_paid_so_far'], 2); ?></td>
                            <td class="<?php echo $row['remaining_balance'] > 0 ? 'amount-negative' : 'amount-positive'; ?>">
                                <?php echo number_format($row['remaining_balance'], 2); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo str_replace('_', '-', $row['detailed_payment_status']); ?>">
                                    <?php 
                                    $status_text = [
                                        'unpaid' => 'غير مدفوعة',
                                        'partially_paid' => 'مدفوعة جزئياً',
                                        'fully_paid' => 'مدفوعة بالكامل'
                                    ];
                                    echo $status_text[$row['detailed_payment_status']] ?? 'غير محدد';
                                    ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($row['date_created'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($report_type == 'customers'): ?>
        <!-- Customer Report -->
        <div class="table-container">
            <h5 class="mb-3"><i class="fas fa-users me-2"></i>تقرير العملاء</h5>
            <div class="table-responsive">
                <table class="table table-striped" id="customerTable">
                    <thead>
                        <tr>
                            <th>اسم العميل</th>
                            <th>رقم الهاتف</th>
                            <th>عدد الشحنات</th>
                            <th>إجمالي قيمة الشحنات</th>
                            <th>إجمالي المسلم</th>
                            <th>إجمالي المرتجع</th>
                            <th>إجمالي المدفوع</th>
                            <th>الرصيد المستحق</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_report as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo $row['total_shipments']; ?></td>
                            <td class="amount-neutral"><?php echo number_format($row['total_shipment_value'] ?? 0, 2); ?></td>
                            <td class="amount-positive"><?php echo number_format($row['total_delivered_value'] ?? 0, 2); ?></td>
                            <td class="amount-negative"><?php echo number_format($row['total_returned_value'] ?? 0, 2); ?></td>
                            <td class="amount-positive"><?php echo number_format($row['total_amount_paid'] ?? 0, 2); ?></td>
                            <td class="<?php echo ($row['total_outstanding'] ?? 0) > 0 ? 'amount-negative' : 'amount-positive'; ?>">
                                <?php echo number_format($row['total_outstanding'] ?? 0, 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($report_type == 'daily_payments'): ?>
        <!-- Daily Payments Report -->
        <div class="table-container">
            <h5 class="mb-3"><i class="fas fa-calendar-day me-2"></i>تقرير الدفعات اليومية</h5>
            <div class="table-responsive">
                <table class="table table-striped" id="dailyTable">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>عدد الدفعات</th>
                            <th>إجمالي المبالغ</th>
                            <th>متوسط الدفعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_payments as $row): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($row['payment_date'])); ?></td>
                            <td><?php echo $row['payments_count']; ?></td>
                            <td class="amount-positive"><?php echo number_format($row['total_payments'], 2); ?> جنيه</td>
                            <td class="amount-neutral"><?php echo number_format($row['avg_payment'], 2); ?> جنيه</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Information -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>ملاحظات هامة</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li><strong>الإيرادات:</strong> تحسب فقط من القيم المسلمة للعملاء (delivered_value)</li>
                            <li><strong>المرتجعات:</strong> يتم عرضها منفصلة ولا تؤثر على حساب الرصيد المتبقي</li>
                            <li><strong>الرصيد المتبقي:</strong> = القيمة المسلمة - المبلغ المدفوع (بدون احتساب المرتجعات)</li>
                            <li><strong>الأرصدة المستحقة:</strong> المبالغ التي لم يتم تحصيلها بعد من العملاء</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Charts
        <?php if ($report_type == 'summary'): ?>
        // Payment Status Chart
        const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        new Chart(paymentStatusCtx, {
            type: 'pie',
            data: {
                labels: ['غير مدفوعة', 'مدفوعة جزئياً', 'مدفوعة بالكامل'],
                datasets: [{
                    data: [
                        <?php echo $financial_stats['unpaid_count']; ?>,
                        <?php echo $financial_stats['partially_paid_count']; ?>,
                        <?php echo $financial_stats['fully_paid_count']; ?>
                    ],
                    backgroundColor: ['#dc3545', '#ffc107', '#28a745']
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

        // Financial Chart
        const financialCtx = document.getElementById('financialChart').getContext('2d');
        new Chart(financialCtx, {
            type: 'bar',
            data: {
                labels: ['الإيرادات', 'المرتجعات', 'الأرصدة المستحقة'],
                datasets: [{
                    data: [
                        <?php echo $total_revenue; ?>,
                        <?php echo $total_returns; ?>,
                        <?php echo $outstanding_balances; ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>

        // Export functions
        function exportToExcel() {
            // Implementation for Excel export
            alert('تصدير Excel قيد التطوير');
        }

        function exportToPDF() {
            // Implementation for PDF export
            alert('تصدير PDF قيد التطوير');
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
