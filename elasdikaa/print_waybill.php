<?php
// بوليصة شحن مبسطة ومحسنة لشركة الأصدقاء
include 'db_connect.php';

$parcel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($parcel_id == 0) {
    die('معرف الشحنة غير صالح');
}

// جلب بيانات الشحنة
$query = "
    SELECT p.*, 
           ps.name_ar as status_name,
           a.company_name as agent_name,
           c.name as courier_name,
           sg.name as sender_gov_name,
           sa.name as sender_area_name,
           rg.name as recipient_gov_name,
           ra.name as recipient_area_name
    FROM parcels p
    LEFT JOIN parcel_status ps ON p.status = ps.id
    LEFT JOIN agents a ON p.agent_id = a.id
    LEFT JOIN couriers c ON p.courier_id = c.id
    LEFT JOIN governorates sg ON p.sender_governorate_id = sg.id
    LEFT JOIN areas sa ON p.sender_area_id = sa.id
    LEFT JOIN governorates rg ON p.recipient_governorate_id = rg.id
    LEFT JOIN areas ra ON p.recipient_area_id = ra.id
    WHERE p.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $parcel_id);
$stmt->execute();
$result = $stmt->get_result();
$parcel = $result->fetch_assoc();

if (!$parcel) {
    die('الشحنة غير موجودة');
}

// حساب الإجمالي المطلوب تحصيله
$total_to_collect = $parcel['cod_amount'] ?? 0;
if (($parcel['shipping_payer'] ?? 'sender') == 'recipient') {
    $total_to_collect += ($parcel['shipping_fees'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوليصة شحن - شركة الأصدقاء</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            direction: rtl;
            background: #fff;
            color: #333;
            line-height: 1.4;
        }
        
        .waybill {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            padding: 15px;
            border: 2px solid #2c3e50;
            background: #fff;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .company-subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        
        .tracking-number {
            background: #3498db;
            color: white;
            padding: 8px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .section {
            margin-bottom: 15px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .section-header {
            background: #ecf0f1;
            padding: 8px 12px;
            font-weight: bold;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .section-content {
            padding: 10px 12px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .info-label {
            font-weight: bold;
            color: #34495e;
            min-width: 60px;
        }
        
        .info-value {
            color: #2c3e50;
            text-align: left;
        }
        
        .payment-section {
            background: #e8f5e8;
            border: 2px solid #27ae60;
        }
        
        .payment-section .section-header {
            background: #27ae60;
            color: white;
        }
        
        .total-amount {
            font-size: 18px;
            font-weight: bold;
            color: #27ae60;
            text-align: center;
            padding: 10px;
            background: #d5f4e6;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            background: #3498db;
        }
        
        .notes-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
        }
        
        .notes-section .section-header {
            background: #f39c12;
            color: white;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #bdc3c7;
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .qr-placeholder {
            width: 60px;
            height: 60px;
            border: 2px dashed #bdc3c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #95a5a6;
            margin: 0 auto 10px;
        }
        
        @media print {
            body { margin: 0; }
            .waybill { 
                max-width: none; 
                margin: 0; 
                border: none;
                box-shadow: none;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #2980b9;
        }
        
        @media print {
            .print-button { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ طباعة</button>
    
    <div class="waybill">
        <!-- رأس البوليصة -->
        <div class="header">
            <div class="company-name">شركة الأصدقاء</div>
            <div class="company-subtitle">للشحن والتوصيل السريع</div>
            <div class="tracking-number">
                رقم التتبع: <?php echo htmlspecialchars($parcel['tracking_number']); ?>
            </div>
        </div>
        
        <!-- بيانات المرسل -->
        <div class="section">
            <div class="section-header">📤 بيانات المرسل</div>
            <div class="section-content">
                <div class="info-row">
                    <span class="info-label">الاسم:</span>
                    <span class="info-value"><?php echo htmlspecialchars($parcel['sender_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">الهاتف:</span>
                    <span class="info-value"><?php echo htmlspecialchars($parcel['sender_phone']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- بيانات المستلم -->
        <div class="section">
            <div class="section-header">📥 بيانات المستلم</div>
            <div class="section-content">
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
                    <span class="info-label">المنطقة:</span>
                    <span class="info-value"><?php echo htmlspecialchars($parcel['recipient_area_name'] . ' - ' . $parcel['recipient_gov_name']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- تفاصيل الشحنة -->
        <div class="section">
            <div class="section-header">📦 تفاصيل الشحنة</div>
            <div class="section-content">
                <div class="info-row">
                    <span class="info-label">المحتوى:</span>
                    <span class="info-value"><?php echo htmlspecialchars($parcel['contents']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">الوزن:</span>
                    <span class="info-value"><?php echo htmlspecialchars($parcel['weight']); ?> كجم</span>
                </div>
                <div class="info-row">
                    <span class="info-label">الحالة:</span>
                    <span class="info-value">
                        <span class="status-badge"><?php echo htmlspecialchars($parcel['status_name']); ?></span>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- المعلومات المالية -->
        <?php if ($total_to_collect > 0): ?>
        <div class="section payment-section">
            <div class="section-header">💰 المبلغ المطلوب تحصيله</div>
            <div class="section-content">
                <div class="total-amount">
                    <?php echo number_format($total_to_collect, 2); ?> جنيه
                </div>
                <?php if (($parcel['cod_amount'] ?? 0) > 0): ?>
                <div class="info-row">
                    <span class="info-label">قيمة البضاعة:</span>
                    <span class="info-value"><?php echo number_format($parcel['cod_amount'], 2); ?> جنيه</span>
                </div>
                <?php endif; ?>
                <?php if (($parcel['shipping_payer'] ?? '') == 'recipient' && ($parcel['shipping_fees'] ?? 0) > 0): ?>
                <div class="info-row">
                    <span class="info-label">رسوم الشحن:</span>
                    <span class="info-value"><?php echo number_format($parcel['shipping_fees'], 2); ?> جنيه</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- معلومات التوصيل -->
        <?php if (!empty($parcel['courier_name']) || !empty($parcel['agent_name'])): ?>
        <div class="section">
            <div class="section-header">🚚 معلومات التوصيل</div>
            <div class="section-content">
                <?php if (!empty($parcel['courier_name'])): ?>
                <div class="info-row">
                    <span class="info-label">المندوب:</span>
                    <span class="info-value"><?php echo htmlspecialchars($parcel['courier_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($parcel['agent_name'])): ?>
                <div class="info-row">
                    <span class="info-label">الوكيل:</span>
                    <span class="info-value"><?php echo htmlspecialchars($parcel['agent_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ملاحظات -->
        <?php if (!empty($parcel['notes'])): ?>
        <div class="section notes-section">
            <div class="section-header">📝 ملاحظات</div>
            <div class="section-content">
                <?php echo nl2br(htmlspecialchars($parcel['notes'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- رمز QR وتذييل -->
        <div class="footer">
            <div class="qr-placeholder">QR Code</div>
            <div>تاريخ الإنشاء: <?php echo date('d/m/Y H:i', strtotime($parcel['date_created'])); ?></div>
            <div style="margin-top: 5px; font-weight: bold;">شركة الأصدقاء للشحن والتوصيل</div>
            <div>هاتف: 01000000000 | البريد: info@alasdeqaa.com</div>
        </div>
    </div>
</body>
</html>