<?php
// ملف تصدير الشحنات
session_start();
include 'db_connect.php';

// التحقق من الصلاحيات
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

// الحصول على المعاملات
$export_type = $_GET['type'] ?? 'excel';
$columns = explode(',', $_GET['columns'] ?? 'tracking_number,sender_name,recipient_name,status,cod_amount,date_created');
$scope = $_GET['scope'] ?? 'all';

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

// تحضير البيانات للتصدير
$data = [];
$headers = [];

// تحديد العناوين
foreach ($columns as $col) {
    switch ($col) {
        case 'tracking_number':
            $headers[] = 'رقم الشحنة';
            break;
        case 'sender_name':
            $headers[] = 'اسم الراسل';
            break;
        case 'recipient_name':
            $headers[] = 'اسم المستلم';
            break;
        case 'status':
            $headers[] = 'الحالة';
            break;
        case 'cod_amount':
            $headers[] = 'القيمة (COD)';
            break;
        case 'payment_status':
            $headers[] = 'الوضع المالي';
            break;
        case 'date_created':
            $headers[] = 'تاريخ الإنشاء';
            break;
        case 'notes':
            $headers[] = 'الملاحظات';
            break;
    }
}

// تحضير البيانات
while ($row = $result->fetch_assoc()) {
    $row_data = [];
    foreach ($columns as $col) {
        switch ($col) {
            case 'tracking_number':
                $row_data[] = $row['tracking_number'];
                break;
            case 'sender_name':
                $row_data[] = $row['sender_name'];
                break;
            case 'recipient_name':
                $row_data[] = $row['recipient_name'];
                break;
            case 'status':
                $status_val = isset($row['status']) ? intval($row['status']) : 0;
                $row_data[] = $status_arr[$status_val] ?? "غير معروف";
                break;
            case 'cod_amount':
                $row_data[] = number_format($row['cod_amount'] ?? 0, 2) . ' جنيه';
                break;
            case 'payment_status':
                $payment_status = $row['payment_status'] ?? 'unpaid';
                switch ($payment_status) {
                    case 'paid':
                        $row_data[] = 'مدفوع بالكامل';
                        break;
                    case 'partial_paid':
                        $row_data[] = 'مدفوع جزئياً';
                        break;
                    default:
                        $row_data[] = 'غير مدفوع';
                        break;
                }
                break;
            case 'date_created':
                $row_data[] = date('Y-m-d', strtotime($row['date_created']));
                break;
            case 'notes':
                $row_data[] = $row['notes'] ?? '';
                break;
        }
    }
    $data[] = $row_data;
}

// تصدير حسب النوع المطلوب
switch ($export_type) {
    case 'excel':
        exportToExcel($headers, $data);
        break;
    case 'csv':
        exportToCSV($headers, $data);
        break;
    case 'pdf':
        exportToPDF($headers, $data);
        break;
    default:
        exportToExcel($headers, $data);
}

function exportToExcel($headers, $data) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="parcels_export_' . date('Y-m-d_H-i-s') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . $header . '</th>';
    }
    echo '</tr>';
    
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . $cell . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
}

function exportToCSV($headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="parcels_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // إضافة BOM للدعم العربي
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, $headers);
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportToPDF($headers, $data) {
    // هنا يمكن إضافة مكتبة PDF مثل TCPDF أو FPDF
    // للتبسيط، سنقوم بإنشاء HTML يمكن طباعته كـ PDF
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="parcels_export_' . date('Y-m-d_H-i-s') . '.html"');
    
    echo '<!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>تصدير الشحنات</title>
        <style>
            body { font-family: Arial, sans-serif; direction: rtl; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>تصدير الشحنات</h1>
            <p>تاريخ التصدير: ' . date('Y-m-d H:i:s') . '</p>
        </div>
        <table>
            <thead>
                <tr>';
    
    foreach ($headers as $header) {
        echo '<th>' . $header . '</th>';
    }
    
    echo '</tr></thead><tbody>';
    
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . $cell . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody></table></body></html>';
}
?>
