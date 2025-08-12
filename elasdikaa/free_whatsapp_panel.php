<?php
// لوحة إدارة إشعارات WhatsApp المجانية
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';
include_once 'FreeWhatsAppNotifications.php';

$free_whatsapp = new FreeWhatsAppNotifications($conn);

// معالجة طلبات الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'mark_sent' && isset($_POST['notification_id'])) {
            $notification_id = intval($_POST['notification_id']);
            if ($free_whatsapp->markAsSent($notification_id)) {
                $success_message = "تم تحديد الإشعار كمُرسل";
            }
        }
        
        if ($_POST['action'] === 'test_notification') {
            $test_parcel_id = intval($_POST['test_parcel_id']);
            $test_status = intval($_POST['test_status']);
            
            $result = $free_whatsapp->sendFreeNotification($test_parcel_id, $test_status);
            if ($result) {
                $success_message = "تم إنشاء روابط الإشعار التجريبية";
                $test_result = $result;
            } else {
                $error_message = "فشل في إنشاء الإشعارات";
            }
        }
    }
}

// جلب الإشعارات المعلقة
$pending_notifications = $free_whatsapp->getPendingNotifications(50);

// جلب قائمة الحالات
$statuses = $conn->query("SELECT id, name_ar FROM parcel_status ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إشعارات WhatsApp المجانية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .notification-card {
            border-right: 4px solid #25d366;
            transition: all 0.3s ease;
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .whatsapp-link {
            background: linear-gradient(45deg, #25d366, #128c7e);
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .whatsapp-link:hover {
            background: linear-gradient(45deg, #128c7e, #25d366);
            color: white;
            transform: scale(1.05);
        }
        .message-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            font-size: 0.85rem;
            max-height: 100px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex align-items-center mb-4">
                <i class="fab fa-whatsapp text-success fs-1 me-3"></i>
                <div>
                    <h2 class="mb-1">إشعارات WhatsApp المجانية</h2>
                    <p class="text-muted mb-0">إدارة الإشعارات بدون تكلفة - اضغط الرابط وأرسل!</p>
                </div>
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

            <!-- إحصائيات سريعة -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-paper-plane fs-2 mb-2"></i>
                            <h5><?php echo count($pending_notifications); ?></h5>
                            <p class="mb-0">إشعارات معلقة</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-money-bill-wave fs-2 mb-2"></i>
                            <h5>مجاني 100%</h5>
                            <p class="mb-0">بدون تكلفة</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-mouse-pointer fs-2 mb-2"></i>
                            <h5>ضغطة واحدة</h5>
                            <p class="mb-0">إرسال فوري</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-cogs fs-2 mb-2"></i>
                            <h5>تلقائي</h5>
                            <p class="mb-0">عند تغيير الحالة</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تبويبات -->
            <ul class="nav nav-pills mb-4">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="pill" href="#pending-notifications">
                        <i class="fas fa-clock me-2"></i>الإشعارات المعلقة
                        <span class="badge bg-light text-dark ms-1"><?php echo count($pending_notifications); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#test-notification">
                        <i class="fas fa-flask me-2"></i>اختبار الإشعار
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#how-it-works">
                        <i class="fas fa-question-circle me-2"></i>كيف يعمل؟
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- الإشعارات المعلقة -->
                <div class="tab-pane fade show active" id="pending-notifications">
                    <?php if (count($pending_notifications) > 0): ?>
                        <div class="row">
                            <?php foreach ($pending_notifications as $notification): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="card notification-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="status-badge bg-primary"><?php echo $notification['status_name']; ?></span>
                                            <small class="text-muted ms-2"><?php echo $notification['tracking_number']; ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo date('d/m H:i', strtotime($notification['created_at'])); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-2">
                                                    <strong>📱 المستقبل:</strong> 
                                                    <?php echo $notification['recipient_type'] === 'sender' ? 'الراسل' : 'المستلم'; ?>
                                                </p>
                                                <p class="mb-2">
                                                    <strong>📞 الهاتف:</strong> 
                                                    <?php echo $notification['phone_number']; ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="message-preview">
                                                    <?php echo substr($notification['message_content'], 0, 100) . '...'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="<?php echo $notification['whatsapp_link']; ?>" 
                                               target="_blank" 
                                               class="whatsapp-link">
                                                <i class="fab fa-whatsapp me-2"></i>إرسال عبر WhatsApp
                                            </a>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="mark_sent">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-check me-1"></i>تم الإرسال
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fs-1 text-muted mb-3"></i>
                            <h4 class="text-muted">لا توجد إشعارات معلقة</h4>
                            <p class="text-muted">سيتم إنشاء الإشعارات تلقائياً عند تغيير حالة الشحنات</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- اختبار الإشعار -->
                <div class="tab-pane fade" id="test-notification">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-flask me-2"></i>اختبار إنشاء الإشعارات</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="test_notification">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">رقم الشحنة للاختبار</label>
                                            <input type="number" name="test_parcel_id" class="form-control" 
                                                   placeholder="أدخل رقم شحنة موجودة" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">الحالة الجديدة</label>
                                            <select name="test_status" class="form-select" required>
                                                <?php foreach ($statuses as $status): ?>
                                                <option value="<?php echo $status['id']; ?>"><?php echo $status['name_ar']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>إنشاء روابط الإشعار
                                </button>
                            </form>
                            
                            <?php if (isset($test_result)): ?>
                            <hr>
                            <h6>نتيجة الاختبار:</h6>
                            <div class="row">
                                <?php foreach ($test_result as $type => $data): ?>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6><?php echo $data['type'] === 'sender' ? 'الراسل' : 'المستلم'; ?></h6>
                                            <p><strong>الهاتف:</strong> <?php echo $data['phone']; ?></p>
                                            <a href="<?php echo $data['whatsapp_link']; ?>" target="_blank" class="whatsapp-link">
                                                <i class="fab fa-whatsapp me-2"></i>إرسال الرسالة
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- كيف يعمل -->
                <div class="tab-pane fade" id="how-it-works">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>كيف يعمل النظام المجاني؟</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-cogs text-primary me-2"></i>آلية العمل:</h6>
                                    <ol class="list-group list-group-flush">
                                        <li class="list-group-item">تغيير حالة الشحنة تلقائياً</li>
                                        <li class="list-group-item">إنشاء رسالة مخصصة للراسل/المستلم</li>
                                        <li class="list-group-item">إنشاء رابط WhatsApp جاهز</li>
                                        <li class="list-group-item">الضغط على الرابط يفتح WhatsApp</li>
                                        <li class="list-group-item">الرسالة جاهزة - فقط اضغط إرسال!</li>
                                    </ol>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-star text-warning me-2"></i>المزايا:</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item">✅ مجاني 100% - بدون تكلفة</li>
                                        <li class="list-group-item">✅ لا يحتاج API أو اشتراك</li>
                                        <li class="list-group-item">✅ يعمل على جميع الأجهزة</li>
                                        <li class="list-group-item">✅ رسائل مخصصة تلقائياً</li>
                                        <li class="list-group-item">✅ سجل كامل للإشعارات</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>ملاحظة مهمة:</h6>
                                <p class="mb-0">هذا النظام شبه تلقائي - يقوم بإنشاء الرسائل والروابط تلقائياً، لكن الإرسال الفعلي يتم بضغطة واحدة منك. هذا يضمن التحكم الكامل ويتجنب مشاكل الرسائل المزعجة.</p>
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
// تحديث الصفحة كل دقيقة للحصول على إشعارات جديدة
setInterval(function() {
    if (document.querySelector('.nav-link.active').getAttribute('href') === '#pending-notifications') {
        location.reload();
    }
}, 60000);

// إضافة صوت تنبيه عند وجود إشعارات جديدة
<?php if (count($pending_notifications) > 0): ?>
if (Notification.permission === "granted") {
    new Notification("إشعارات WhatsApp معلقة", {
        body: "لديك <?php echo count($pending_notifications); ?> إشعار معلق للإرسال",
        icon: "https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/svgs/brands/whatsapp.svg"
    });
}
<?php endif; ?>
</script>

</body>
</html>











