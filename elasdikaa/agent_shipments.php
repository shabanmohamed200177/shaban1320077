<?php
// agent_shipments.php

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

// تعريف مصفوفة حالات الشحنات الجديدة بناءً على parcel_status (1).sql
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

// بناء استعلام SQL لجلب الشحنات المرتبطة بالوكيل
// الشحنات المرتبطة بالوكيل هي تلك التي يكون فيها agent_id هو الوكيل نفسه
$query_parts = [];
$params = [];
$types = '';

// Default filter to show all shipments (-1) if no status is explicitly selected
$filter_status = isset($_GET['status']) ? intval($_GET['status']) : -1; 

// استخدام agent_id في الشرط
$query_parts[] = "p.agent_id = ?";
$params[] = $agent_id;
$types .= 'i';

// فلتر رقم الشحنة
$tracking_number = isset($_GET['tracking_number']) ? trim($_GET['tracking_number']) : '';
if (!empty($tracking_number)) {
    $query_parts[] = "p.tracking_number LIKE ?";
    $params[] = "%$tracking_number%";
    $types .= 's';
}

// فلتر البحث في الراسل
$sender_search = isset($_GET['sender_search']) ? trim($_GET['sender_search']) : '';
if (!empty($sender_search)) {
    $query_parts[] = "(p.sender_name LIKE ? OR p.sender_phone LIKE ?)";
    $params[] = "%$sender_search%";
    $params[] = "%$sender_search%";
    $types .= 'ss';
}

// فلتر البحث في المستلم
$recipient_search = isset($_GET['recipient_search']) ? trim($_GET['recipient_search']) : '';
if (!empty($recipient_search)) {
    $query_parts[] = "(p.recipient_name LIKE ? OR p.recipient_phone LIKE ?)";
    $params[] = "%$recipient_search%";
    $params[] = "%$recipient_search%";
    $types .= 'ss';
}

// فلتر حالة الشحنة
if ($filter_status > -1) { // Apply filter if it's not "All Statuses" (-1)
    $query_parts[] = "p.status = ?";
    $params[] = $filter_status;
    $types .= 'i';
}

// فلتر المحافظة
$governorate_id = isset($_GET['governorate_id']) ? intval($_GET['governorate_id']) : 0;
if ($governorate_id > 0) {
    $query_parts[] = "p.recipient_governorate_id = ?";
    $params[] = $governorate_id;
    $types .= 'i';
}

// فلتر المنطقة
$filter_area = isset($_GET['area']) ? trim($_GET['area']) : '';
if (!empty($filter_area)) {
    // البحث في منطقة المستلم
    $query_parts[] = "ar.name LIKE ?";
    $params[] = "%$filter_area%";
    $types .= 's';
}

// فلتر المندوب
$courier_id = isset($_GET['courier_id']) ? intval($_GET['courier_id']) : 0;
if ($courier_id > 0) {
    $query_parts[] = "p.courier_id = ?";
    $params[] = $courier_id;
    $types .= 'i';
}

// فلتر نوع الشحنة
$shipment_direction = isset($_GET['shipment_direction']) ? trim($_GET['shipment_direction']) : '';
if (!empty($shipment_direction)) {
    $query_parts[] = "p.shipment_direction = ?";
    $params[] = $shipment_direction;
    $types .= 's';
}

// فلتر التاريخ
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
if (!empty($start_date) && !empty($end_date)) {
    $query_parts[] = "DATE(p.date_created) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
}

// البحث العام
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search_term)) {
    // البحث في رقم المرجع أو اسم المستلم أو رقم التتبع
    $query_parts[] = "(p.reference_number LIKE ? OR p.recipient_name LIKE ? OR p.tracking_number LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $types .= 'sss';
}

// تعديل الاستعلام ليشمل ربط بجدول المناطق والحصول على اسم المنطقة
$query = "SELECT p.*, ar.name as recipient_area_name, g.name as governorate_name, c.name as courier_name
          FROM parcels p 
          LEFT JOIN areas ar ON p.recipient_area_id = ar.id 
          LEFT JOIN governorates g ON p.recipient_governorate_id = g.id
          LEFT JOIN couriers c ON p.courier_id = c.id
          WHERE " . implode(' AND ', $query_parts) . " ORDER BY p.date_created DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $shipments = $stmt->get_result();
} else {
    $shipments = null;
    echo '<div class="container mt-5"><div class="alert alert-danger text-center">خطأ في إعداد الاستعلام: ' . $conn->error . '</div></div>';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شحنات الوكيل</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; direction: rtl; background-color: #f4f7f6; }
        .card { border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: none; }
        .card-header { background-color: #17a2b8; color: white; border-radius: 1rem 1rem 0 0; padding: 1.5rem; text-align: center; }
        .table thead th { background-color: #17a2b8; color: #fff; border-color: #17a2b8; }
        .table tbody tr:hover { background-color: #e9ecef; }
        .form-control, .btn { border-radius: 0.5rem; }
        .filter-container { background-color: #e9ecef; border-radius: 1rem; padding: 20px; margin-bottom: 25px; }
        .btn-info { background-color: #17a2b8; border-color: #17a2b8; }
        .btn-info:hover { background-color: #138496; border-color: #138496; }
        .status-badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 50rem; font-weight: bold; }

        /* ألوان مخصصة لكل حالة (تم تحديثها لتتوافق مع الـ IDs الجديدة) */
        .status-1 { background-color: #6c757d; color: #fff; } /* قيد التنفيذ (رمادي) */
        .status-2 { background-color: #ffc107; color: #333; } /* تم تسليمها للمندوب (أصفر) */
        .status-3 { background-color: #0d6efd; color: #fff; } /* جاري التوصيل (أزرق) */
        .status-4 { background-color: #28a745; color: #fff; } /* تم التسليم بنجاح (أخضر) */
        .status-5 { background-color: #17a2b8; color: #fff; } /* تم الدفع بنجاح (أزرق سماوي) */
        .status-6 { background-color: #fd7e14; color: #fff; } /* تم الدفع جزئي (برتقالي) */
        .status-7 { background-color: #6f42c1; color: #fff; } /* تم تأجيل الطلب (بنفسجي) */
        .status-8 { background-color: #dc3545; color: #fff; } /* تم الرفض (أحمر) */
        .status-9 { background-color: #007bff; color: #fff; } /* المرتجعات (أزرق داكن) */
        .status-10 { background-color: #6610f2; color: #fff; } /* مرتجع للمخزن (بنفسجي داكن) */
        .status-11 { background-color: #20c997; color: #fff; } /* مرتجع للفرع (أخضر فاتح) */
        .status-12 { background-color: #e83e8c; color: #fff; } /* مرتجع للعميل (وردي) */
        
        .action-buttons .btn { margin-left: 5px; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .card-body { padding: 15px; }
            .filter-container { margin-bottom: 20px; }
            .col-md-2, .col-md-3 { margin-bottom: 15px; }
            .table-responsive { font-size: 12px; }
            .table th, .table td { padding: 6px 4px; }
            .btn { font-size: 12px; padding: 6px 12px; }
            .d-flex.justify-content-end { flex-direction: column; }
            .d-flex.justify-content-end .btn { margin-bottom: 10px; width: 100%; }
            .modal-dialog { margin: 10px; }
            .form-check { margin-bottom: 10px; }
        }

        @media (max-width: 576px) {
            .container { padding: 5px; }
            .card-body { padding: 10px; }
            .table-responsive { font-size: 11px; }
            .table th, .table td { padding: 4px 2px; }
            .btn { font-size: 11px; padding: 4px 8px; }
            .col-md-2, .col-md-3 { margin-bottom: 10px; }
            .filter-container .row { margin: 0; }
            .filter-container .col-md-2, .filter-container .col-md-3 { padding: 5px; }
        }

        @media (max-width: 480px) {
            .table-responsive { font-size: 10px; }
            .table th, .table td { padding: 3px 1px; }
            .btn { font-size: 10px; padding: 3px 6px; }
            .modal-dialog { margin: 5px; }
            .modal-body { padding: 15px; }
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #print-area, #print-area * {
                visibility: visible;
            }
            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
            }
            .table th, .table td {
                border: 1px solid #dee2e6;
                padding: 8px;
                text-align: right; /* Adjust for RTL */
            }
            .table thead th {
                background-color: #e9ecef;
                color: #000;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="py-4" style="max-width: 98%; margin: 0 auto;">
    <div class="bg-white p-3 rounded-3 mb-4">
        <div class="border-bottom pb-3 mb-3">
            <h4 class="mb-0"><i class="fas fa-boxes me-2"></i> شحنات الوكيل: <?php echo htmlspecialchars($agent_data['company_name']); ?></h4>
        </div>
        <div>
            <div class="filter-container">
                <form id="filterForm" class="row g-3" method="GET">
                    <input type="hidden" name="page" value="agent_shipments">
                    <input type="hidden" name="id" value="<?php echo $agent_id; ?>">
                    
                    <!-- الصف الأول من الفلاتر -->
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <label for="search_input" class="form-label">بحث سريع</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search_input" name="search" placeholder="رقم التتبع، المرجع أو اسم المستلم" value="<?php echo htmlspecialchars($search_term); ?>">
                            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label for="tracking_number" class="form-label">رقم الشحنة</label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="رقم الشحنة" value="<?php echo htmlspecialchars($_GET['tracking_number'] ?? ''); ?>">
                    </div>

                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label for="sender_search" class="form-label">الراسل (الاسم/الهاتف)</label>
                        <input type="text" class="form-control" id="sender_search" name="sender_search" placeholder="اسم أو هاتف الراسل" value="<?php echo htmlspecialchars($_GET['sender_search'] ?? ''); ?>">
                    </div>

                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label for="recipient_search" class="form-label">المستلم (الاسم/الهاتف)</label>
                        <input type="text" class="form-control" id="recipient_search" name="recipient_search" placeholder="اسم أو هاتف المستلم" value="<?php echo htmlspecialchars($_GET['recipient_search'] ?? ''); ?>">
                    </div>

                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <label for="status_select" class="form-label">حالة الشحنة</label>
                        <select class="form-select" id="status_select" name="status">
                            <option value="-1" <?php echo $filter_status == -1 ? 'selected' : ''; ?>>جميع الحالات</option>
                            <?php foreach($status_arr as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filter_status == $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- الصف الثاني من الفلاتر -->
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <label for="governorate_filter" class="form-label">المحافظة</label>
                        <select class="form-select" id="governorate_filter" name="governorate_id">
                            <option value="">كل المحافظات</option>
                            <?php
                            $governorates = $conn->query("SELECT * FROM governorates ORDER BY name");
                            while($gov = $governorates->fetch_assoc()):
                                $selected = (isset($_GET['governorate_id']) && $_GET['governorate_id'] == $gov['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $gov['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($gov['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <label for="area_input" class="form-label">المنطقة</label>
                        <input type="text" class="form-control" id="area_input" name="area" placeholder="اسم المنطقة" value="<?php echo htmlspecialchars($filter_area); ?>">
                    </div>

                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <label for="courier_filter" class="form-label">المندوب</label>
                        <select class="form-select" id="courier_filter" name="courier_id">
                            <option value="">كل المندوبين</option>
                            <?php
                            $couriers = $conn->query("SELECT * FROM couriers ORDER BY name");
                            while($courier = $couriers->fetch_assoc()):
                                $selected = (isset($_GET['courier_id']) && $_GET['courier_id'] == $courier['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $courier['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($courier['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <label for="shipment_direction_filter" class="form-label">نوع الشحنة</label>
                        <select class="form-select" id="shipment_direction_filter" name="shipment_direction">
                            <option value="">كل الأنواع</option>
                            <option value="to_agent" <?php echo (isset($_GET['shipment_direction']) && $_GET['shipment_direction'] == 'to_agent') ? 'selected' : ''; ?>>إرسال إلى وكيل</option>
                            <option value="from_agent" <?php echo (isset($_GET['shipment_direction']) && $_GET['shipment_direction'] == 'from_agent') ? 'selected' : ''; ?>>استلام من وكيل</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex align-items-end flex-wrap">
                        <button type="submit" class="btn btn-info me-2 mb-2"><i class="fas fa-filter me-2"></i> تطبيق الفلاتر</button>
                        <button type="button" class="btn btn-secondary mb-2" onclick="clearFilters()"><i class="fas fa-times me-2"></i> مسح الفلاتر</button>
                    </div>
                </form>
                
                <hr>
                
                <!-- فلترة التاريخ -->
                <form id="dateFilterForm" class="row g-3" method="GET">
                    <input type="hidden" name="page" value="agent_shipments">
                    <input type="hidden" name="id" value="<?php echo $agent_id; ?>">
                    <!-- نقل جميع الفلاتر الحالية كـ hidden inputs -->
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                    <input type="hidden" name="tracking_number" value="<?php echo htmlspecialchars($_GET['tracking_number'] ?? ''); ?>">
                    <input type="hidden" name="sender_search" value="<?php echo htmlspecialchars($_GET['sender_search'] ?? ''); ?>">
                    <input type="hidden" name="recipient_search" value="<?php echo htmlspecialchars($_GET['recipient_search'] ?? ''); ?>">
                    <input type="hidden" name="status" value="<?php echo $filter_status; ?>">
                    <input type="hidden" name="governorate_id" value="<?php echo htmlspecialchars($_GET['governorate_id'] ?? ''); ?>">
                    <input type="hidden" name="area" value="<?php echo htmlspecialchars($filter_area); ?>">
                    <input type="hidden" name="courier_id" value="<?php echo htmlspecialchars($_GET['courier_id'] ?? ''); ?>">
                    <input type="hidden" name="shipment_direction" value="<?php echo htmlspecialchars($_GET['shipment_direction'] ?? ''); ?>">

                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <label for="start_date_input" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="start_date_input" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>

                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <label for="end_date_input" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="end_date_input" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="col-lg-4 col-md-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-info w-100"><i class="fas fa-calendar-alt me-2"></i> تصفية بالتاريخ</button>
                    </div>
                </form>
            </div>
            
            <?php if($shipments && $shipments->num_rows > 0): ?>
            <div class="d-flex justify-content-end mb-3 no-print">
                <button class="btn btn-success me-2" onclick="showExcelExportModal()"><i class="fas fa-file-excel me-2"></i> تصدير Excel</button>
                <button class="btn btn-primary" onclick="showPrintModal()"><i class="fas fa-print me-2"></i> طباعة</button>
                <button class="btn btn-warning ms-2" onclick="updateMultipleStatus()"><i class="fas fa-edit me-2"></i> تحديث الحالة المحددة</button>
            </div>

            <!-- نافذة اختيار أعمدة الطباعة -->
            <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="printModalLabel">اختر الأعمدة للطباعة</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                اختر الأعمدة التي تريد طباعتها
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <h6 class="mb-3">معلومات الشحنة:</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_tracking" value="tracking" checked>
                                        <label class="form-check-label" for="print_col_tracking">رقم الشحنة</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_sender_name" value="sender_name" checked>
                                        <label class="form-check-label" for="print_col_sender_name">اسم الراسل</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_sender_phone" value="sender_phone" checked>
                                        <label class="form-check-label" for="print_col_sender_phone">رقم الراسل</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_recipient_name" value="recipient_name" checked>
                                        <label class="form-check-label" for="print_col_recipient_name">اسم المستلم</label>
                                    </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="print_col_recipient_phone" value="recipient_phone" checked>
                                <label class="form-check-label" for="print_col_recipient_phone">رقم المستلم</label>
                            </div>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-3">معلومات التوصيل:</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_governorate" value="governorate" checked>
                                        <label class="form-check-label" for="print_col_governorate">المحافظة</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_area" value="area" checked>
                                        <label class="form-check-label" for="print_col_area">المنطقة</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_shipment_type" value="shipment_type" checked>
                                        <label class="form-check-label" for="print_col_shipment_type">نوع الشحنة</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_courier" value="courier" checked>
                                        <label class="form-check-label" for="print_col_courier">المندوب</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_status" value="status" checked>
                                        <label class="form-check-label" for="print_col_status">حالة الشحنة</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-3">المعلومات المالية:</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_cod" value="cod" checked>
                                        <label class="form-check-label" for="print_col_cod">القيمة (COD)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_commission" value="commission" checked>
                                        <label class="form-check-label" for="print_col_commission">العمولة</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_total" value="total" checked>
                                        <label class="form-check-label" for="print_col_total">إجمالي المستحق</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="print_col_date" value="date" checked>
                                        <label class="form-check-label" for="print_col_date">التاريخ</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPrintColumns()">تحديد الكل</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllPrintColumns()">إلغاء تحديد الكل</button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="button" class="btn btn-success" onclick="printSelectedColumns()">
                                <i class="fas fa-print"></i> طباعة
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive" id="shipmentsTableContainer">
                <table class="table table-bordered table-hover" id="shipmentsTable">
                    <thead>
                        <tr>
                            <th class="no-print">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>رقم الشحنة</th>
                            <th>بيانات الراسل</th>
                            <th>اسم المستلم</th>
                            <th>المحافظة</th>
                            <th>المنطقة</th>
                            <th>نوع الشحنة</th>
                            <th>المندوب</th>
                            <th>الحالة</th>
                            <th>القيمة</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>التاريخ</th>
                            <th class="no-print">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1; 
                        $total_shipments = 0;
                        $total_cod_value = 0;      // إجمالي القيمة المعروضة (المطلوب عند الباب)
                        $total_paid_value = 0;     // إجمالي المدفوع الصافي
                        $total_remaining_value = 0;// إجمالي المتبقي
                        
                        while($row = $shipments->fetch_assoc()): 
                            // حساب إجمالي المستحق بناءً على نوع الشحنة
                            $shipment_direction = $row['shipment_direction'] ?? 'to_agent';
                            $cod_amount = floatval($row['cod_amount'] ?? 0);
                            $shipping_fees = floatval($row['shipping_fees'] ?? 0);
                            $shipping_payer = (string)($row['shipping_payer'] ?? 'sender');
                            // إجمالي المطلوب من المستلم عند الباب
                            $total_display = $cod_amount;
                            if ($shipment_direction === 'to_agent' && $shipping_payer === 'recipient') {
                                $total_display += $shipping_fees;
                            }
                            // المدفوع الصافي (حسب النظام الجديد)
                            $paid_amount = floatval($row['paid_amount'] ?? 0);
                            // المتبقي
                            $remaining_display = max(0, $total_display - $paid_amount);

                            // تحديد نوع الشحنة بالعربية
                            $shipment_type = $shipment_direction === 'to_agent' ? 'إرسال إلى وكيل' : 'استلام من وكيل';
                            
                            // حساب الإجماليات
                            $total_shipments++;
                            $total_cod_value += $total_display;
                            $total_paid_value += $paid_amount;
                            $total_remaining_value += $remaining_display;
                        ?>
                        <tr>
                            <td class="no-print">
                                <input type="checkbox" class="form-check-input shipment-checkbox" value="<?php echo $row['id']; ?>">
                            </td>
                            <td><strong><?php echo htmlspecialchars($row['tracking_number'] ?? ''); ?></strong></td>
                            <td>
                                <div><strong><?php echo htmlspecialchars($row['sender_name'] ?? ''); ?></strong></div>
                                <div class="text-muted"><?php echo htmlspecialchars($row['sender_phone'] ?? ''); ?></div>
                            </td>
                            <td>
                                <div><strong><?php echo htmlspecialchars($row['recipient_name'] ?? ''); ?></strong></div>
                                <div class="text-muted"><?php echo htmlspecialchars($row['recipient_phone'] ?? ''); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($row['governorate_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['recipient_area_name'] ?? ''); ?></td>
                            <td><?php echo $shipment_type; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['courier_name'] ?? 'لا يوجد مندوب'); ?>
                            </td>
                            <td>
                                <?php
                                $status = $row['status'] ?? 0;
                                echo '<span class="badge status-badge status-'.htmlspecialchars($status).'">'.htmlspecialchars($status_arr[$status] ?? 'غير معروف').'</span>';
                                ?>
                            </td>
                            <td><?php echo number_format($total_display, 2); ?> جنيه</td>
                            <td class="text-success"><?php echo number_format($paid_amount, 2); ?> جنيه</td>
                            <td class="text-warning"><?php echo number_format($remaining_display, 2); ?> جنيه</td>
                            <td><?php echo date('Y-m-d', strtotime($row['date_created'] ?? '')); ?></td>
                            <td class="action-buttons no-print">
                                <a href="index.php?page=parcel_details&id=<?php echo $row['id'] ?? ''; ?>" class="btn btn-sm btn-info" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                    </tbody>
                </table>
            </div>
            
            <!-- جدول الإجماليات المنفصل -->
            <?php if($shipments && $shipments->num_rows > 0): ?>
            <div class="mt-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i> ملخص الإجماليات</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" style="width: 100%;">
                                <thead class="table-success">
                                    <tr>
                                        <th style="width: 25%; text-align: center;">إجمالي عدد الشحنات</th>
                                        <th style="width: 25%; text-align: center;">إجمالي القيمة</th>
                                        <th style="width: 25%; text-align: center;">إجمالي المدفوع</th>
                                        <th style="width: 25%; text-align: center;">إجمالي المتبقي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-warning">
                                        <td style="text-align: center;">
                                            <strong class="text-primary fs-4"><?php echo number_format($total_shipments); ?> شحنة</strong>
                            </td>
                                        <td style="text-align: center;">
                                            <strong class="text-success fs-4"><?php echo number_format($total_cod_value, 2); ?> جنيه</strong>
                            </td>
                                        <td style="text-align: center;">
                                            <strong class="text-info fs-4"><?php echo number_format($total_paid_value, 2); ?> جنيه</strong>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong class="text-warning fs-4"><?php echo number_format($total_remaining_value, 2); ?> جنيه</strong>
                                        </td>
                        </tr>
                    </tbody>
                </table>
            </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="alert alert-info text-center">لا توجد شحنات مطابقة لخيارات البحث.</div>
            <?php endif; ?>

            <div class="text-center mt-4 no-print">
                <a href="index.php?page=agent_profile&id=<?php echo $agent_id; ?>" class="btn btn-outline-secondary mx-1"><i class="fas fa-arrow-right"></i> العودة لملف الوكيل</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editShipmentModal" tabindex="-1" aria-labelledby="editShipmentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editShipmentModalLabel">تعديل الشحنة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editShipmentForm">
          <input type="hidden" id="edit_shipment_id" name="id">
          <div class="mb-3">
            <label for="edit_reference" class="form-label">المرجع</label>
            <input type="text" class="form-control" id="edit_reference" name="reference_number" required>
          </div>
          <div class="mb-3">
            <label for="edit_recipient" class="form-label">اسم المستلم</label>
            <input type="text" class="form-control" id="edit_recipient" name="recipient_name" required>
          </div>
          <div class="mb-3">
            <label for="edit_area" class="form-label">المنطقة</label>
            <input type="text" class="form-control" id="edit_area" name="to_area" required>
          </div>
          <div class="mb-3">
            <label for="edit_cod_amount" class="form-label">مبلغ COD</label>
            <input type="number" class="form-control" id="edit_cod_amount" name="cod_amount" step="0.01" required>
          </div>
          <div class="mb-3">
            <label for="edit_shipping_fees" class="form-label">تكلفة الشحن</label>
            <input type="number" class="form-control" id="edit_shipping_fees" name="shipping_fees" step="0.01" required>
          </div>
          <div class="mb-3">
            <label for="edit_shipping_payer" class="form-label">دافع الشحن</label>
            <select class="form-select" id="edit_shipping_payer" name="shipping_payer" required>
                <option value="sender">المرسل</option>
                <option value="recipient">المستلم</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="edit_status" class="form-label">الحالة</label>
            <select class="form-select" id="edit_status" name="status" required>
                <?php foreach($status_arr as $key => $value): ?>
                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-100">حفظ التغييرات</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Custom Confirmation Modal (replaces default confirm) -->
<div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="customConfirmModalLabel"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="customConfirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">تأكيد</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Temporary Message Modal -->
<div class="modal fade" id="temporaryMessageModal" tabindex="-1" aria-labelledby="temporaryMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header text-white">
                <h5 class="modal-title" id="temporaryMessageModalLabel"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- Multiple Status Update Modal -->
<div class="modal fade" id="multipleStatusModal" tabindex="-1" aria-labelledby="multipleStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="multipleStatusModalLabel">
                    <i class="fas fa-edit"></i> تحديث حالة الشحنات المحددة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    تم تحديد <span id="selectedCount">0</span> شحنة للتحديث
                </div>
                <form id="multipleStatusForm">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">الحالة الجديدة</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="">اختر الحالة الجديدة</option>
                            <?php foreach($status_arr as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-warning" onclick="confirmMultipleStatusUpdate()">
                    <i class="fas fa-save"></i> تحديث الحالة
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Excel Export Column Selection Modal -->
<div class="modal fade" id="excelExportModal" tabindex="-1" aria-labelledby="excelExportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="excelExportModalLabel">
                    <i class="fas fa-file-excel"></i> تصدير إلى Excel
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    اختر الأعمدة التي تريد تصديرها إلى ملف Excel
                </div>
                <div class="row">
                                            <div class="col-md-4">
                            <h6 class="mb-3">معلومات الشحنة:</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_tracking" value="tracking" checked>
                                <label class="form-check-label" for="col_tracking">رقم الشحنة</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_sender_name" value="sender_name" checked>
                                <label class="form-check-label" for="col_sender_name">اسم الراسل</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_sender_phone" value="sender_phone" checked>
                                <label class="form-check-label" for="col_sender_phone">رقم الراسل</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_recipient_name" value="recipient_name" checked>
                                <label class="form-check-label" for="col_recipient_name">اسم المستلم</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_recipient_phone" value="recipient_phone" checked>
                                <label class="form-check-label" for="col_recipient_phone">رقم المستلم</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-3">معلومات التوصيل:</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_governorate" value="governorate" checked>
                                <label class="form-check-label" for="col_governorate">المحافظة</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_area" value="area" checked>
                                <label class="form-check-label" for="col_area">المنطقة</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_shipment_type" value="shipment_type" checked>
                                <label class="form-check-label" for="col_shipment_type">نوع الشحنة</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_courier" value="courier" checked>
                                <label class="form-check-label" for="col_courier">المندوب</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_status" value="status" checked>
                                <label class="form-check-label" for="col_status">حالة الشحنة</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-3">المعلومات المالية:</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_cod" value="cod" checked>
                                <label class="form-check-label" for="col_cod">القيمة (COD)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_commission" value="commission" checked>
                                <label class="form-check-label" for="col_commission">العمولة</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_total" value="total" checked>
                                <label class="form-check-label" for="col_total">إجمالي المستحق</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="col_date" value="date" checked>
                                <label class="form-check-label" for="col_date">التاريخ</label>
                            </div>
                        </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllColumns()">تحديد الكل</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllColumns()">إلغاء تحديد الكل</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" onclick="exportSelectedColumns()">
                    <i class="fas fa-download"></i> تصدير Excel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media (min-width: 1200px) {
    .table-responsive {
        overflow-x: visible;
    }
}

.table th {
    white-space: nowrap;
    background-color: #f8f9fa;
}

.table td {
    vertical-align: middle;
}

.filter-container {
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.btn-group-sm > .btn, .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

.status-badge {
    font-size: 0.875rem;
    padding: 0.35em 0.65em;
    font-weight: 600;
}

.text-muted {
    font-size: 0.875rem;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Function to show temporary modal messages
function showTemporaryModal(title, message, type = 'info') {
    var headerClass = 'bg-info';
    if (type === 'warning') headerClass = 'bg-warning text-dark';
    if (type === 'error') headerClass = 'bg-danger';
    if (type === 'success') headerClass = 'bg-success';
    
    $('#temporaryMessageModal .modal-header').removeClass('bg-info bg-warning bg-danger bg-success text-dark').addClass(headerClass);
    $('#temporaryMessageModalLabel').text(title);
    $('#temporaryMessageModal .modal-body p').text(message);
    $('#temporaryMessageModal').modal('show');
}

// Custom Confirmation Modal (replaces default confirm)
function showConfirmModal(title, message, callback) {
    // Remove any existing modal instance to prevent duplicates
    $('#customConfirmModal').remove();
    const modalHtml = `
        <div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="customConfirmModalLabel">${title}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="customConfirmMessage">${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="button" class="btn btn-primary" id="confirmActionBtn">تأكيد</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    $('body').append(modalHtml);
    const confirmModal = new bootstrap.Modal(document.getElementById('customConfirmModal'));
    confirmModal.show();

    $('#confirmActionBtn').off('click').on('click', function() {
        confirmModal.hide();
        callback();
    });

    $('#customConfirmModal').on('hidden.bs.modal', function () {
        $(this).remove(); // Clean up modal from DOM after hiding
    });
}

// Custom Temporary Message Modal
function showTemporaryModal(title, message, type) {
    // Remove any existing modal instance to prevent duplicates
    $('#temporaryMessageModal').remove();
    const modalHtml = `
        <div class="modal fade" id="temporaryMessageModal" tabindex="-1" aria-labelledby="temporaryMessageModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header text-white">
                        <h5 class="modal-title" id="temporaryMessageModalLabel">${title}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    $('body').append(modalHtml);
    const tempModal = new bootstrap.Modal(document.getElementById('temporaryMessageModal'));
    tempModal.show();
    $('#temporaryMessageModal').on('hidden.bs.modal', function () {
        $(this).remove(); // Clean up modal from DOM after hiding
    });
    // Set header background color based on type
    $('#temporaryMessageModal .modal-header').removeClass('bg-success bg-danger bg-warning bg-info bg-primary').addClass(`bg-${type}`);
}


// عند الضغط على زر التعديل، قم بملء النموذج المنبثق بالبيانات
$(document).on('click', '.edit-btn', function() {
    $('#edit_shipment_id').val($(this).data('id'));
    $('#edit_reference').val($(this).data('reference'));
    $('#edit_recipient').val($(this).data('recipient'));
    $('#edit_area').val($(this).data('area'));
    $('#edit_cod_amount').val($(this).data('cod')); // استخدام cod_amount
    $('#edit_shipping_fees').val($(this).data('shippingfees')); // استخدام shipping_fees
    $('#edit_shipping_payer').val($(this).data('shippingpayer')); // استخدام shipping_payer
    $('#edit_status').val($(this).data('status'));
});

// عند إرسال نموذج التعديل داخل النافذة المنبثقة
$('#editShipmentForm').on('submit', function(e) {
    e.preventDefault();
    
    var formData = $(this).serialize();

    showConfirmModal('تأكيد الحفظ', 'هل أنت متأكد من حفظ التغييرات على هذه الشحنة؟', function() {
        $.ajax({
            url: 'update_parcel_data.php', // اسم الملف الذي سيعالج التحديث
            type: 'POST',
            data: formData,
            success: function(response) {
                // Assuming update_parcel_data.php returns plain text or JSON
                // If it returns plain text, you might need to parse it or check its content
                if (response.includes("تم تحديث الشحنة بنجاح")) { // Simple text check
                    showTemporaryModal('نجاح', response, 'success');
                    location.reload(); // إعادة تحميل الصفحة لرؤية التغييرات
                } else {
                    showTemporaryModal('خطأ', response, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showTemporaryModal('خطأ', "حدث خطأ أثناء حفظ التغييرات: " + error, 'danger');
            }
        });
    });
});

// كود تحديث الحالة القديم (لا يزال يعمل) - يجب التأكد من أن هذا يتصل بـ update_parcel_status.php
$(document).on('click', '.update-status', function(e) {
    e.preventDefault();
    var parcelId = $(this).data('id');
    var newStatus = $(this).data('status'); // This will be the new status (e.g., 4 for delivered)
    showConfirmModal('تأكيد تغيير الحالة', 'هل أنت متأكد من تغيير حالة هذه الشحنة؟', function() {
        $.ajax({
            url: 'ajax.php?action=update_parcel_status', // Changed to ajax.php
            type: 'POST',
            data: { id: parcelId, status_id: newStatus, remarks: 'تحديث سريع للحالة' }, // Pass remarks
            dataType: 'json', // Expect JSON response
            success: function(response) {
                if (response.status === 'success') { // Assuming ajax.php returns JSON with status
                    showTemporaryModal('نجاح', response.message, 'success');
                    location.reload();
                } else {
                    showTemporaryModal('خطأ', response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showTemporaryModal('خطأ', "حدث خطأ: " + error, 'danger');
            }
        });
    });
});

// Export Table to Excel
function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel';
    var tableSelect = document.getElementById(tableID);
    // Clone the table to remove the "Actions" column before export
    var clonedTable = tableSelect.cloneNode(true);
    // Remove the "الإجراءات" column (last th in header, last td in each row)
    $(clonedTable).find('th.no-print, td.no-print').remove();

    var tableHTML = clonedTable.outerHTML.replace(/ /g, '%20');
    
    // Specify file name
    filename = filename?filename+'.xls':'excel_data.xls';
    
    // Create download link element
    downloadLink = document.createElement("a");
    
    document.body.appendChild(downloadLink);
    
    if(navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', tableHTML], {
            type: dataType
        });
        navigator.msSaveOrOpenBlob( blob, filename);
    }else{
        // Create a link to the file with proper encoding
        var encodedData = encodeURIComponent(tableHTML);
        downloadLink.href = 'data:' + dataType + ';charset=utf-8,' + encodedData;
    
        // Setting the file name
        downloadLink.download = filename;
        
        //triggering the function
        downloadLink.click();
    }
}

// Print Table
function printDiv(containerId) {
    var printContents = document.getElementById(containerId).innerHTML;
    var originalContents = document.body.innerHTML;

    // Create a new window for printing to avoid issues with current page layout
    var printWindow = window.open('', '', 'height=700,width=900');
    printWindow.document.write('<html><head><title>طباعة الشحنات</title>');
    // Include the necessary CSS for printing
    printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        body { font-family: 'Tajawal', Arial, sans-serif; direction: rtl; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #dee2e6; padding: 8px; text-align: right; }
        thead th { background-color: #e9ecef; color: #000; }
        .no-print { display: none !important; }
        .status-badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 50rem; font-weight: bold; }
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
    `);
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h4 style="text-align: center;">تقرير شحنات الوكيل: <?php echo htmlspecialchars($agent_data['company_name']); ?></h4>');
    printWindow.document.write(printContents); // This will contain the table
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}

// Function to clear filters
function clearFilters() {
    window.location.href = window.location.pathname + '?id=<?php echo $agent_id; ?>&page=agent_shipments';
}

// Select All functionality
$(document).on('change', '#selectAll', function() {
    $('.shipment-checkbox').prop('checked', $(this).is(':checked'));
    updateSelectedCount();
});

// Individual checkbox change
$(document).on('change', '.shipment-checkbox', function() {
    updateSelectedCount();
    // Update select all checkbox
    var totalCheckboxes = $('.shipment-checkbox').length;
    var checkedCheckboxes = $('.shipment-checkbox:checked').length;
    $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
});

// Update selected count
function updateSelectedCount() {
    var count = $('.shipment-checkbox:checked').length;
    $('#selectedCount').text(count);
}

// Update multiple status function
function updateMultipleStatus() {
    var selectedShipments = $('.shipment-checkbox:checked');
    if (selectedShipments.length === 0) {
        showTemporaryModal('تنبيه', 'الرجاء تحديد شحنات للتحديث', 'warning');
        return;
    }
    
    $('#selectedCount').text(selectedShipments.length);
    $('#multipleStatusModal').modal('show');
}

// Confirm multiple status update
function confirmMultipleStatusUpdate() {
    var newStatus = $('#new_status').val();
    
    if (!newStatus) {
        showTemporaryModal('خطأ', 'الرجاء اختيار الحالة الجديدة', 'danger');
        return;
    }
    
    var selectedShipments = [];
    $('.shipment-checkbox:checked').each(function() {
        selectedShipments.push($(this).val());
    });
    
    showConfirmModal('تأكيد التحديث', 'هل أنت متأكد من تحديث حالة ' + selectedShipments.length + ' شحنة؟', function() {
        $.ajax({
            url: 'ajax.php?action=update_multiple_parcel_status',
            type: 'POST',
                            data: {
                    shipment_ids: selectedShipments,
                    new_status: newStatus
                },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    showTemporaryModal('نجاح', response.message, 'success');
                    location.reload();
                } else {
                    showTemporaryModal('خطأ', response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showTemporaryModal('خطأ', 'حدث خطأ أثناء تحديث الحالة: ' + error, 'danger');
            }
        });
    });
}

// Professional Excel export with column selection
function showExcelExportModal() {
    $('#excelExportModal').modal('show');
}

function selectAllColumns() {
    $('.form-check-input[type="checkbox"]').prop('checked', true);
}

function deselectAllColumns() {
    $('.form-check-input[type="checkbox"]').prop('checked', false);
}

// Select all shipments checkbox functionality
$(document).ready(function() {
    $('#selectAll').change(function() {
        $('.shipment-checkbox').prop('checked', $(this).is(':checked'));
    });
    
    // Update select all checkbox when individual checkboxes change
    $('.shipment-checkbox').change(function() {
        var totalCheckboxes = $('.shipment-checkbox').length;
        var checkedCheckboxes = $('.shipment-checkbox:checked').length;
        
        if (checkedCheckboxes === 0) {
            $('#selectAll').prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#selectAll').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#selectAll').prop('indeterminate', true);
        }
    });
});

function exportSelectedColumns() {
    var selectedColumns = [];
    $('.form-check-input[type="checkbox"]:checked').each(function() {
        var columnValue = $(this).val();
        // Ensure no duplicate columns are added
        if (selectedColumns.indexOf(columnValue) === -1) {
            selectedColumns.push(columnValue);
        }
    });
    
    if (selectedColumns.length === 0) {
        showTemporaryModal('تنبيه', 'الرجاء اختيار عمود واحد على الأقل للتصدير', 'warning');
        return;
    }
    
    // Debug: Log selected columns
    console.log('Selected columns for export:', selectedColumns);
    
    // Create a new table with only selected columns
    var originalTable = document.getElementById('shipmentsTable');
    
    // Get all header cells from original table
    var originalHeaders = originalTable.querySelectorAll('thead tr th');
    var originalHeaderArray = Array.from(originalHeaders);
    
    // Get all data rows from original table
    var originalRows = originalTable.querySelectorAll('tbody tr');
    
    // Create new table structure
    var newTable = document.createElement('table');
    newTable.className = 'table table-bordered';
    
    // Create header row
    var newHeaderRow = document.createElement('tr');
    var newDataRows = [];
    
    // Define column mapping for the original table (including checkbox and action columns)
    var columnMapping = {
        'tracking': 1,        // رقم الشحنة (after checkbox)
        'sender_name': 2,     // بيانات الراسل (contains name and phone)
        'sender_phone': 2,    // بيانات الراسل (contains name and phone)
        'recipient_name': 3,  // اسم المستلم (contains name and phone)
        'recipient_phone': 3, // اسم المستلم (contains name and phone)
        'governorate': 4,     // المحافظة
        'area': 5,           // المنطقة
        'shipment_type': 6,  // نوع الشحنة
        'courier': 7,        // المندوب
        'status': 8,         // حالة الشحنة
        'cod': 9,            // القيمة (COD)
        'commission': 10,    // العمولة
        'total': 11,         // إجمالي المستحق
        'date': 12          // التاريخ
    };
    
    // Create a mapping for totals columns to their actual data positions
    var totalsColumnMapping = {
        'tracking': 0,        // إجمالي الشحنات
        'cod': 1,            // إجمالي القيمة
        'commission': 2,      // إجمالي العمولة
        'total': 3           // الإجمالي الكلي
    };
    
    // Process each row
    originalRows.forEach(function(row, rowIndex) {
        var cells = row.querySelectorAll('td');
        var cellArray = Array.from(cells);
        var newRow = document.createElement('tr');
        newDataRows.push(newRow);
        
        // Add cells based on selected columns
        selectedColumns.forEach(function(colType) {
            var cellIndex = columnMapping[colType];
            if (cellIndex >= 0 && cellIndex < cellArray.length) {
                var newCell = document.createElement('td');
                var cellContent = '';
                
                // Special handling for sender and recipient columns
                if (colType === 'sender_name' || colType === 'sender_phone') {
                    var senderCell = cellArray[2]; // Sender cell
                    if (senderCell) {
                        var senderDivs = senderCell.querySelectorAll('div');
                        if (senderDivs.length >= 2) {
                            if (colType === 'sender_name') {
                                // Extract only the text content from the first div (name)
                                cellContent = senderDivs[0].innerText.trim();
                            } else if (colType === 'sender_phone') {
                                // Extract only the text content from the second div (phone)
                                cellContent = senderDivs[1].innerText.trim();
                            }
                        }
                    }
                } else if (colType === 'recipient_name' || colType === 'recipient_phone') {
                    var recipientCell = cellArray[3]; // Recipient cell
                    if (recipientCell) {
                        var recipientDivs = recipientCell.querySelectorAll('div');
                        if (recipientDivs.length >= 2) {
                            if (colType === 'recipient_name') {
                                // Extract only the text content from the first div (name)
                                cellContent = recipientDivs[0].innerText.trim();
                            } else if (colType === 'recipient_phone') {
                                // Extract only the text content from the second div (phone)
                                cellContent = recipientDivs[1].innerText.trim();
                            }
                        }
                    }
                } else {
                // Clean the cell content for Excel export
                    cellContent = cellArray[cellIndex].innerHTML
                    .replace(/<br>/g, ' ')
                    .replace(/<small[^>]*>/g, '')
                    .replace(/<\/small>/g, ' ')
                    .replace(/<strong>/g, '')
                    .replace(/<\/strong>/g, '')
                    .replace(/<div>/g, '')
                    .replace(/<\/div>/g, ' ')
                    .replace(/<span[^>]*>/g, '')
                    .replace(/<\/span>/g, '')
                    .replace(/<i[^>]*>/g, '')
                    .replace(/<\/i>/g, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                }
                
                newCell.innerHTML = cellContent;
                newRow.appendChild(newCell);
                
                // Debug: Log cell content for sender/recipient columns
                if (colType === 'sender_name' || colType === 'sender_phone' || 
                    colType === 'recipient_name' || colType === 'recipient_phone') {
                    console.log('Column:', colType, 'Content:', cellContent);
                }
            }
        });
    });
    
    // Add totals table if it exists - with better formatting and clear separation
    var totalsTable = document.querySelector('.card-body .table-responsive table, .card .table-responsive table');
    if (totalsTable) {
        // Add multiple empty rows for clear separation
        for (var i = 0; i < 3; i++) {
            var spacingRow = document.createElement('tr');
            selectedColumns.forEach(function() {
                var emptyCell = document.createElement('td');
                emptyCell.innerHTML = '';
                spacingRow.appendChild(emptyCell);
            });
            newDataRows.push(spacingRow);
        }
        
        // Add totals section header row with better formatting
        var totalsSectionHeader = document.createElement('tr');
        selectedColumns.forEach(function(colType, index) {
            var headerCell = document.createElement('td');
            if (index === 0) {
                headerCell.innerHTML = '📊 === ملخص الإجماليات === 📊';
                headerCell.style.fontWeight = 'bold';
                headerCell.style.backgroundColor = '#007bff';
                headerCell.style.color = 'white';
                headerCell.style.textAlign = 'center';
                headerCell.style.fontSize = '14px';
                headerCell.setAttribute('colspan', selectedColumns.length);
            } else {
                headerCell.innerHTML = '';
            }
            totalsSectionHeader.appendChild(headerCell);
        });
        newDataRows.push(totalsSectionHeader);
        
        // Add totals header row with proper column headers and data division
        var totalsHeaderRow = document.createElement('tr');
        selectedColumns.forEach(function(colType, index) {
            var headerCell = document.createElement('td');
            // Map the totals columns to proper headers based on the selected column type
            var headerText = '';
            if (totalsColumnMapping.hasOwnProperty(colType)) {
                var totalsHeaders = ['إجمالي الشحنات', 'إجمالي القيمة', 'إجمالي العمولة', 'الإجمالي الكلي'];
                var headerIndex = totalsColumnMapping[colType];
                if (headerIndex >= 0 && headerIndex < totalsHeaders.length) {
                    headerText = '📊 ' + totalsHeaders[headerIndex];
                }
            }
            headerCell.innerHTML = headerText;
            headerCell.style.fontWeight = 'bold';
            headerCell.style.backgroundColor = '#28a745';
            headerCell.style.color = 'white';
            headerCell.style.border = '2px solid #1e7e34';
            totalsHeaderRow.appendChild(headerCell);
        });
        newDataRows.push(totalsHeaderRow);
        
        // Add totals data row with proper data mapping
        var totalsDataRow = document.createElement('tr');
        var totalsCells = totalsTable.querySelectorAll('tbody tr td');
        if (totalsCells.length >= 4) {
            // Create a mapping for totals data
            var totalsData = [];
            totalsCells.forEach(function(cell, cellIndex) {
                if (cellIndex < 4) { // Only take the first 4 cells which contain the actual totals
                    var cellContent = cell.innerHTML
                        .replace(/<br>/g, ' ')
                        .replace(/<small[^>]*>/g, '')
                        .replace(/<\/small>/g, ' ')
                        .replace(/<strong>/g, '')
                        .replace(/<\/strong>/g, '')
                        .replace(/<div>/g, '')
                        .replace(/<\/div>/g, ' ')
                        .replace(/<span[^>]*>/g, '')
                        .replace(/<\/span>/g, '')
                        .replace(/<i[^>]*>/g, '')
                        .replace(/<\/i>/g, '')
                        .replace(/\s+/g, ' ')
                        .trim();
                    totalsData.push(cellContent);
                }
            });
            
            selectedColumns.forEach(function(colType, index) {
                var dataCell = document.createElement('td');
                // Map totals data based on the column type
                if (totalsColumnMapping.hasOwnProperty(colType)) {
                    var dataIndex = totalsColumnMapping[colType];
                    if (dataIndex >= 0 && dataIndex < totalsData.length) {
                        dataCell.innerHTML = totalsData[dataIndex];
                        dataCell.style.fontWeight = 'bold';
                        dataCell.style.backgroundColor = '#495057';
                        dataCell.style.color = 'white';
                        dataCell.style.textAlign = 'center';
                        dataCell.style.fontSize = '12px';
                    } else {
                        dataCell.innerHTML = '';
                    }
                } else {
                    dataCell.innerHTML = '';
                }
                totalsDataRow.appendChild(dataCell);
            });
            newDataRows.push(totalsDataRow);
        }
    }
    
    // Add header cells based on selected columns
    selectedColumns.forEach(function(colType) {
        var newHeaderCell = document.createElement('th');
        var headerContent = '';
        
        // Special handling for sender and recipient headers
        if (colType === 'sender_name') {
            headerContent = 'اسم الراسل';
        } else if (colType === 'sender_phone') {
            headerContent = 'رقم الراسل';
        } else if (colType === 'recipient_name') {
            headerContent = 'اسم المستلم';
        } else if (colType === 'recipient_phone') {
            headerContent = 'رقم المستلم';
        } else {
        var headerIndex = columnMapping[colType];
        if (headerIndex >= 0 && headerIndex < originalHeaderArray.length) {
            // Clean the header content for Excel export
                headerContent = originalHeaderArray[headerIndex].innerHTML
                .replace(/<br>/g, ' ')
                .replace(/<small[^>]*>/g, '')
                .replace(/<\/small>/g, ' ')
                .replace(/<strong>/g, '')
                .replace(/<\/strong>/g, '')
                .replace(/<div>/g, '')
                .replace(/<\/div>/g, ' ')
                .replace(/<span[^>]*>/g, '')
                .replace(/<\/span>/g, '')
                .replace(/<i[^>]*>/g, '')
                .replace(/<\/i>/g, '')
                .replace(/\s+/g, ' ')
                .trim();
            }
        }
        
            newHeaderCell.innerHTML = headerContent;
            newHeaderRow.appendChild(newHeaderCell);
    });
    
    // Build the new table
    var newTbody = document.createElement('tbody');
    newDataRows.forEach(function(row) {
        newTbody.appendChild(row);
    });
    
    var newThead = document.createElement('thead');
    newThead.appendChild(newHeaderRow);
    
    newTable.appendChild(newThead);
    newTable.appendChild(newTbody);
    
    // Export the new table
    exportTableToExcel(newTable, 'شحنات_الوكيل_' + new Date().toISOString().split('T')[0]);
    
    // Close modal
    $('#excelExportModal').modal('hide');
}

// Enhanced Excel export with better formatting
function exportTableToExcel(tableElement, filename) {
    var downloadLink;
    var dataType = 'application/vnd.ms-excel';
    
    // Add XML declaration and Excel workbook/worksheet tags
    var excelTemplate = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    excelTemplate += "<?mso-application progid=\"Excel.Sheet\"?>\n";
    excelTemplate += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
    excelTemplate += 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
    excelTemplate += 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
    excelTemplate += 'xmlns:html="http://www.w3.org/TR/REC-html40">\n';
    excelTemplate += '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">\n';
    excelTemplate += '<Version>16.00</Version>\n';
    excelTemplate += '</DocumentProperties>\n';
    excelTemplate += '<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">\n';
    excelTemplate += '<WindowHeight>15000</WindowHeight>\n';
    excelTemplate += '<WindowWidth>20000</WindowWidth>\n';
    excelTemplate += '<WindowTopX>0</WindowTopX>\n';
    excelTemplate += '<WindowTopY>0</WindowTopY>\n';
    excelTemplate += '<ProtectStructure>False</ProtectStructure>\n';
    excelTemplate += '<ProtectWindows>False</ProtectWindows>\n';
    excelTemplate += '</ExcelWorkbook>\n';
    excelTemplate += '<Styles>\n';
    excelTemplate += '<Style ss:ID="Default" ss:Name="Normal">\n';
    excelTemplate += '<Alignment ss:Vertical="Center" ss:Horizontal="Right"/>\n';
    excelTemplate += '<Borders/>\n';
    excelTemplate += '<Font ss:FontName="Arial" x:CharSet="1" ss:Size="10"/>\n';
    excelTemplate += '<Interior/>\n';
    excelTemplate += '<NumberFormat/>\n';
    excelTemplate += '<Protection/>\n';
    excelTemplate += '</Style>\n';
    excelTemplate += '<Style ss:ID="Header">\n';
    excelTemplate += '<Alignment ss:Vertical="Center" ss:Horizontal="Right"/>\n';
    excelTemplate += '<Borders>\n';
    excelTemplate += '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>\n';
    excelTemplate += '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>\n';
    excelTemplate += '</Borders>\n';
    excelTemplate += '<Font ss:FontName="Arial" x:CharSet="1" ss:Size="11" ss:Bold="1"/>\n';
    excelTemplate += '<Interior ss:Color="#4472C4" ss:Pattern="Solid"/>\n';
    excelTemplate += '</Style>\n';
    excelTemplate += '<Style ss:ID="Cell">\n';
    excelTemplate += '<Alignment ss:Vertical="Center" ss:Horizontal="Right"/>\n';
    excelTemplate += '<Borders>\n';
    excelTemplate += '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '</Borders>\n';
    excelTemplate += '<Font ss:FontName="Arial" x:CharSet="1" ss:Size="10"/>\n';
    excelTemplate += '</Style>\n';
    excelTemplate += '<Style ss:ID="TotalsHeader">\n';
    excelTemplate += '<Alignment ss:Vertical="Center" ss:Horizontal="Right"/>\n';
    excelTemplate += '<Borders>\n';
    excelTemplate += '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>\n';
    excelTemplate += '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>\n';
    excelTemplate += '</Borders>\n';
    excelTemplate += '<Font ss:FontName="Arial" x:CharSet="1" ss:Size="11" ss:Bold="1"/>\n';
    excelTemplate += '<Interior ss:Color="#28A745" ss:Pattern="Solid"/>\n';
    excelTemplate += '</Style>\n';
    excelTemplate += '<Style ss:ID="TotalsCell">\n';
    excelTemplate += '<Alignment ss:Vertical="Center" ss:Horizontal="Right"/>\n';
    excelTemplate += '<Borders>\n';
    excelTemplate += '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '</Borders>\n';
    excelTemplate += '<Font ss:FontName="Arial" x:CharSet="1" ss:Size="10" ss:Bold="1"/>\n';
    excelTemplate += '<Interior ss:Color="#495057" ss:Pattern="Solid"/>\n';
    excelTemplate += '</Style>\n';
    excelTemplate += '<Style ss:ID="TotalsSectionHeader">\n';
    excelTemplate += '<Alignment ss:Vertical="Center" ss:Horizontal="Center"/>\n';
    excelTemplate += '<Borders>\n';
    excelTemplate += '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>\n';
    excelTemplate += '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>\n';
    excelTemplate += '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>\n';
    excelTemplate += '</Borders>\n';
    excelTemplate += '<Font ss:FontName="Arial" x:CharSet="1" ss:Size="12" ss:Bold="1"/>\n';
    excelTemplate += '<Interior ss:Color="#007BFF" ss:Pattern="Solid"/>\n';
    excelTemplate += '</Style>\n';
    excelTemplate += '</Styles>\n';
    excelTemplate += '<Worksheet ss:Name="Sheet1">\n';
    excelTemplate += '<Table ss:DefaultColumnWidth="120">\n';
    
    // Get all rows
    var rows = tableElement.querySelectorAll('tr');
    
    // Process each row
    rows.forEach(function(row, rowIndex) {
        excelTemplate += '<Row>\n';
        
        // Determine row type for styling
        var isHeaderRow = rowIndex === 0;
        var isTotalsSectionHeader = false;
        var isTotalsHeaderRow = false;
        var isTotalsDataRow = false;
        
        // Check if this is a totals row by looking for specific styling
        var firstCell = row.querySelector('td, th');
        if (firstCell) {
            var cellStyle = firstCell.style.backgroundColor || firstCell.style.background || '';
            if (cellStyle.includes('#007bff') || cellStyle.includes('rgb(0, 123, 255)')) {
                isTotalsSectionHeader = true;
            } else if (cellStyle.includes('#28a745') || cellStyle.includes('rgb(40, 167, 69)')) {
                isTotalsHeaderRow = true;
            } else if (cellStyle.includes('#495057') || cellStyle.includes('rgb(73, 80, 87)')) {
                isTotalsDataRow = true;
            }
        }
        
        // Get all cells (th and td)
        var cells = row.querySelectorAll('th, td');
        cells.forEach(function(cell) {
            // Clean the cell content
            var cellContent = cell.innerHTML
                .replace(/<br>/g, ' ')
                .replace(/<small[^>]*>/g, '')
                .replace(/<\/small>/g, ' ')
                .replace(/<strong>/g, '')
                .replace(/<\/strong>/g, '')
                .replace(/<div>/g, '')
                .replace(/<\/div>/g, ' ')
                .replace(/<span[^>]*>/g, '')
                .replace(/<\/span>/g, '')
                .replace(/<i[^>]*>/g, '')
                .replace(/<\/i>/g, '')
                .replace(/&nbsp;/g, ' ')
                .replace(/&amp;/g, '&')
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>')
                .replace(/&quot;/g, '"')
                .replace(/\s+/g, ' ')
                .trim();
            
            // Determine cell style based on row type
            var cellStyle = 'Cell';
            if (isHeaderRow) {
                cellStyle = 'Header';
            } else if (isTotalsSectionHeader) {
                cellStyle = 'TotalsSectionHeader';
            } else if (isTotalsHeaderRow) {
                cellStyle = 'TotalsHeader';
            } else if (isTotalsDataRow) {
                cellStyle = 'TotalsCell';
            }
            
            // Add cell to template with proper XML encoding and style
            excelTemplate += '<Cell ss:StyleID="' + cellStyle + '">';
            excelTemplate += '<Data ss:Type="String">' + 
                cellContent.replace(/[<>&"']/g, function(c) {
                    switch (c) {
                        case '<': return '&lt;';
                        case '>': return '&gt;';
                        case '&': return '&amp;';
                        case '"': return '&quot;';
                        case "'": return '&apos;';
                    }
                }) + 
                '</Data></Cell>\n';
        });
        
        excelTemplate += '</Row>\n';
    });
    
    // Close tags
    excelTemplate += '</Table>\n';
    excelTemplate += '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">\n';
    excelTemplate += '<PageSetup>\n';
    excelTemplate += '<Layout x:Orientation="Landscape"/>\n';
    excelTemplate += '<Header x:Margin="0.3"/>\n';
    excelTemplate += '<Footer x:Margin="0.3"/>\n';
    excelTemplate += '<PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>\n';
    excelTemplate += '</PageSetup>\n';
    excelTemplate += '<FitToPage/>\n';
    excelTemplate += '<Print>\n';
    excelTemplate += '<FitHeight>0</FitHeight>\n';
    excelTemplate += '<ValidPrinterInfo/>\n';
    excelTemplate += '<PaperSizeIndex>9</PaperSizeIndex>\n';
    excelTemplate += '<Scale>100</Scale>\n';
    excelTemplate += '<HorizontalResolution>600</HorizontalResolution>\n';
    excelTemplate += '<VerticalResolution>600</VerticalResolution>\n';
    excelTemplate += '</Print>\n';
    excelTemplate += '<Selected/>\n';
    excelTemplate += '<DoNotDisplayGridlines/>\n';
    excelTemplate += '<ProtectObjects>False</ProtectObjects>\n';
    excelTemplate += '<ProtectScenarios>False</ProtectScenarios>\n';
    excelTemplate += '</WorksheetOptions>\n';
    excelTemplate += '</Worksheet>\n';
    excelTemplate += '</Workbook>';
    
    // Specify file name
    filename = filename ? filename + '.xls' : 'excel_data.xls';
    
    // Create download link element
    downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    
    if(navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', excelTemplate], {
            type: dataType
        });
        navigator.msSaveOrOpenBlob(blob, filename);
    }else{
        // Create a link to the file with proper encoding
        var encodedData = encodeURIComponent(excelTemplate);
        downloadLink.href = 'data:' + dataType + ';charset=utf-8,' + encodedData;
    
        // Setting the file name
        downloadLink.download = filename;
        
        //triggering the function
        downloadLink.click();
    }
    
    // Clean up
    document.body.removeChild(downloadLink);
}

// Enhanced print function with better formatting
function showPrintModal() {
    $('#printModal').modal('show');
}

function selectAllPrintColumns() {
    $('#printModal input[type="checkbox"]').prop('checked', true);
}

function deselectAllPrintColumns() {
    $('#printModal input[type="checkbox"]').prop('checked', false);
}

function printSelectedColumns() {
    var selectedColumns = [];
    $('#printModal input[type="checkbox"]:checked').each(function() {
        var columnValue = $(this).val();
        // Ensure no duplicate columns are added
        if (selectedColumns.indexOf(columnValue) === -1) {
            selectedColumns.push(columnValue);
        }
    });
    
    if (selectedColumns.length === 0) {
        showTemporaryModal('تنبيه', 'الرجاء اختيار عمود واحد على الأقل للطباعة', 'warning');
        return;
    }
    
    // Debug: Log selected columns
    console.log('Selected columns for print:', selectedColumns);
    
    // Create a new table with only selected columns
    var originalTable = document.getElementById('shipmentsTable');
    var originalHeaderArray = originalTable.querySelectorAll('thead th');
    var originalRows = originalTable.querySelectorAll('tbody tr');
    
    var newTable = document.createElement('table');
    newTable.className = 'table table-bordered';
    
    var newHeaderRow = document.createElement('tr');
    var newDataRows = [];
    
    // Define column mapping for print (same as Excel export)
    var printColumnMapping = {
        'tracking': 1,        // رقم الشحنة (after checkbox)
        'sender_name': 2,     // بيانات الراسل (contains name and phone)
        'sender_phone': 2,    // بيانات الراسل (contains name and phone)
        'recipient_name': 3,  // اسم المستلم (contains name and phone)
        'recipient_phone': 3, // اسم المستلم (contains name and phone)
        'governorate': 4,     // المحافظة
        'area': 5,           // المنطقة
        'shipment_type': 6,  // نوع الشحنة
        'courier': 7,        // المندوب
        'status': 8,         // حالة الشحنة
        'cod': 9,            // القيمة (COD)
        'commission': 10,    // العمولة
        'total': 11,         // إجمالي المستحق
        'date': 12          // التاريخ
    };
    
    // Create a mapping for totals columns to their actual data positions (same as Excel export)
    var printTotalsColumnMapping = {
        'tracking': 0,        // إجمالي الشحنات
        'cod': 1,            // إجمالي القيمة
        'commission': 2,      // إجمالي العمولة
        'total': 3           // الإجمالي الكلي
    };
    
    // Add header cells based on selected columns
    selectedColumns.forEach(function(colType) {
        var newHeaderCell = document.createElement('th');
        var headerContent = '';
        
        // Special handling for sender and recipient headers
        if (colType === 'sender_name') {
            headerContent = 'اسم الراسل';
        } else if (colType === 'sender_phone') {
            headerContent = 'رقم الراسل';
        } else if (colType === 'recipient_name') {
            headerContent = 'اسم المستلم';
        } else if (colType === 'recipient_phone') {
            headerContent = 'رقم المستلم';
        } else {
        var headerIndex = printColumnMapping[colType];
        if (headerIndex >= 0 && headerIndex < originalHeaderArray.length) {
                headerContent = originalHeaderArray[headerIndex].innerHTML
                .replace(/<br>/g, ' ')
                .replace(/<small[^>]*>/g, '')
                .replace(/<\/small>/g, ' ')
                .replace(/<strong>/g, '')
                .replace(/<\/strong>/g, '')
                .replace(/<div>/g, '')
                .replace(/<\/div>/g, ' ')
                .replace(/<span[^>]*>/g, '')
                .replace(/<\/span>/g, '')
                .replace(/<i[^>]*>/g, '')
                .replace(/<\/i>/g, '')
                .replace(/\s+/g, ' ')
                .trim();
            }
        }
        
            newHeaderCell.innerHTML = headerContent;
            newHeaderRow.appendChild(newHeaderCell);
    });
    
    // Add data rows (including totals row)
    originalRows.forEach(function(row) {
        
        var newRow = document.createElement('tr');
        var cells = row.querySelectorAll('td');
        
        selectedColumns.forEach(function(colType) {
            var newCell = document.createElement('td');
            var cellContent = '';
            
            // Special handling for sender and recipient columns
            if (colType === 'sender_name' || colType === 'sender_phone') {
                var senderCell = cells[2]; // Sender cell
                if (senderCell) {
                    var senderDivs = senderCell.querySelectorAll('div');
                    if (senderDivs.length >= 2) {
                        if (colType === 'sender_name') {
                            // Extract only the text content from the first div (name)
                            cellContent = senderDivs[0].innerText.trim();
                        } else if (colType === 'sender_phone') {
                            // Extract only the text content from the second div (phone)
                            cellContent = senderDivs[1].innerText.trim();
                        }
                    }
                }
            } else if (colType === 'recipient_name' || colType === 'recipient_phone') {
                var recipientCell = cells[3]; // Recipient cell
                if (recipientCell) {
                    var recipientDivs = recipientCell.querySelectorAll('div');
                    if (recipientDivs.length >= 2) {
                        if (colType === 'recipient_name') {
                            // Extract only the text content from the first div (name)
                            cellContent = recipientDivs[0].innerText.trim();
                        } else if (colType === 'recipient_phone') {
                            // Extract only the text content from the second div (phone)
                            cellContent = recipientDivs[1].innerText.trim();
                        }
                    }
                }
            } else {
            var cellIndex = printColumnMapping[colType];
            if (cellIndex >= 0 && cellIndex < cells.length) {
                    cellContent = cells[cellIndex].innerHTML
                    .replace(/<br>/g, ' ')
                    .replace(/<small[^>]*>/g, '')
                    .replace(/<\/small>/g, ' ')
                    .replace(/<strong>/g, '')
                    .replace(/<\/strong>/g, '')
                    .replace(/<div>/g, '')
                    .replace(/<\/div>/g, ' ')
                    .replace(/<span[^>]*>/g, '')
                    .replace(/<\/span>/g, '')
                    .replace(/<i[^>]*>/g, '')
                    .replace(/<\/i>/g, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                }
            }
            
                newCell.innerHTML = cellContent;
                newRow.appendChild(newCell);
        });
        
        newDataRows.push(newRow);
    });
    
    var newThead = document.createElement('thead');
    newThead.appendChild(newHeaderRow);
    newTable.appendChild(newThead);
    
    var newTbody = document.createElement('tbody');
    newDataRows.forEach(function(row) {
        newTbody.appendChild(row);
    });
    newTable.appendChild(newTbody);
    
    // Create print window
    var printWindow = window.open('', '', 'height=700,width=900');
    printWindow.document.write('<html><head><title>طباعة شحنات الوكيل</title>');
    printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        @page { size: landscape; }
        body { 
            font-family: 'Tajawal', Arial, sans-serif; 
            direction: rtl; 
            padding: 20px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
            page-break-inside: auto;
        }
        th, td { 
            border: 1px solid #dee2e6; 
            padding: 8px; 
            text-align: right;
            font-size: 12px;
        }
        thead th { 
            background-color: #f8f9fa; 
            color: #000;
            font-weight: bold;
        }
        tr { 
            page-break-inside: avoid; 
            page-break-after: auto;
        }
        .report-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .report-header h3 {
            margin-bottom: 10px;
            color: #333;
        }
        .report-header h4 {
            color: #666;
            margin-bottom: 5px;
        }
        .report-header p {
            color: #888;
            font-size: 12px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .totals-row {
            background-color: #343a40 !important;
            color: white !important;
        }
        .totals-row td {
            border-color: #495057 !important;
        }
        .totals-row strong {
            color: white !important;
        }
        .totals-row .badge {
            font-size: 0.9em;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    `);
    printWindow.document.write('</style></head><body>');
    
    // Add report header
    printWindow.document.write('<div class="report-header">');
    printWindow.document.write('<h3>تقرير شحنات الوكيل</h3>');
    printWindow.document.write('<h4><?php echo htmlspecialchars($agent_data['company_name']); ?></h4>');
    printWindow.document.write('<p>تاريخ التقرير: ' + new Date().toLocaleDateString('ar-EG') + '</p>');
    printWindow.document.write('</div>');
    
    // Add table
    printWindow.document.write(newTable.outerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    // Wait for resources to load then print
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };
    
    // Close the modal
    $('#printModal').modal('hide');
}

</script>

</body>
</html>
