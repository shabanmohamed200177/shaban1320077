<?php
// ملف طباعة الشحنات
session_start();
include 'db_connect.php';

// التحقق من الصلاحيات
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

// الحصول على المعاملات
$print_type = $_GET['type'] ?? 'waybill';
$scope = $_GET['scope'] ?? 'all';
$options = explode(',', $_GET['options'] ?? 'header,footer,qr');

// بناء الاستعلام
$where = "WHERE 1=1";
$params = [];
$param_types = "";

// إضافة معايير الفلترة
if (!empty($_GET['tracking_number'])) {
    $where .= " AND p.tracking_number LIKE ?";
    $params[] = '%' . $_GET['tracking_number'] . '%';
    $param_types .= "s";
}

if (!empty($_GET['status'])) {
    $where .= " AND p.status = ?";
    $params[] = intval($_GET['status']);
    $param_types .= "i";
}

if (!empty($_GET['governorate_id'])) {
    $where .= " AND p.recipient_governorate_id = ?";
    $params[] = intval($_GET['governorate_id']);
    $param_types .= "i";
}

if (!empty($_GET['from_date'])) {
    $where .= " AND DATE(p.date_created) >= ?";
    $params[] = $_GET['from_date'];
    $param_types .= "s";
}

if (!empty($_GET['to_date'])) {
    $where .= " AND DATE(p.date_created) <= ?";
    $params[] = $_GET['to_date'];
    $param_types .= "s";
}

// فلترة للمستخدمين غير المديرين
if (isset($_SESSION['login_type']) && $_SESSION['login_type'] != 1) { 
    $where .= " AND (p.agent_id = ? OR p.courier_id = ?)";
    $params[] = $_SESSION['login_id'];
    $params[] = $_SESSION['login_id'];
    $param_types .= "ii";
}

$sql = "SELECT p.*,
               c.name as courier_name,
               ar_rec.name as recipient_area_name,
               g_rec.name as recipient_gov_name,
               a.company_name as agent_name,
               g_send.name as sender_gov_name,
               ar_send.name as sender_area_name
        FROM parcels p
        LEFT JOIN couriers c ON p.courier_id = c.id
        LEFT JOIN areas ar_rec ON p.recipient_area_id = ar_rec.id
        LEFT JOIN governorates g_rec ON p.recipient_governorate_id = g_rec.id
        LEFT JOIN agents a ON p.agent_id = a.id
        LEFT JOIN governorates g_send ON p.sender_governorate_id = g_send.id
        LEFT JOIN areas ar_send ON p.sender_area_id = ar_send.id
        $where
        ORDER BY p.date_created DESC";

$stmt = $conn->prepare($sql);
if ($param_types) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// تعريف حالات الشحنات
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

// حساب الإجماليات
$total_parcels = 0;
$total_cod = 0;
$total_commission = 0;
$total_due = 0;

$parcels_data = [];
while ($row = $result->fetch_assoc()) {
    $parcels_data[] = $row;
    $total_parcels++;
    $total_cod += ($row['cod_amount'] ?? 0);
    $total_commission += ($row['company_profit'] ?? 0);
    
    $total_to_collect = ($row['cod_amount'] ?? 0);
    if (($row['shipping_payer'] ?? '') == 'recipient') {
        $total_to_collect += ($row['shipping_fees'] ?? 0);
    }
    $total_due += $total_to_collect;
}

// طباعة حسب النوع المطلوب
switch ($print_type) {
    case 'waybill':
        printWaybills($parcels_data, $options);
        break;
    case 'label':
        printLabels($parcels_data, $options);
        break;
    case 'summary':
        printSummary($parcels_data, $total_parcels, $total_cod, $total_commission, $total_due, $options);
        break;
    default:
        printWaybills($parcels_data, $options);
}

function printWaybills($parcels_data, $options) {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>طباعة البواليص</title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none !important; }
                .page-break { page-break-after: always; }
            }
            
            body { 
                font-family: 'Tajawal', Arial, sans-serif; 
                direction: rtl; 
                margin: 20px;
                background: #f5f5f5;
            }
            
            .waybill {
                background: white;
                border: 2px solid #333;
                margin: 20px 0;
                padding: 20px;
                page-break-inside: avoid;
                max-width: 800px;
                margin: 20px auto;
            }
            
            .header {
                text-align: center;
                border-bottom: 2px solid #333;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            
            .company-logo {
                font-size: 24px;
                font-weight: bold;
                color: #1976d2;
                margin-bottom: 10px;
            }
            
            .waybill-title {
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .tracking-number {
                font-size: 18px;
                font-weight: bold;
                color: #333;
                background: #f0f0f0;
                padding: 10px;
                text-align: center;
                margin: 15px 0;
            }
            
            .section {
                margin: 15px 0;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            
            .section-title {
                font-weight: bold;
                font-size: 16px;
                margin-bottom: 10px;
                color: #1976d2;
                border-bottom: 1px solid #1976d2;
                padding-bottom: 5px;
            }
            
            .info-row {
                display: flex;
                justify-content: space-between;
                margin: 5px 0;
                padding: 5px 0;
                border-bottom: 1px solid #eee;
            }
            
            .info-label {
                font-weight: bold;
                color: #666;
                min-width: 120px;
            }
            
            .info-value {
                color: #333;
                flex: 1;
            }
            
            .qr-code {
                text-align: center;
                margin: 20px 0;
                padding: 15px;
                border: 1px solid #ddd;
                background: #f9f9f9;
            }
            
            .footer {
                text-align: center;
                margin-top: 30px;
                padding-top: 15px;
                border-top: 2px solid #333;
                font-size: 12px;
                color: #666;
            }
            
            .print-btn {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #1976d2;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
            }
            
            .print-btn:hover {
                background: #1565c0;
            }
        </style>
    </head>
    <body>
        <button class="print-btn no-print" onclick="window.print()">طباعة</button>
        
        <?php foreach ($parcels_data as $index => $parcel): ?>
            <div class="waybill">
                <?php if (in_array('header', $options)): ?>
                    <div class="header">
                        <div class="company-logo">شركة الشحن السريع</div>
                        <div class="waybill-title">بوليصة شحنة</div>
                        <div>تاريخ الطباعة: <?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="tracking-number">
                    رقم التتبع: <?php echo htmlspecialchars($parcel['tracking_number']); ?>
                </div>
                
                <div class="section">
                    <div class="section-title">بيانات الراسل</div>
                    <div class="info-row">
                        <span class="info-label">الاسم:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['sender_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الهاتف:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['sender_phone']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">المحافظة:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['sender_gov_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">المنطقة:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['sender_area_name'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">بيانات المستلم</div>
                    <div class="info-row">
                        <span class="info-label">الاسم:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['recipient_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الهاتف:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['recipient_phone']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">العنوان:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['recipient_address']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">المحافظة:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['recipient_gov_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">المنطقة:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['recipient_area_name'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">تفاصيل الشحنة</div>
                    <div class="info-row">
                        <span class="info-label">القيمة (COD):</span>
                        <span class="info-value"><?php echo number_format($parcel['cod_amount'] ?? 0, 2); ?> جنيه</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">تكلفة الشحن:</span>
                        <span class="info-value"><?php echo number_format($parcel['shipping_fees'] ?? 0, 2); ?> جنيه</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الشحن على:</span>
                        <span class="info-value"><?php echo ($parcel['shipping_payer'] ?? '') == 'recipient' ? 'المستلم' : 'الراسل'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الملاحظات:</span>
                        <span class="info-value"><?php echo htmlspecialchars($parcel['notes'] ?? ''); ?></span>
                    </div>
                </div>
                
                <?php if (in_array('qr', $options)): ?>
                    <div class="qr-code">
                        <div>QR Code</div>
                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($parcel['tracking_number']); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (in_array('footer', $options)): ?>
                    <div class="footer">
                        <div>شكراً لثقتكم بنا</div>
                        <div>للاستفسار: 0123456789</div>
                        <div>www.shipping-company.com</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($index < count($parcels_data) - 1): ?>
                <div class="page-break"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
}

function printLabels($parcels_data, $options) {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>طباعة الملصقات</title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none !important; }
            }
            
            body { 
                font-family: 'Tajawal', Arial, sans-serif; 
                direction: rtl; 
                margin: 10px;
                background: #f5f5f5;
            }
            
            .label {
                background: white;
                border: 1px solid #333;
                margin: 10px;
                padding: 15px;
                width: 300px;
                height: 200px;
                display: inline-block;
                vertical-align: top;
                page-break-inside: avoid;
            }
            
            .tracking-number {
                font-size: 16px;
                font-weight: bold;
                text-align: center;
                margin-bottom: 10px;
                background: #f0f0f0;
                padding: 5px;
            }
            
            .recipient-info {
                margin: 10px 0;
                font-size: 12px;
            }
            
            .recipient-name {
                font-weight: bold;
                font-size: 14px;
                margin-bottom: 5px;
            }
            
            .recipient-phone {
                color: #666;
                margin-bottom: 5px;
            }
            
            .recipient-address {
                color: #333;
                font-size: 11px;
                line-height: 1.3;
            }
            
            .qr-code {
                text-align: center;
                margin-top: 10px;
                font-size: 10px;
                color: #666;
            }
            
            .print-btn {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #1976d2;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
            }
        </style>
    </head>
    <body>
        <button class="print-btn no-print" onclick="window.print()">طباعة</button>
        
        <?php foreach ($parcels_data as $parcel): ?>
            <div class="label">
                <div class="tracking-number">
                    <?php echo htmlspecialchars($parcel['tracking_number']); ?>
                </div>
                
                <div class="recipient-info">
                    <div class="recipient-name"><?php echo htmlspecialchars($parcel['recipient_name']); ?></div>
                    <div class="recipient-phone"><?php echo htmlspecialchars($parcel['recipient_phone']); ?></div>
                    <div class="recipient-address"><?php echo htmlspecialchars($parcel['recipient_address']); ?></div>
                </div>
                
                <?php if (in_array('qr', $options)): ?>
                    <div class="qr-code">
                        QR: <?php echo htmlspecialchars($parcel['tracking_number']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
}

function printSummary($parcels_data, $total_parcels, $total_cod, $total_commission, $total_due, $options) {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ملخص الشحنات</title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none !important; }
            }
            
            body { 
                font-family: 'Tajawal', Arial, sans-serif; 
                direction: rtl; 
                margin: 20px;
                background: #f5f5f5;
            }
            
            .summary {
                background: white;
                border: 2px solid #333;
                margin: 20px auto;
                padding: 30px;
                max-width: 1000px;
            }
            
            .header {
                text-align: center;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            .company-logo {
                font-size: 28px;
                font-weight: bold;
                color: #1976d2;
                margin-bottom: 10px;
            }
            
            .summary-title {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            .summary-stats {
                display: flex;
                justify-content: space-around;
                margin: 30px 0;
                text-align: center;
            }
            
            .stat-item {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                border: 1px solid #dee2e6;
                min-width: 200px;
            }
            
            .stat-value {
                font-size: 24px;
                font-weight: bold;
                color: #1976d2;
                margin-bottom: 5px;
            }
            
            .stat-label {
                color: #666;
                font-size: 14px;
            }
            
            .parcels-table {
                width: 100%;
                border-collapse: collapse;
                margin: 30px 0;
            }
            
            .parcels-table th,
            .parcels-table td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: right;
            }
            
            .parcels-table th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            
            .print-btn {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #1976d2;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
            }
        </style>
    </head>
    <body>
        <button class="print-btn no-print" onclick="window.print()">طباعة</button>
        
        <div class="summary">
            <?php if (in_array('header', $options)): ?>
                <div class="header">
                    <div class="company-logo">شركة الشحن السريع</div>
                    <div class="summary-title">ملخص الشحنات</div>
                    <div>تاريخ الطباعة: <?php echo date('Y-m-d H:i:s'); ?></div>
                </div>
            <?php endif; ?>
            
            <div class="summary-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $total_parcels; ?></div>
                    <div class="stat-label">إجمالي الشحنات</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($total_cod, 2); ?> جنيه</div>
                    <div class="stat-label">إجمالي القيمة (COD)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($total_commission, 2); ?> جنيه</div>
                    <div class="stat-label">إجمالي العمولة</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($total_due, 2); ?> جنيه</div>
                    <div class="stat-label">إجمالي المستحق</div>
                </div>
            </div>
            
            <table class="parcels-table">
                <thead>
                    <tr>
                        <th>رقم الشحنة</th>
                        <th>الراسل</th>
                        <th>المستلم</th>
                        <th>الحالة</th>
                        <th>القيمة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parcels_data as $parcel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($parcel['tracking_number']); ?></td>
                            <td><?php echo htmlspecialchars($parcel['sender_name']); ?></td>
                            <td><?php echo htmlspecialchars($parcel['recipient_name']); ?></td>
                            <td><?php 
                                $status_val = isset($parcel['status']) ? intval($parcel['status']) : 0;
                                echo $status_arr[$status_val] ?? "غير معروف";
                            ?></td>
                            <td><?php echo number_format($parcel['cod_amount'] ?? 0, 2); ?> جنيه</td>
                            <td><?php echo date('Y-m-d', strtotime($parcel['date_created'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (in_array('footer', $options)): ?>
                <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #333; color: #666;">
                    <div>شكراً لثقتكم بنا</div>
                    <div>للاستفسار: 0123456789</div>
                    <div>www.shipping-company.com</div>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}
?>
