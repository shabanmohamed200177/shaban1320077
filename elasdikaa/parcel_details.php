<?php
/**
 * صفحة تفاصيل الشحنة المحسنة
 * تعرض تفاصيل الشحنة بشكل منظم ومتكامل
 * متوافقة مع آخر التحديثات في النظام
 */

// بدء الجلسة إذا لم تكن بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من وضع الطباعة
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// التحقق من نوع العرض (modal أم صفحة كاملة)
$is_modal_view = !$print_mode;

include 'db_connect.php';

// التحقق من وجود معرف الشحنة
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger text-center m-4">معرف الشحنة غير موجود.</div>';
    exit;
}

$parcel_id = intval($_GET['id']);

// جلب تفاصيل الشحنة مع جميع البيانات المرتبطة
$query = "
    SELECT 
        p.*, 
        a.company_name as agent_company, 
        a.contact_person as agent_contact,
        c.name as courier_name, 
        c.phone as courier_phone, 
        ar.name as recipient_area_name,
        g.name as recipient_gov_name,
        sar.name as sender_area_name,
        sg.name as sender_gov_name,
        cu.name as customer_name,
        cu.phone as customer_phone,
        ps.name_ar as status_name,
        psr.reason_text as status_reason,
        psr.reason_code as status_reason_code,
        p.status_updated_at,
        p.status_updated_by
    FROM 
        parcels p
    LEFT JOIN agents a ON p.agent_id = a.id
    LEFT JOIN couriers c ON p.courier_id = c.id
    LEFT JOIN areas ar ON p.recipient_area_id = ar.id
    LEFT JOIN governorates g ON p.recipient_governorate_id = g.id
    LEFT JOIN areas sar ON p.sender_area_id = sar.id
    LEFT JOIN governorates sg ON p.sender_governorate_id = sg.id
    LEFT JOIN customers cu ON p.client_id_fk = cu.id
    LEFT JOIN parcel_status ps ON p.status = ps.id
    LEFT JOIN parcel_status_reasons psr ON p.status_reason_id = psr.id
    WHERE 
        p.id = ?
";

$stmt = $conn->prepare($query);
    $stmt->bind_param("i", $parcel_id);
    $stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger text-center m-4">الشحنة غير موجودة.</div>';
    exit;
}

$parcel = $result->fetch_assoc();

// حسابات مالية موحّدة للعرض وفق القواعد الجديدة
$cod_amount = (float)($parcel['cod_amount'] ?? 0);
$shipping_fees = (float)($parcel['shipping_fees'] ?? 0);
$shipping_payer = (string)($parcel['shipping_payer'] ?? 'sender');
$shipment_direction = (string)($parcel['shipment_direction'] ?? 'to_agent');
$paid_amount = (float)($parcel['paid_amount'] ?? 0); // هذا هو الصافي بعد الخصومات وفق المنطق الجديد

// إجمالي المطلوب تحصيله عند الباب
if (!empty($parcel['total_to_collect']) && (float)$parcel['total_to_collect'] > 0) {
    $total_to_collect_display = (float)$parcel['total_to_collect'];
} else {
    $total_to_collect_display = $cod_amount;
    if ($shipment_direction === 'to_agent' && $shipping_payer === 'recipient') {
        $total_to_collect_display += $shipping_fees;
    }
}

$remaining_display = max(0, $total_to_collect_display - $paid_amount);

// جلب تاريخ حالات الشحنة
$history_query = "
    SELECT 
        psh.*, 
        ps_old.name_ar as old_status_name,
        ps_new.name_ar as new_status_name,
        psr.reason_text
    FROM 
        parcel_status_history psh
    LEFT JOIN parcel_status ps_old ON psh.old_status_id = ps_old.id
    LEFT JOIN parcel_status ps_new ON psh.new_status_id = ps_new.id
    LEFT JOIN parcel_status_reasons psr ON psh.reason_id = psr.id
    WHERE 
        psh.parcel_id = ?
    ORDER BY 
        psh.changed_at DESC
    LIMIT 5
";

$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $parcel_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

// دوال مساعدة
function formatDate($date) {
    if (!$date) return '<span class="text-muted">غير محدد</span>';
    return '<span class="fw-medium">' . date('Y-m-d', strtotime($date)) . '</span><br><small class="text-muted">' . date('H:i', strtotime($date)) . '</small>';
}

function formatMoney($amount) {
    if ($amount == 0) return '<span class="text-muted">0.00 ج.م</span>';
    return '<span class="fw-bold text-success">' . number_format($amount, 2) . ' ج.م</span>';
}

function getStatusBadge($status) {
    $status_info = [
        1 => ['class' => 'primary', 'text' => 'قيد التنفيذ'],
        2 => ['class' => 'info', 'text' => 'مع المندوب'], 
        3 => ['class' => 'warning', 'text' => 'قيد التوصيل'],
        4 => ['class' => 'success', 'text' => 'تم التسليم'],
        5 => ['class' => 'success', 'text' => 'تم الدفع'],
        6 => ['class' => 'warning', 'text' => 'دفع جزئي'],
        7 => ['class' => 'secondary', 'text' => 'مؤجل'],
        8 => ['class' => 'danger', 'text' => 'مرفوض']
    ];
    
    $info = $status_info[$status] ?? ['class' => 'secondary', 'text' => 'غير محدد'];
    return '<span class="badge bg-' . $info['class'] . ' fs-6 px-3 py-2">' . $info['text'] . '</span>';
}

if ($print_mode): ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفاصيل الشحنة - <?php echo $parcel['tracking_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>@media print { body { font-size: 12px; } .no-print { display: none !important; } }</style>
</head>
<body>
<?php endif; 

// إذا كان modal، أضف تنسيق خاص
if ($is_modal_view): ?>
    <style>
/* تصميم بسيط ونظيف مثل الصورة */
#parcelDetailsModal .container-fluid,
.container-fluid {
    padding: 20px !important;
    background: #f8f9fa !important;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    min-height: auto !important;
}

#parcelDetailsModal .main-header,
.main-header {
    background: white !important;
    border-radius: 8px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    margin-bottom: 20px !important;
    padding: 20px !important;
    border-left: 4px solid #007bff !important;
}

#parcelDetailsModal .status-badge,
.status-badge {
    background: #dc3545 !important;
    color: white !important;
    padding: 8px 16px !important;
    border-radius: 20px !important;
    font-size: 0.9em !important;
    font-weight: 600 !important;
    position: absolute !important;
    top: 20px !important;
    right: 20px !important;
}

#parcelDetailsModal .tracking-number,
.tracking-number {
    font-size: 1.5em !important;
    font-weight: 700 !important;
    color: #333 !important;
    margin-bottom: 5px !important;
}

#parcelDetailsModal .content-section,
.content-section {
    background: white !important;
    border-radius: 8px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    margin-bottom: 20px !important;
    overflow: hidden !important;
}

#parcelDetailsModal .section-header,
.section-header {
    background: #007bff !important;
    color: white !important;
    padding: 15px 20px !important;
    margin: 0 !important;
    font-size: 1.1em !important;
    font-weight: 600 !important;
}

#parcelDetailsModal .section-body,
.section-body {
    padding: 20px !important;
}

#parcelDetailsModal .info-table,
.info-table {
    width: 100% !important;
    border-collapse: collapse !important;
}

#parcelDetailsModal .info-table td,
.info-table td {
    padding: 12px 15px !important;
    border-bottom: 1px solid #eee !important;
    vertical-align: top !important;
}

#parcelDetailsModal .info-table td:first-child,
.info-table td:first-child {
    color: #666 !important;
    font-weight: 500 !important;
    width: 30% !important;
}

#parcelDetailsModal .info-table td:last-child,
.info-table td:last-child {
    color: #333 !important;
    font-weight: 400 !important;
}

#parcelDetailsModal .phone-link,
.phone-link {
    color: #007bff !important;
    text-decoration: none !important;
}

#parcelDetailsModal .phone-link:hover,
.phone-link:hover {
    text-decoration: underline !important;
}

#parcelDetailsModal .financial-grid,
.financial-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 15px !important;
    margin-bottom: 20px !important;
}

#parcelDetailsModal .financial-item,
.financial-item {
    text-align: center !important;
    padding: 20px !important;
    background: #f8f9fa !important;
    border-radius: 8px !important;
    border: 2px solid #e9ecef !important;
}

#parcelDetailsModal .financial-value,
.financial-value {
    font-size: 1.3em !important;
    font-weight: 700 !important;
    margin-bottom: 5px !important;
}

#parcelDetailsModal .financial-label,
.financial-label {
    color: #666 !important;
    font-size: 0.9em !important;
}

#parcelDetailsModal .timeline,
.timeline {
    position: relative !important;
    padding-right: 30px !important;
}

#parcelDetailsModal .timeline::before,
.timeline::before {
    content: '' !important;
    position: absolute !important;
    right: 15px !important;
    top: 0 !important;
    bottom: 0 !important;
    width: 2px !important;
    background: #007bff !important;
}

#parcelDetailsModal .timeline-item,
.timeline-item {
    position: relative !important;
    margin-bottom: 20px !important;
    background: #f8f9fa !important;
    padding: 15px !important;
    border-radius: 8px !important;
    margin-left: 40px !important;
}

#parcelDetailsModal .timeline-item::before,
.timeline-item::before {
    content: '' !important;
    position: absolute !important;
    right: -38px !important;
    top: 20px !important;
    width: 12px !important;
    height: 12px !important;
    background: #007bff !important;
    border-radius: 50% !important;
    border: 3px solid white !important;
    box-shadow: 0 0 0 3px #007bff !important;
}

#parcelDetailsModal .btn-whatsapp,
.btn-whatsapp {
    background: #25d366 !important;
    border: none !important;
    color: white !important;
    padding: 10px 20px !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: background 0.3s !important;
}

#parcelDetailsModal .btn-whatsapp:hover,
.btn-whatsapp:hover {
    background: #1da851 !important;
    color: white !important;
}

#parcelDetailsModal .close-btn,
.close-btn {
    position: fixed !important;
    top: 15px !important;
    left: 15px !important;
    z-index: 9999 !important;
    background: #dc3545 !important;
    border: none !important;
    color: white !important;
    width: 35px !important;
    height: 35px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    transition: all 0.3s !important;
}

#parcelDetailsModal .close-btn:hover,
.close-btn:hover {
    background: #c82333 !important;
    transform: scale(1.1) !important;
}

/* إخفاء العناصر غير المرغوبة */
#parcelDetailsModal .text-primary,
.text-primary { color: #007bff !important; }
#parcelDetailsModal .text-success,
.text-success { color: #28a745 !important; }
#parcelDetailsModal .text-info,
.text-info { color: #17a2b8 !important; }
#parcelDetailsModal .text-warning,
.text-warning { color: #ffc107 !important; }
#parcelDetailsModal .text-danger,
.text-danger { color: #dc3545 !important; }
#parcelDetailsModal .text-muted,
.text-muted { color: #6c757d !important; }

/* تحسين Bootstrap classes داخل Modal */
#parcelDetailsModal .row {
    margin-right: -15px !important;
    margin-left: -15px !important;
}

#parcelDetailsModal .col-md-6 {
    padding-right: 15px !important;
    padding-left: 15px !important;
}

#parcelDetailsModal .mt-2 {
    margin-top: 0.5rem !important;
}

#parcelDetailsModal .mt-3 {
    margin-top: 1rem !important;
}

#parcelDetailsModal .mb-3 {
    margin-bottom: 1rem !important;
}

#parcelDetailsModal .small {
    font-size: 0.875em !important;
}

#parcelDetailsModal strong {
    font-weight: 700 !important;
        }
    </style>
<?php endif; ?>

<?php if ($is_modal_view): ?>
<!-- زر الإغلاق البسيط -->
<button type="button" class="close-btn" onclick="$('#parcelDetailsModal').modal('hide');">×</button>

<!-- إضافة Bootstrap CSS إذا لم يكن موجوداً -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

<div class="container-fluid">
<?php else: ?>
<div class="container-fluid p-4">
<?php endif; ?>
    
    <!-- Header الرئيسي -->
    <div class="main-header position-relative">
        <div class="status-badge"><?php echo $parcel['status_name'] ?: 'مرفوض'; ?></div>
        <div class="tracking-number"><?php echo $parcel['tracking_number']; ?></div>
        <div class="text-muted">رقم التتبع</div>
        <div class="mt-2">
            <small class="text-muted">الوزن: <?php echo $parcel['weight'] ?: '1'; ?> كجم | القطع: <?php echo $parcel['pieces'] ?: '1'; ?></small>
                </div>
        <div class="mt-2">
            <small class="text-muted">تاريخ الإنشاء: <?php echo date('Y-m-d H:i', strtotime($parcel['date_created'])); ?></small>
            </div>

        <!-- زر مشاركة واتساب -->
        <div class="mt-3">
            <button type="button" class="btn-whatsapp" onclick="shareWhatsApp()">مشاركة</button>
                        </div>
                    </div>
                
    <!-- معلومات الحالة الحالية -->
    <div class="content-section">
        <h5 class="section-header">الحالة الحالية</h5>
        <div class="section-body">
            <table class="info-table">
                <tr>
                    <td><strong>الحالة:</strong></td>
                    <td>
                        <span class="badge 
                            <?php 
                            $status_val = $parcel['status'] ?? 1;
                            switch($status_val) {
                                case 1: echo 'bg-primary'; break;
                                case 2: echo 'bg-info'; break;
                                case 3: echo 'bg-warning'; break;
                                case 4: echo 'bg-success'; break;
                                case 5: echo 'bg-success'; break;
                                case 6: echo 'bg-warning'; break;
                                case 7: echo 'bg-secondary'; break;
                                case 8: echo 'bg-danger'; break;
                                case 9: echo 'bg-dark'; break;
                                case 10: echo 'bg-dark'; break;
                                case 11: echo 'bg-dark'; break;
                                case 12: echo 'bg-dark'; break;
                                default: echo 'bg-secondary'; break;
                            }
                            ?>">
                            <?php echo $parcel['status_name'] ?: 'غير محدد'; ?>
                        </span>
                    </td>
                </tr>
                <?php if ($parcel['status_reason']): ?>
                <tr>
                    <td><strong>سبب الحالة:</strong></td>
                    <td><?php echo $parcel['status_reason']; ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($parcel['status_reason_note']): ?>
                <tr>
                    <td><strong>ملاحظة إضافية:</strong></td>
                    <td class="status-note"><?php echo nl2br($parcel['status_reason_note']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($parcel['status_updated_at']): ?>
                <tr>
                    <td><strong>آخر تحديث:</strong></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($parcel['status_updated_at'])); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($parcel['status_updated_by']): ?>
                <tr>
                    <td><strong>تم التحديث بواسطة:</strong></td>
                    <td><?php echo $parcel['status_updated_by']; ?></td>
                </tr>
                <?php endif; ?>
            </table>
                    </div>
                </div>

    <!-- بيانات الراسل والمستلم -->
    <div class="row">
        <div class="col-md-6">
            <div class="content-section">
                <h5 class="section-header">الراسل</h5>
                <div class="section-body">
                    <table class="info-table">
                        <tr>
                            <td>الاسم:</td>
                            <td><?php echo $parcel['sender_name']; ?></td>
                            </tr>
                        <tr>
                            <td>الهاتف:</td>
                            <td>
                                <a href="tel:<?php echo $parcel['sender_phone']; ?>" class="phone-link">
                                    <?php echo $parcel['sender_phone']; ?>
                                </a>
                                </td>
                            </tr>
                        <tr>
                            <td>العنوان:</td>
                            <td><?php echo $parcel['sender_address']; ?></td>
                        </tr>
                        <tr>
                            <td>المنطقة:</td>
                            <td><?php echo $parcel['sender_area_name']; ?> - <?php echo $parcel['sender_gov_name']; ?></td>
                        </tr>
                    </table>
                </div>
                </div>
                </div>

        <div class="col-md-6">
            <div class="content-section">
                <h5 class="section-header">المستلم</h5>
                <div class="section-body">
                    <table class="info-table">
                        <tr>
                            <td>الاسم:</td>
                            <td><?php echo $parcel['recipient_name']; ?></td>
                            </tr>
                            <tr>
                            <td>الهاتف:</td>
                            <td>
                                <a href="tel:<?php echo $parcel['recipient_phone']; ?>" class="phone-link">
                                    <?php echo $parcel['recipient_phone']; ?>
                                </a>
                            </td>
                            </tr>
                            <tr>
                            <td>العنوان:</td>
                            <td><?php echo $parcel['recipient_address']; ?></td>
                            </tr>
                        <tr>
                            <td>المنطقة:</td>
                            <td><?php echo $parcel['recipient_area_name']; ?> - <?php echo $parcel['recipient_gov_name']; ?></td>
                            </tr>
                    </table>
                </div>
            </div>
        </div>
                </div>
                
    <!-- الوكيل والمندوب -->
    <div class="row">
                    <div class="col-md-6">
            <div class="content-section">
                <h5 class="section-header">الوكيل</h5>
                <div class="section-body">
                    <?php if ($parcel['agent_company']): ?>
                        <table class="info-table">
                            <tr>
                                <td>اسم الشركة:</td>
                                <td><?php echo $parcel['agent_company']; ?></td>
                            </tr>
                            <tr>
                                <td>جهة الاتصال:</td>
                                <td><?php echo $parcel['agent_contact']; ?></td>
                            </tr>
                            <tr>
                                <td>نوع الشحنة:</td>
                                <td>
                                    <?php if ($parcel['shipment_direction'] == 'to_agent'): ?>
                                        إرسال إلى وكيل
                                <?php else: ?>
                                        استلام من وكيل
                                <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                                <?php else: ?>
                        <p class="text-muted">لا يوجد وكيل مُكلف</p>
                                <?php endif; ?>
                    </div>
                </div>
            </div>

                <div class="col-md-6">
            <div class="content-section">
                <h5 class="section-header">المندوب</h5>
                <div class="section-body">
                    <?php if ($parcel['courier_name']): ?>
                        <table class="info-table">
                            <tr>
                                <td>اسم المندوب:</td>
                                <td><?php echo $parcel['courier_name']; ?></td>
                            </tr>
                            <tr>
                                <td>رقم الهاتف:</td>
                                <td>
                                    <a href="tel:<?php echo $parcel['courier_phone']; ?>" class="phone-link">
                                        <?php echo $parcel['courier_phone']; ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
                                <?php else: ?>
                        <p class="text-muted">لا يوجد مندوب مُكلف</p>
                                <?php endif; ?>
                        </div>
                    </div>
                </div>
                </div>

    <!-- تاريخ الحالات -->
    <?php if ($history_result->num_rows > 0): ?>
    <div class="content-section">
        <h5 class="section-header">آخر تحديثات الشحنة</h5>
        <div class="section-body">
            <div class="timeline">
                <?php while($history = $history_result->fetch_assoc()): ?>
                <div class="timeline-item">
                    <div class="d-flex justify-content-between align-items-start">
            <div>
                            <strong>
                                <?php if ($history['old_status_name']): ?>
                                    <?php echo $history['old_status_name']; ?> ← 
                                <?php endif; ?>
                                <?php echo $history['new_status_name']; ?>
                            </strong>
                            <?php if ($history['reason_text']): ?>
                                <div class="text-muted small mt-1">
                                    السبب: <?php echo $history['reason_text']; ?>
            </div>
                            <?php endif; ?>
                            <?php if ($history['reason_note']): ?>
                                <div class="small mt-1">
                                    ملاحظة: <?php echo $history['reason_note']; ?>
                </div>
                            <?php endif; ?>
                            <small class="text-muted">
                                بواسطة: <?php echo $history['changed_by']; ?>
                            </small>
            </div>
                        <small class="text-muted">
                            <?php echo date('Y-m-d H:i', strtotime($history['changed_at'])); ?>
                        </small>
        </div>
        </div>
                <?php endwhile; ?>
        </div>
        </div>
        </div>
    <?php endif; ?>
    </div>

    <!-- البيانات المالية -->
    <div class="content-section">
        <h5 class="section-header">البيانات المالية</h5>
        <div class="section-body">
            <!-- القيم الأساسية (وفق المنطق الموحّد) -->
            <div class="financial-grid">
                <div class="financial-item">
                    <div class="financial-value text-primary"><?php echo number_format($total_to_collect_display, 2); ?> ج.م</div>
                    <div class="financial-label">إجمالي المطلوب من المستلم</div>
                    </div>
                <div class="financial-item">
                    <div class="financial-value text-info"><?php echo number_format($parcel['shipping_fees'], 2); ?> ج.م</div>
                    <div class="financial-label">رسوم الشحن</div>
                    </div>
                <div class="financial-item">
                    <div class="financial-value text-success"><?php echo number_format($paid_amount, 2); ?> ج.م</div>
                    <div class="financial-label">صافي المدفوع</div>
                    </div>
                <div class="financial-item">
                    <div class="financial-value text-warning"><?php echo number_format($remaining_display, 2); ?> ج.م</div>
                    <div class="financial-label">المتبقي للتحصيل</div>
                </div>
            </div>

            <!-- تفاصيل مالية إضافية -->
            <table class="info-table">
                <tr>
                    <td>عمولة الوكيل:</td>
                    <td><?php echo number_format($parcel['agent_share'], 2); ?> ج.م</td>
                            </tr>
                <tr>
                    <td>عمولة المندوب:</td>
                    <td><?php echo number_format($parcel['delivery_agent_fee'], 2); ?> ج.م</td>
                            </tr>
                <tr>
                    <td>إجمالي المبلغ (COD + شحن إن كان على المستلم):</td>
                    <td><?php echo number_format($total_to_collect_display, 2); ?> ج.م</td>
                            </tr>
                <tr>
                    <td>المبلغ الصافي المُحصَّل:</td>
                    <td><?php echo number_format($paid_amount, 2); ?> ج.م</td>
                            </tr>
                <tr>
                    <td><strong>ربح الشركة:</strong></td>
                    <td><strong><?php echo number_format($parcel['company_profit'], 2); ?> ج.م</strong></td>
                            </tr>
                    </table>

            <div class="mt-3">
                <small class="text-muted">
                    من يتحمل الشحن: 
                    <strong><?php echo $parcel['shipping_payer'] == 'sender' ? 'الراسل' : 'المستلم'; ?></strong>
                </small>
                <br>
                <small class="text-muted">
                    قاعدة احتساب صافي المدفوع:
                    <?php if ($shipment_direction === 'from_agent'): ?>
                        صافي كل دفعة = المبلغ − عمولة المندوب
                    <?php elseif ($shipment_direction === 'to_agent' && $shipping_payer === 'sender'): ?>
                        صافي كل دفعة = المبلغ − رسوم الشحن
                    <?php else: ?>
                        صافي كل دفعة = المبلغ كما هو
                    <?php endif; ?>
                </small>
            </div>
        </div>
                </div>

    <!-- ملاحظات -->
    <?php if ($parcel['note'] || $parcel['financial_notes']): ?>
    <div class="content-section">
        <h5 class="section-header">ملاحظات</h5>
        <div class="section-body">
            <?php if ($parcel['note']): ?>
                <div class="mb-3">
                    <strong>ملاحظة عامة:</strong>
                    <p class="mb-0 mt-1"><?php echo nl2br($parcel['note']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($parcel['financial_notes']): ?>
                <div>
                    <strong>ملاحظة مالية:</strong>
                    <p class="mb-0 mt-1"><?php echo nl2br($parcel['financial_notes']); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
                </div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-dot {
    position: absolute;
    left: -23px;
    top: 5px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    margin-left: 20px;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
}

.card {
    transition: all 0.3s ease;
}

.status-note {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 6px;
    border-left: 3px solid #007bff;
    font-style: italic;
    color: #495057;
    white-space: pre-wrap;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.bg-light {
    background-color: #f8f9fa !important;
}

@media print {
    .card:hover {
        transform: none;
        box-shadow: none !important;
    }
}
</style>

<?php if ($print_mode): ?>
</body>
</html>
<?php endif; ?>

<?php if ($is_modal_view): ?>
<script>
// دالة مشاركة واتساب محسنة
function shareWhatsApp() {
    // جمع البيانات المطلوبة
    const senderName = "<?php echo addslashes($parcel['sender_name']); ?>";
    const senderPhone = "<?php echo addslashes($parcel['sender_phone']); ?>";
    const recipientName = "<?php echo addslashes($parcel['recipient_name']); ?>";
    const recipientPhone = "<?php echo addslashes($parcel['recipient_phone']); ?>";
    const recipientAddress = "<?php echo addslashes($parcel['recipient_address']); ?>";
    const codAmount = "<?php echo number_format($parcel['cod_amount'], 2); ?>";
    const statusName = "<?php echo addslashes($parcel['status_name']); ?>";
    const statusReason = "<?php echo addslashes($parcel['status_reason']); ?>";
    const statusReasonNote = "<?php echo addslashes($parcel['status_reason_note']); ?>";
    const trackingNumber = "<?php echo addslashes($parcel['tracking_number']); ?>";
    
    // تكوين رسالة واتساب احترافية
    let message = `*تفاصيل الشحنة*\n\n`;
    message += `*رقم التتبع:* ${trackingNumber}\n\n`;
    
    message += `*بيانات الراسل:*\n`;
    message += `الاسم: ${senderName}\n`;
    message += `الهاتف: ${senderPhone}\n\n`;
    
    message += `*بيانات المستلم:*\n`;
    message += `الاسم: ${recipientName}\n`;
    message += `الهاتف: ${recipientPhone}\n`;
    message += `العنوان: ${recipientAddress}\n\n`;
    
    message += `*إجمالي مبلغ التحصيل:* ${codAmount} ج.م\n\n`;
    
    message += `*الحالة الحالية:* ${statusName}\n`;
    
    // إضافة سبب الحالة إذا كان موجوداً
    if (statusReason && statusReason.trim() !== '') {
        message += `*سبب الحالة:* ${statusReason}\n`;
    }
    
    // إضافة ملاحظة السبب إذا كانت موجودة
    if (statusReasonNote && statusReasonNote.trim() !== '') {
        message += `*ملاحظة إضافية:* ${statusReasonNote}\n`;
    }

    // ترميز الرسالة للرابط
    const encodedMessage = encodeURIComponent(message);
    
    // إنشاء رابط واتساب
    const whatsappUrl = `https://wa.me/?text=${encodedMessage}`;
    
    // فتح واتساب في نافذة جديدة
    window.open(whatsappUrl, '_blank');
}

// دالة تحسين إغلاق المودال
$(document).ready(function() {
    // التأكد من أن زر الإغلاق يعمل
    $(document).on('click', '.close-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#parcelDetailsModal').modal('hide');
    });
    
    // إضافة معالج للزر العادي في الـ header أيضاً
    $(document).on('click', '[data-bs-dismiss="modal"]', function() {
        $('#parcelDetailsModal').modal('hide');
    });
});
    </script>
<?php endif; ?>