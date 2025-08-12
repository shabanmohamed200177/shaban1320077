<?php
// print_label.php - طباعة ملصقات الشحنات مع QR Code

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    include 'db_connect.php';
}

// جلب بيانات الشحنة
$parcel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($parcel_id <= 0) {
    die('معرف الشحنة غير صحيح');
}

$sql = "SELECT p.*, 
               c.name as courier_name,
               ar_rec.name as recipient_area_name,
               g_rec.name as recipient_gov_name,
               a.company_name as agent_name
        FROM parcels p
        LEFT JOIN couriers c ON p.courier_id = c.id
        LEFT JOIN areas ar_rec ON p.recipient_area_id = ar_rec.id
        LEFT JOIN governorates g_rec ON p.recipient_governorate_id = g_rec.id
        LEFT JOIN agents a ON p.agent_id = a.id
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parcel_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('الشحنة غير موجودة');
}

$parcel = $result->fetch_assoc();

// حساب المبلغ الإجمالي
$total_amount = $parcel['cod_amount'] ?? 0;
if (($parcel['shipping_payer'] ?? 'sender') == 'recipient') {
    $total_amount += ($parcel['shipping_fees'] ?? 0);
}

// إنشاء QR Code
$qr_data = $parcel['tracking_number'];
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($qr_data);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملصق الشحنة - <?php echo htmlspecialchars($parcel['tracking_number']); ?></title>
    <style>
        @page {
            size: 100mm 150mm;
            margin: 5mm;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            direction: rtl;
            margin: 0;
            padding: 0;
            background: white;
        }
        
        .label-container {
            width: 90mm;
            height: 140mm;
            border: 2px solid #000;
            padding: 5mm;
            box-sizing: border-box;
            position: relative;
        }
        
        .company-header {
            text-align: center;
            margin-bottom: 3mm;
            border-bottom: 1px solid #000;
            padding-bottom: 2mm;
        }
        
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            color: #1976d2;
            margin: 0;
        }
        
        .tracking-info {
            text-align: center;
            margin-bottom: 3mm;
            background: #f8f9fa;
            padding: 2mm;
            border-radius: 2mm;
        }
        
        .tracking-number {
            font-size: 16pt;
            font-weight: bold;
            color: #000;
            margin: 0;
        }
        
        .qr-section {
            text-align: center;
            margin-bottom: 3mm;
        }
        
        .qr-code {
            width: 80px;
            height: 80px;
            border: 1px solid #ccc;
        }
        
        .sender-info {
            margin-bottom: 3mm;
            border: 1px solid #ccc;
            padding: 2mm;
            background: #f8f9fa;
        }
        
        .sender-title {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
            color: #333;
        }
        
        .sender-details {
            font-size: 9pt;
            line-height: 1.3;
        }
        
        .recipient-info {
            margin-bottom: 3mm;
            border: 1px solid #ccc;
            padding: 2mm;
            background: #fff;
        }
        
        .recipient-title {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
            color: #333;
        }
        
        .recipient-details {
            font-size: 9pt;
            line-height: 1.3;
        }
        
        .amount-info {
            text-align: center;
            margin-bottom: 3mm;
            background: #28a745;
            color: white;
            padding: 2mm;
            border-radius: 2mm;
        }
        
        .amount-title {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        
        .amount-value {
            font-size: 12pt;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            font-size: 8pt;
            color: #666;
            margin-top: 2mm;
        }
        
        @media print {
            body { margin: 0; }
            .label-container { border: none; }
        }
    </style>
</head>
<body>
    <div class="label-container">
        <!-- ترويسة الشركة -->
        <div class="company-header">
            <h1 class="company-name">الأصدقاء</h1>
        </div>
        
        <!-- معلومات التتبع -->
        <div class="tracking-info">
            <p class="tracking-number"><?php echo htmlspecialchars($parcel['tracking_number']); ?></p>
        </div>
        
        <!-- QR Code -->
        <div class="qr-section">
            <img src="<?php echo $qr_url; ?>" alt="QR Code" class="qr-code">
        </div>
        
        <!-- معلومات الراسل -->
        <div class="sender-info">
            <div class="sender-title">الراسل:</div>
            <div class="sender-details">
                <strong>الاسم:</strong> <?php echo htmlspecialchars($parcel['sender_name']); ?><br>
                <strong>الهاتف:</strong> <?php echo htmlspecialchars($parcel['sender_phone']); ?>
            </div>
        </div>
        
        <!-- معلومات المستلم -->
        <div class="recipient-info">
            <div class="recipient-title">المستلم:</div>
            <div class="recipient-details">
                <strong>الاسم:</strong> <?php echo htmlspecialchars($parcel['recipient_name']); ?><br>
                <strong>الهاتف:</strong> <?php echo htmlspecialchars($parcel['recipient_phone']); ?><br>
                <strong>العنوان:</strong> <?php echo htmlspecialchars($parcel['recipient_address']); ?><br>
                <strong>المنطقة:</strong> <?php echo htmlspecialchars($parcel['recipient_area_name'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($parcel['recipient_gov_name'] ?? 'N/A'); ?>
            </div>
        </div>
        
        <!-- معلومات المبلغ -->
        <div class="amount-info">
            <div class="amount-title">المبلغ الإجمالي:</div>
            <div class="amount-value"><?php echo number_format($total_amount, 2); ?> جنيه</div>
        </div>
        
        <!-- تذييل -->
        <div class="footer">
            <p>تاريخ الطباعة: <?php echo date('Y-m-d H:i'); ?></p>
            <p>شركة الأصدقاء للشحن والتوصيل</p>
        </div>
    </div>
    
    <script>
        // طباعة تلقائية عند تحميل الصفحة
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
