<?php
// صفحة إدارة إعدادات WhatsApp
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من صلاحيات المدير
if ($_SESSION['login_type'] != 1) {
    echo '<script>alert("ليس لديك صلاحية للوصول لهذه الصفحة"); window.history.back();</script>';
    exit;
}

include 'db_connect.php';

// معالجة النماذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_api_config') {
            $api_provider = $_POST['api_provider'];
            $api_url = $_POST['api_url'];
            $api_token = $_POST['api_token'];
            $phone_number = $_POST['phone_number'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $rate_limit = intval($_POST['rate_limit_per_minute']);
            
            // حذف الإعدادات القديمة
            $conn->query("DELETE FROM whatsapp_config");
            
            // إدراج الإعدادات الجديدة
            $stmt = $conn->prepare("INSERT INTO whatsapp_config (api_provider, api_url, api_token, phone_number, is_active, rate_limit_per_minute) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $api_provider, $api_url, $api_token, $phone_number, $is_active, $rate_limit);
            
            if ($stmt->execute()) {
                $success_message = "تم حفظ إعدادات API بنجاح";
            } else {
                $error_message = "فشل في حفظ الإعدادات: " . $conn->error;
            }
        }
        
        if ($action === 'update_notification_setting') {
            $setting_id = intval($_POST['setting_id']);
            $send_to_sender = isset($_POST['send_to_sender']) ? 1 : 0;
            $send_to_recipient = isset($_POST['send_to_recipient']) ? 1 : 0;
            $sender_template = $_POST['sender_message_template'];
            $recipient_template = $_POST['recipient_message_template'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE notification_settings SET send_to_sender = ?, send_to_recipient = ?, sender_message_template = ?, recipient_message_template = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("iissii", $send_to_sender, $send_to_recipient, $sender_template, $recipient_template, $is_active, $setting_id);
            
            if ($stmt->execute()) {
                $success_message = "تم تحديث إعدادات الإشعار بنجاح";
            } else {
                $error_message = "فشل في تحديث الإعدادات: " . $conn->error;
            }
        }
        
        if ($action === 'test_api') {
            $test_phone = $_POST['test_phone'];
            $test_message = $_POST['test_message'];
            
            include_once 'WhatsAppNotificationManager.php';
            $whatsapp_manager = new WhatsAppNotificationManager($conn);
            
            // محاولة إرسال رسالة تجريبية
            $result = $whatsapp_manager->sendMessage($test_phone, $test_message, 'test', 0, 0);
            
            if ($result) {
                $success_message = "تم إرسال الرسالة التجريبية بنجاح";
            } else {
                $error_message = "فشل في إرسال الرسالة التجريبية";
            }
        }
    }
}

// جلب الإعدادات الحالية
$api_config = $conn->query("SELECT * FROM whatsapp_config LIMIT 1")->fetch_assoc();
$notification_settings = $conn->query("SELECT ns.*, ps.name_ar as status_name FROM notification_settings ns LEFT JOIN parcel_status ps ON ns.status_id = ps.id ORDER BY ns.status_id")->fetch_all(MYSQLI_ASSOC);

// جلب آخر السجلات
$recent_logs = $conn->query("
    SELECT nl.*, p.tracking_number, ps.name_ar as status_name 
    FROM notification_logs nl 
    LEFT JOIN parcels p ON nl.parcel_id = p.id 
    LEFT JOIN parcel_status ps ON nl.status_id = ps.id 
    ORDER BY nl.created_at DESC 
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات إشعارات WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center mb-4">
                <i class="fab fa-whatsapp text-success fs-2 me-3"></i>
                <h2 class="mb-0">إعدادات إشعارات WhatsApp</h2>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- التبويبات -->
            <ul class="nav nav-pills mb-4" id="settingsTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="pill" href="#api-config">
                        <i class="fas fa-cog me-2"></i>إعدادات API
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#notifications">
                        <i class="fas fa-bell me-2"></i>إعدادات الإشعارات
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#test">
                        <i class="fas fa-test-tube me-2"></i>اختبار الإرسال
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#logs">
                        <i class="fas fa-history me-2"></i>سجل الإرسال
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- إعدادات API -->
                <div class="tab-pane fade show active" id="api-config">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-plug me-2"></i>إعدادات WhatsApp API</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_api_config">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">مقدم الخدمة</label>
                                            <select name="api_provider" class="form-select" required>
                                                <option value="twilio" <?php echo ($api_config['api_provider'] ?? '') === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                                <option value="maytapi" <?php echo ($api_config['api_provider'] ?? '') === 'maytapi' ? 'selected' : ''; ?>>Maytapi</option>
                                                <option value="chatapi" <?php echo ($api_config['api_provider'] ?? '') === 'chatapi' ? 'selected' : ''; ?>>ChatAPI</option>
                                                <option value="custom" <?php echo ($api_config['api_provider'] ?? '') === 'custom' ? 'selected' : ''; ?>>مخصص</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">رقم واتساب الشركة</label>
                                            <input type="text" name="phone_number" class="form-control" 
                                                   value="<?php echo htmlspecialchars($api_config['phone_number'] ?? ''); ?>" 
                                                   placeholder="+201234567890" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">رابط API</label>
                                    <input type="url" name="api_url" class="form-control" 
                                           value="<?php echo htmlspecialchars($api_config['api_url'] ?? ''); ?>" 
                                           placeholder="https://api.example.com">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">مفتاح API</label>
                                    <input type="password" name="api_token" class="form-control" 
                                           value="<?php echo htmlspecialchars($api_config['api_token'] ?? ''); ?>" 
                                           placeholder="API Token">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">حد الإرسال (رسالة/دقيقة)</label>
                                            <input type="number" name="rate_limit_per_minute" class="form-control" 
                                                   value="<?php echo $api_config['rate_limit_per_minute'] ?? 30; ?>" min="1" max="100">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 pt-4">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active" class="form-check-input" 
                                                       <?php echo ($api_config['is_active'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label">تفعيل إرسال الإشعارات</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>حفظ الإعدادات
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- إعدادات الإشعارات -->
                <div class="tab-pane fade" id="notifications">
                    <div class="row">
                        <?php foreach ($notification_settings as $setting): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <span class="status-badge bg-primary"><?php echo $setting['status_name']; ?></span>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_notification_setting">
                                        <input type="hidden" name="setting_id" value="<?php echo $setting['id']; ?>">
                                        
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <div class="form-check">
                                                    <input type="checkbox" name="send_to_sender" class="form-check-input" 
                                                           <?php echo $setting['send_to_sender'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">إشعار الراسل</label>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-check">
                                                    <input type="checkbox" name="send_to_recipient" class="form-check-input" 
                                                           <?php echo $setting['send_to_recipient'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">إشعار المستلم</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input type="checkbox" name="is_active" class="form-check-input" 
                                                   <?php echo $setting['is_active'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">تفعيل الإشعار لهذه الحالة</label>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-save me-1"></i>حفظ
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                onclick="editTemplates(<?php echo $setting['id']; ?>)">
                                            <i class="fas fa-edit me-1"></i>تعديل القوالب
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- اختبار الإرسال -->
                <div class="tab-pane fade" id="test">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-test-tube me-2"></i>اختبار إرسال الرسائل</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="test_api">
                                
                                <div class="mb-3">
                                    <label class="form-label">رقم الهاتف للاختبار</label>
                                    <input type="text" name="test_phone" class="form-control" placeholder="+201234567890" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">نص الرسالة</label>
                                    <textarea name="test_message" class="form-control" rows="4" required>🧪 رسالة تجريبية

هذه رسالة اختبار من نظام إدارة الشحن
التاريخ: <?php echo date('Y-m-d H:i'); ?>

تم الإرسال بنجاح ✅</textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-paper-plane me-2"></i>إرسال رسالة تجريبية
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- سجل الإرسال -->
                <div class="tab-pane fade" id="logs">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>سجل الإرسال (آخر 20 محاولة)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>رقم الشحنة</th>
                                            <th>الهاتف</th>
                                            <th>المستقبل</th>
                                            <th>الحالة</th>
                                            <th>حالة الإرسال</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('m-d H:i', strtotime($log['created_at'])); ?></td>
                                            <td><?php echo $log['tracking_number'] ?? 'N/A'; ?></td>
                                            <td><?php echo $log['phone_number']; ?></td>
                                            <td><?php echo $log['recipient_type'] === 'sender' ? 'الراسل' : 'المستلم'; ?></td>
                                            <td><?php echo $log['status_name'] ?? 'N/A'; ?></td>
                                            <td>
                                                <?php if ($log['send_status'] === 'sent'): ?>
                                                    <span class="badge bg-success">تم الإرسال</span>
                                                <?php elseif ($log['send_status'] === 'failed'): ?>
                                                    <span class="badge bg-danger">فشل</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">معلق</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editTemplates(settingId) {
    // يمكن إضافة modal لتعديل قوالب الرسائل
    alert('سيتم إضافة هذه الميزة قريباً');
}
</script>

</body>
</html>











