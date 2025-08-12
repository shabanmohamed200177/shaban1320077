<?php
// صفحة إشعارات WhatsApp المطورة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'حدث خطأ'];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_sent':
                $notification_id = intval($_POST['notification_id']);
                $stmt = $conn->prepare("UPDATE free_notification_logs SET sent_manually = TRUE, sent_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $notification_id);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'تم تحديد الإشعار كمُرسل'];
                }
                break;
                
            case 'save_custom_message':
                $message_content = $_POST['message_content'];
                $phone_number = $_POST['phone_number'];
                $parcel_id = intval($_POST['parcel_id']);
                $recipient_type = $_POST['recipient_type'];
                
                // تنسيق رقم الهاتف لإضافة رمز مصر
                $phone_number = preg_replace('/\D/', '', $phone_number); // إزالة كل شيء عدا الأرقام
                if (substr($phone_number, 0, 2) === '01') {
                    $phone_number = '2' . $phone_number; // إضافة رمز مصر
                } elseif (substr($phone_number, 0, 3) !== '201') {
                    $phone_number = '201' . ltrim($phone_number, '0'); // إضافة رمز مصر مع إزالة الصفر
                }
                
                // حفظ الرسالة المخصصة
                $stmt = $conn->prepare("INSERT INTO free_notification_logs (parcel_id, recipient_type, phone_number, message_content, whatsapp_link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $whatsapp_link = "https://wa.me/" . $phone_number . "?text=" . urlencode($message_content);
                $stmt->bind_param("issss", $parcel_id, $recipient_type, $phone_number, $message_content, $whatsapp_link);
                
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'تم حفظ الرسالة المخصصة بنجاح'];
                } else {
                    $response = ['status' => 'error', 'message' => 'خطأ في حفظ الرسالة'];
                }
                break;
                
            case 'send_rejection_postponement':
                $parcel_id = intval($_POST['parcel_id']);
                $custom_message = $_POST['custom_message'];
                
                // جلب بيانات الشحنة
                $parcel_query = "SELECT p.*, a.company_name as agent_name, psr.reason_text as status_reason
                                FROM parcels p 
                                LEFT JOIN agents a ON p.agent_id = a.id
                                LEFT JOIN parcel_status_reasons psr ON p.status_reason_id = psr.id
                                WHERE p.id = ?";
                $stmt = $conn->prepare($parcel_query);
                $stmt->bind_param("i", $parcel_id);
                $stmt->execute();
                $parcel = $stmt->get_result()->fetch_assoc();
                
                if ($parcel && $parcel['shipment_direction'] === 'from_agent') {
                    // تنسيق رقم هاتف الراسل
                    $sender_phone = preg_replace('/\D/', '', $parcel['sender_phone']);
                    if (substr($sender_phone, 0, 2) === '01') {
                        $sender_phone = '2' . $sender_phone;
                    } elseif (substr($sender_phone, 0, 3) !== '201') {
                        $sender_phone = '201' . ltrim($sender_phone, '0');
                    }
                    
                    // تحضير الرسالة
                    $message = $custom_message;
                    $message = str_replace('{tracking_number}', $parcel['tracking_number'] ?: '', $message);
                    $message = str_replace('{sender_name}', $parcel['sender_name'] ?: '', $message);
                    $message = str_replace('{recipient_name}', $parcel['recipient_name'] ?: '', $message);
                    $message = str_replace('{cod_amount}', number_format($parcel['cod_amount'] ?: 0, 2), $message);
                    $message = str_replace('{status_reason}', $parcel['status_reason'] ?: '', $message);
                    $message = str_replace('{status_reason_note}', $parcel['status_reason_note'] ?: '', $message);
                    
                    // حفظ الإشعار
                    $stmt = $conn->prepare("INSERT INTO free_notification_logs (parcel_id, status_id, recipient_type, phone_number, message_content, whatsapp_link, created_at) VALUES (?, ?, 'sender', ?, ?, ?, NOW())");
                    $whatsapp_link = "https://wa.me/" . $sender_phone . "?text=" . urlencode($message);
                    $stmt->bind_param("iisss", $parcel_id, $parcel['status'], $sender_phone, $message, $whatsapp_link);
                    
                    if ($stmt->execute()) {
                        $response = ['status' => 'success', 'message' => 'تم إنشاء إشعار للراسل بنجاح'];
                    } else {
                        $response = ['status' => 'error', 'message' => 'خطأ في إنشاء الإشعار: ' . $conn->error];
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'الشحنة غير صالحة أو ليست من وكيل'];
                }
                break;
                
            case 'mark_multiple_sent':
                $ids = json_decode($_POST['notification_ids'], true);
                if ($ids && is_array($ids)) {
                    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                    $stmt = $conn->prepare("UPDATE free_notification_logs SET sent_manually = TRUE, sent_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                    if ($stmt->execute()) {
                        $response = ['status' => 'success', 'message' => 'تم تحديد ' . count($ids) . ' إشعار كمُرسل'];
                    }
                }
                break;
                
            case 'delete_notification':
                $notification_id = intval($_POST['notification_id']);
                $stmt = $conn->prepare("DELETE FROM free_notification_logs WHERE id = ?");
                $stmt->bind_param("i", $notification_id);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'تم حذف الإشعار'];
                }
                break;
                
            case 'resend_notification':
                $notification_id = intval($_POST['notification_id']);
                $stmt = $conn->prepare("UPDATE free_notification_logs SET sent_manually = FALSE, sent_at = NULL WHERE id = ?");
                $stmt->bind_param("i", $notification_id);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'تم إعادة الإشعار للقائمة المعلقة'];
                }
                break;
        }
    }
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// فلترة البيانات
$filter_status = $_GET['filter_status'] ?? 'pending';
$filter_type = $_GET['filter_type'] ?? 'all';
$search_tracking = $_GET['search_tracking'] ?? '';

// بناء الاستعلام
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_status === 'pending') {
    $where_conditions[] = "fnl.sent_manually = FALSE";
} elseif ($filter_status === 'sent') {
    $where_conditions[] = "fnl.sent_manually = TRUE";
}

if ($filter_type !== 'all') {
    $where_conditions[] = "fnl.recipient_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}

if ($search_tracking) {
    $where_conditions[] = "p.tracking_number LIKE ?";
    $params[] = '%' . $search_tracking . '%';
    $param_types .= 's';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// جلب الإشعارات
$notifications_query = "
    SELECT fnl.*, p.tracking_number, ps.name_ar as status_name, p.shipment_direction,
           a.company_name as agent_name, p.sender_name, p.recipient_name,
           p.cod_amount, c.name as courier_name
    FROM free_notification_logs fnl
    LEFT JOIN parcels p ON fnl.parcel_id = p.id
    LEFT JOIN parcel_status ps ON fnl.status_id = ps.id
    LEFT JOIN agents a ON p.agent_id = a.id
    LEFT JOIN couriers c ON p.courier_id = c.id
    $where_clause
    ORDER BY fnl.created_at DESC
    LIMIT 100
";

$stmt = $conn->prepare($notifications_query);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// احصائيات سريعة
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN sent_manually = FALSE THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN sent_manually = TRUE THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN recipient_type = 'sender' THEN 1 ELSE 0 END) as senders,
        SUM(CASE WHEN recipient_type = 'recipient' THEN 1 ELSE 0 END) as recipients
    FROM free_notification_logs fnl
    LEFT JOIN parcels p ON fnl.parcel_id = p.id
    WHERE p.shipment_direction = 'from_agent'
";
$stats = $conn->query($stats_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إرسال واتساب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .notification-card {
            border-right: 4px solid #25d366;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .notification-card.sent {
            border-right-color: #6c757d;
            opacity: 0.8;
        }
        .whatsapp-btn {
            background: linear-gradient(45deg, #25d366, #128c7e);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .whatsapp-btn:hover {
            background: linear-gradient(45deg, #128c7e, #25d366);
            color: white;
            transform: scale(1.05);
        }
        .message-preview {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            max-height: 120px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin: 10px 0;
        }
        .stats-card {
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .filter-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .btn-group-toggle .btn {
            border-radius: 20px;
            margin: 2px;
        }
        .notification-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .bulk-actions {
            position: sticky;
            top: 20px;
            z-index: 100;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .notification-checkbox {
            transform: scale(1.2);
        }
        .progress-ring {
            width: 60px;
            height: 60px;
        }
        .progress-ring circle {
            stroke: #25d366;
            stroke-width: 4;
            fill: transparent;
            stroke-dasharray: 188;
            stroke-dashoffset: 188;
            transition: stroke-dashoffset 0.3s ease;
        }
        
        /* تحسينات للجوال */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            
            .d-flex.align-items-center.justify-content-between {
                flex-direction: column;
                gap: 15px;
            }
            
            .d-flex.align-items-center.justify-content-between > div {
                width: 100%;
                text-align: center;
            }
            
            .d-flex.align-items-center.justify-content-between > div:last-child {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px;
            }
            
            .btn {
                font-size: 0.85rem;
                padding: 6px 12px;
            }
            
            .notification-card {
                margin-bottom: 10px;
            }
            
            .notification-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .notification-actions .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .whatsapp-btn {
                width: 100% !important;
                margin-bottom: 8px;
            }
            
            .message-preview {
                max-height: 80px;
                font-size: 0.8rem;
            }
            
            .filter-panel .row {
                gap: 10px;
            }
            
            .filter-panel .col-md-3,
            .filter-panel .col-md-4,
            .filter-panel .col-md-2 {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .bulk-actions {
                position: relative;
                margin: 10px 0;
                padding: 10px;
            }
            
            .bulk-actions .d-flex {
                flex-direction: column;
                gap: 10px;
            }
            
            .bulk-actions .d-flex > div:last-child {
                display: flex;
                gap: 5px;
                flex-wrap: wrap;
            }
            
            .stats-card .card-body {
                padding: 15px;
            }
            
            .stats-card h3 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }
            
            .notification-card .card-body .row {
                flex-direction: column;
            }
            
            .notification-card .card-body .col-md-4,
            .notification-card .card-body .col-md-8 {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .form-control, .form-select {
                font-size: 16px; /* منع الزووم في iOS */
            }
            
            .filter-panel {
                padding: 15px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .stats-card .fs-1 {
                font-size: 2rem !important;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center">
            <i class="fab fa-whatsapp text-success fs-1 me-3"></i>
            <div>
                <h2 class="mb-1">إرسال واتساب</h2>
                <p class="text-muted mb-0">
                    للشحنات المستلمة من الوكيل - إدارة شاملة للإشعارات
                </p>
            </div>
        </div>
        <div>
            <button class="btn btn-success" onclick="showCustomMessageModal()">
                <i class="fas fa-edit me-2"></i>رسالة مخصصة
            </button>
            <button class="btn btn-warning" onclick="showRejectionPostponementModal()">
                <i class="fas fa-exclamation-triangle me-2"></i>تأجيل/رفض
            </button>
            <button class="btn btn-primary" onclick="refreshNotifications()">
                <i class="fas fa-sync-alt me-2"></i>تحديث
            </button>
            <a href="index.php?page=parcel_list" class="btn btn-secondary">
                <i class="fas fa-list me-2"></i>قائمة الشحنات
            </a>
        </div>
    </div>

    <!-- إحصائيات -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card bg-gradient-success text-white">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center">
                        <svg class="progress-ring me-3">
                            <circle cx="30" cy="30" r="25" style="stroke-dashoffset: <?php echo 188 - (188 * $stats['pending'] / max(1, $stats['total'])); ?>"></circle>
                        </svg>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['pending']; ?></h3>
                            <p class="mb-0">معلقة</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card bg-gradient-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fs-1 mb-2"></i>
                    <h3><?php echo $stats['sent']; ?></h3>
                    <p class="mb-0">تم الإرسال</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card bg-gradient-warning text-white">
                <div class="card-body text-center">
                    <i class="fas fa-user-tie fs-1 mb-2"></i>
                    <h3><?php echo $stats['senders']; ?></h3>
                    <p class="mb-0">للراسلين</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card bg-gradient-primary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-users fs-1 mb-2"></i>
                    <h3><?php echo $stats['recipients']; ?></h3>
                    <p class="mb-0">للمستلمين</p>
                </div>
            </div>
        </div>
    </div>

    <!-- فلاتر متقدمة -->
    <div class="filter-panel text-white">
        <h5><i class="fas fa-filter me-2"></i>فلاتر البحث المتقدمة</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">حالة الإرسال</label>
                <select name="filter_status" class="form-select">
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>معلقة</option>
                    <option value="sent" <?php echo $filter_status === 'sent' ? 'selected' : ''; ?>>تم الإرسال</option>
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>الكل</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">نوع المستقبل</label>
                <select name="filter_type" class="form-select">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>الكل</option>
                    <option value="sender" <?php echo $filter_type === 'sender' ? 'selected' : ''; ?>>الراسل</option>
                    <option value="recipient" <?php echo $filter_type === 'recipient' ? 'selected' : ''; ?>>المستلم</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">رقم التتبع</label>
                <input type="text" name="search_tracking" class="form-control" 
                       value="<?php echo htmlspecialchars($search_tracking); ?>" placeholder="بحث برقم التتبع">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-light w-100">
                    <i class="fas fa-search me-2"></i>بحث
                </button>
            </div>
        </form>
    </div>

    <!-- إجراءات مجمعة -->
    <div class="bulk-actions" id="bulkActions" style="display: none;">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span id="selectedCount">0</span> إشعار محدد
            </div>
            <div>
                <button class="btn btn-success btn-sm" onclick="bulkMarkSent()">
                    <i class="fas fa-check me-1"></i>تحديد كمُرسل
                </button>
                <button class="btn btn-danger btn-sm" onclick="bulkDelete()">
                    <i class="fas fa-trash me-1"></i>حذف
                </button>
                <button class="btn btn-secondary btn-sm" onclick="clearSelection()">
                    <i class="fas fa-times me-1"></i>إلغاء التحديد
                </button>
            </div>
        </div>
    </div>

    <!-- الإشعارات -->
    <?php if (count($notifications) > 0): ?>
        <div class="row" id="notificationsContainer">
            <?php foreach ($notifications as $notification): ?>
            <div class="col-lg-6 mb-3 notification-item" data-id="<?php echo $notification['id']; ?>">
                <div class="card notification-card <?php echo $notification['sent_manually'] ? 'sent' : ''; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <input type="checkbox" class="notification-checkbox me-2" 
                                   value="<?php echo $notification['id']; ?>" onchange="updateSelection()">
                            <div>
                                <strong class="badge bg-<?php echo $notification['sent_manually'] ? 'secondary' : 'primary'; ?>">
                                    <?php echo $notification['status_name'] ?? 'غير محدد'; ?>
                                </strong>
                                <small class="text-muted ms-2"><?php echo $notification['tracking_number'] ?? 'N/A'; ?></small>
                            </div>
                        </div>
                        <small class="text-muted"><?php echo date('d/m H:i', strtotime($notification['created_at'])); ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1">
                                    <strong>📱 المستقبل:</strong> 
                                    <span class="badge bg-<?php echo $notification['recipient_type'] === 'sender' ? 'warning' : 'info'; ?>">
                                        <?php echo $notification['recipient_type'] === 'sender' ? 'الراسل' : 'المستلم'; ?>
                                    </span>
                                </p>
                                <p class="mb-1">
                                    <strong>👤 الاسم:</strong> 
                                    <?php echo $notification['recipient_type'] === 'sender' ? $notification['sender_name'] : $notification['recipient_name']; ?>
                                </p>
                                <p class="mb-1">
                                    <strong>📞 الهاتف:</strong> 
                                    <?php echo $notification['phone_number']; ?>
                                </p>
                                <?php if ($notification['cod_amount']): ?>
                                <p class="mb-1">
                                    <strong>💰 المبلغ:</strong> 
                                    <span class="text-success"><?php echo number_format($notification['cod_amount'], 2); ?> ج.م</span>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <div class="message-preview">
                                    <?php echo $notification['message_content']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="notification-actions mt-3">
                            <?php if (!$notification['sent_manually']): ?>
                                <a href="<?php echo $notification['whatsapp_link']; ?>" 
                                   target="_blank" 
                                   class="whatsapp-btn">
                                    <i class="fab fa-whatsapp me-2"></i>إرسال
                                </a>
                                <button class="btn btn-outline-success btn-sm" 
                                        onclick="markAsSent(<?php echo $notification['id']; ?>)">
                                    <i class="fas fa-check me-1"></i>تم الإرسال
                                </button>
                            <?php else: ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i>تم الإرسال 
                                    <?php echo $notification['sent_at'] ? date('d/m H:i', strtotime($notification['sent_at'])) : ''; ?>
                                </span>
                                <button class="btn btn-outline-warning btn-sm" 
                                        onclick="resendNotification(<?php echo $notification['id']; ?>)">
                                    <i class="fas fa-redo me-1"></i>إعادة إرسال
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline-info btn-sm" 
                                    onclick="viewFullMessage(<?php echo $notification['id']; ?>)">
                                <i class="fas fa-eye me-1"></i>عرض كامل
                            </button>
                            <button class="btn btn-outline-danger btn-sm" 
                                    onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                <i class="fas fa-trash me-1"></i>حذف
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-inbox fs-1 text-muted mb-3"></i>
            <h4 class="text-muted">لا توجد إشعارات مطابقة</h4>
            <p class="text-muted">جرب تغيير فلاتر البحث أو إنشاء إشعارات جديدة</p>
            <a href="index.php?page=parcel_list" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>إنشاء إشعارات جديدة
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal رسالة مخصصة -->
<div class="modal fade" id="customMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>إنشاء رسالة مخصصة
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="customMessageForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">رقم الشحنة</label>
                                <input type="text" class="form-control" id="customParcelSearch" placeholder="ابحث برقم التتبع">
                                <input type="hidden" id="customParcelId">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">المستقبل</label>
                                <select class="form-select" id="customRecipientType">
                                    <option value="sender">الراسل</option>
                                    <option value="recipient">المستلم</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" class="form-control" id="customPhoneNumber" placeholder="01012345678 (سيتم إضافة رمز مصر تلقائياً)">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">نص الرسالة</label>
                        <textarea class="form-control" id="customMessageContent" rows="6" placeholder="اكتب رسالتك هنا...

يمكنك استخدام المتغيرات التالية:
{tracking_number} - رقم التتبع
{sender_name} - اسم الراسل
{recipient_name} - اسم المستلم
{cod_amount} - المبلغ
{status_reason} - سبب الحالة
{status_reason_note} - ملاحظة الحالة"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>المتغيرات المتاحة:</strong><br>
                        <code>{tracking_number}</code> - رقم التتبع<br>
                        <code>{sender_name}</code> - اسم الراسل<br>
                        <code>{recipient_name}</code> - اسم المستلم<br>
                        <code>{cod_amount}</code> - المبلغ<br>
                        <code>{status_reason}</code> - سبب الحالة<br>
                        <code>{status_reason_note}</code> - ملاحظة الحالة
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" onclick="saveCustomMessage()">
                    <i class="fas fa-save me-2"></i>حفظ الرسالة
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal تأجيل/رفض -->
<div class="modal fade" id="rejectionPostponementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>إشعار تأجيل أو رفض للراسل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectionForm">
                    <div class="mb-3">
                        <label class="form-label">رقم الشحنة</label>
                        <input type="text" class="form-control" id="rejectionParcelSearch" placeholder="ابحث برقم التتبع">
                        <input type="hidden" id="rejectionParcelId">
                    </div>
                    
                    <div class="row" id="parcelInfo" style="display: none;">
                        <div class="col-md-6">
                            <p><strong>الراسل:</strong> <span id="senderInfo"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>المستلم:</strong> <span id="recipientInfo"></span></p>
                        </div>
                        <div class="col-12">
                            <p><strong>الحالة الحالية:</strong> <span id="currentStatus"></span></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">قالب الرسالة</label>
                        <select class="form-select" id="messageTemplate" onchange="loadTemplate()">
                            <option value="">اختر قالب...</option>
                            <option value="postponed">رسالة تأجيل</option>
                            <option value="rejected">رسالة رفض</option>
                            <option value="custom">رسالة مخصصة</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">نص الرسالة للراسل</label>
                        <textarea class="form-control" id="rejectionMessage" rows="8" placeholder="سيتم ملؤها تلقائياً عند اختيار القالب..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>ملاحظة:</strong> سيتم إرسال هذه الرسالة للراسل فقط لإعلامه بحالة شحنته.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-warning" onclick="sendRejectionPostponement()">
                    <i class="fas fa-paper-plane me-2"></i>إنشاء الإشعار
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let selectedNotifications = [];

function updateSelection() {
    selectedNotifications = Array.from(document.querySelectorAll('.notification-checkbox:checked'))
                                 .map(cb => cb.value);
    
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    selectedCount.textContent = selectedNotifications.length;
    bulkActions.style.display = selectedNotifications.length > 0 ? 'block' : 'none';
}

function clearSelection() {
    document.querySelectorAll('.notification-checkbox').forEach(cb => cb.checked = false);
    updateSelection();
}

function markAsSent(id) {
    sendAjaxRequest('mark_sent', {notification_id: id});
}

function deleteNotification(id) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'سيتم حذف هذا الإشعار نهائياً',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            sendAjaxRequest('delete_notification', {notification_id: id});
        }
    });
}

function resendNotification(id) {
    sendAjaxRequest('resend_notification', {notification_id: id});
}

function bulkMarkSent() {
    if (selectedNotifications.length === 0) return;
    
    sendAjaxRequest('mark_multiple_sent', {
        notification_ids: JSON.stringify(selectedNotifications)
    });
}

function bulkDelete() {
    if (selectedNotifications.length === 0) return;
    
    Swal.fire({
        title: 'حذف متعدد',
        text: `هل تريد حذف ${selectedNotifications.length} إشعار؟`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف الكل',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            selectedNotifications.forEach(id => {
                sendAjaxRequest('delete_notification', {notification_id: id}, false);
            });
            setTimeout(() => location.reload(), 1000);
        }
    });
}

function viewFullMessage(id) {
    const messageElement = document.querySelector(`[data-id="${id}"] .message-preview`);
    const message = messageElement.textContent;
    
    Swal.fire({
        title: 'الرسالة الكاملة',
        text: message,
        icon: 'info',
        confirmButtonText: 'إغلاق',
        customClass: {
            popup: 'text-start'
        }
    });
}

function refreshNotifications() {
    location.reload();
}

function sendAjaxRequest(action, data, showResult = true, callback = null) {
    data.action = action;
    data.ajax = true;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(result => {
        if (showResult) {
            if (result.status === 'success') {
                Swal.fire('نجح!', result.message, 'success').then(() => {
                    if (callback) {
                        callback();
                    } else {
                        location.reload();
                    }
                });
            } else {
                Swal.fire('خطأ!', result.message, 'error');
            }
        } else if (callback) {
            callback();
        }
    })
    .catch(error => {
        if (showResult) {
            Swal.fire('خطأ!', 'حدث خطأ في الاتصال', 'error');
        }
    });
}

// تحديث تلقائي كل دقيقة للإشعارات المعلقة
setInterval(() => {
    if (document.querySelector('[name="filter_status"]').value === 'pending') {
        refreshNotifications();
    }
}, 60000);

// اختصارات لوحة المفاتيح
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey) {
        switch(e.key) {
            case 'r':
                e.preventDefault();
                refreshNotifications();
                break;
            case 'a':
                e.preventDefault();
                document.querySelectorAll('.notification-checkbox').forEach(cb => cb.checked = true);
                updateSelection();
                break;
        }
    }
});

// ===== وظائف الميزات الجديدة =====

// عرض modal الرسالة المخصصة
function showCustomMessageModal() {
    document.getElementById('customMessageModal').querySelector('.modal-body form').reset();
    new bootstrap.Modal(document.getElementById('customMessageModal')).show();
}

// عرض modal تأجيل/رفض
function showRejectionPostponementModal() {
    document.getElementById('rejectionPostponementModal').querySelector('.modal-body form').reset();
    document.getElementById('parcelInfo').style.display = 'none';
    new bootstrap.Modal(document.getElementById('rejectionPostponementModal')).show();
}

// بحث الشحنة للرسالة المخصصة
document.getElementById('customParcelSearch').addEventListener('input', function() {
    const trackingNumber = this.value.trim();
    if (trackingNumber.length >= 3) {
        searchParcel(trackingNumber, 'custom');
    }
});

// بحث الشحنة للتأجيل/الرفض
document.getElementById('rejectionParcelSearch').addEventListener('input', function() {
    const trackingNumber = this.value.trim();
    if (trackingNumber.length >= 3) {
        searchParcel(trackingNumber, 'rejection');
    }
});

// بحث في الشحنات
function searchParcel(trackingNumber, type) {
    // هذه دالة مبسطة - في الواقع ستحتاج لـ AJAX call لقاعدة البيانات
    fetch('search_parcel.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `tracking_number=${encodeURIComponent(trackingNumber)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.parcel) {
            if (type === 'custom') {
                document.getElementById('customParcelId').value = data.parcel.id;
                document.getElementById('customPhoneNumber').value = 
                    document.getElementById('customRecipientType').value === 'sender' 
                    ? data.parcel.sender_phone 
                    : data.parcel.recipient_phone;
            } else if (type === 'rejection') {
                document.getElementById('rejectionParcelId').value = data.parcel.id;
                document.getElementById('senderInfo').textContent = 
                    `${data.parcel.sender_name} (${data.parcel.sender_phone})`;
                document.getElementById('recipientInfo').textContent = 
                    `${data.parcel.recipient_name} (${data.parcel.recipient_phone})`;
                document.getElementById('currentStatus').textContent = data.parcel.status_name;
                document.getElementById('parcelInfo').style.display = 'block';
            }
        } else {
            if (type === 'custom') {
                document.getElementById('customParcelId').value = '';
                document.getElementById('customPhoneNumber').value = '';
            } else if (type === 'rejection') {
                document.getElementById('rejectionParcelId').value = '';
                document.getElementById('parcelInfo').style.display = 'none';
            }
        }
    })
    .catch(error => {
        console.error('خطأ في البحث:', error);
    });
}

// تحديث رقم الهاتف عند تغيير نوع المستقبل
document.getElementById('customRecipientType').addEventListener('change', function() {
    const trackingNumber = document.getElementById('customParcelSearch').value.trim();
    if (trackingNumber && document.getElementById('customParcelId').value) {
        searchParcel(trackingNumber, 'custom');
    }
});

// تنسيق رقم الهاتف
function formatPhoneNumber(phone) {
    // إزالة كل شيء عدا الأرقام
    phone = phone.replace(/\D/g, '');
    
    // إضافة رمز مصر إذا لزم الأمر
    if (phone.startsWith('01')) {
        phone = '2' + phone;
    } else if (!phone.startsWith('201') && phone.length > 0) {
        phone = '201' + phone.replace(/^0+/, '');
    }
    
    return phone;
}

// حفظ الرسالة المخصصة
function saveCustomMessage() {
    const parcelId = document.getElementById('customParcelId').value;
    const recipientType = document.getElementById('customRecipientType').value;
    let phoneNumber = document.getElementById('customPhoneNumber').value;
    const messageContent = document.getElementById('customMessageContent').value;
    
    if (!parcelId || !phoneNumber || !messageContent) {
        Swal.fire('خطأ!', 'يرجى ملء جميع الحقول المطلوبة', 'error');
        return;
    }
    
    // تنسيق رقم الهاتف
    phoneNumber = formatPhoneNumber(phoneNumber);
    
    sendAjaxRequest('save_custom_message', {
        parcel_id: parcelId,
        recipient_type: recipientType,
        phone_number: phoneNumber,
        message_content: messageContent
    }, true, function() {
        // إغلاق الـ modal
        bootstrap.Modal.getInstance(document.getElementById('customMessageModal')).hide();
        // إعادة تحديث الصفحة
        setTimeout(() => location.reload(), 1000);
    });
}

// تحميل قالب الرسالة
function loadTemplate() {
    const template = document.getElementById('messageTemplate').value;
    const messageTextarea = document.getElementById('rejectionMessage');
    
    const templates = {
        postponed: `تم تأجيل شحنتك

عزيزي {sender_name}

للأسف تم تأجيل توصيل شحنتك لظروف خارجة عن إرادتنا

رقم الشحنة: {tracking_number}
المستلم: {recipient_name}
قيمة التحصيل: {cod_amount} جنيه
سبب التأجيل: {status_reason}
ملاحظة: {status_reason_note}

سيتم إعادة المحاولة قريباً
سنتواصل معك لتحديد موعد جديد

شكراً لصبرك وتفهمك
شركة الشحن`,

        rejected: `تم رفض استلام الشحنة

عزيزي {sender_name}

للأسف تم رفض استلام شحنتك من المستلم

رقم الشحنة: {tracking_number}
المستلم: {recipient_name}
قيمة التحصيل: {cod_amount} جنيه
سبب الرفض: {status_reason}
ملاحظة: {status_reason_note}

الشحنة الآن في طريق العودة
سيتم التواصل معك لترتيب الاستلام أو إعادة المحاولة

للاستفسار يرجى التواصل معنا
شركة الشحن`,

        custom: ``
    };
    
    messageTextarea.value = templates[template] || '';
}

// إرسال إشعار تأجيل/رفض
function sendRejectionPostponement() {
    const parcelId = document.getElementById('rejectionParcelId').value;
    const customMessage = document.getElementById('rejectionMessage').value;
    
    if (!parcelId || !customMessage) {
        Swal.fire('خطأ!', 'يرجى اختيار شحنة وكتابة الرسالة', 'error');
        return;
    }
    
    sendAjaxRequest('send_rejection_postponement', {
        parcel_id: parcelId,
        custom_message: customMessage
    }, true, function() {
        // إغلاق الـ modal
        bootstrap.Modal.getInstance(document.getElementById('rejectionPostponementModal')).hide();
        // إعادة تحديث الصفحة
        setTimeout(() => location.reload(), 1000);
    });
}
</script>

</body>
</html>
