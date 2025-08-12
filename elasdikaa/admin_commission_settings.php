<?php
require_once 'middleware.php';
require_once 'error_handler.php';
SecurityMiddleware::checkSession();
requireLogin();

// التحقق من صلاحيات المشرف
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$pageTitle = 'إعدادات العمولة';
include 'includes/header.php';

// معالجة حفظ الإعدادات
if ($_POST['action'] ?? '' === 'save_settings') {
    try {
        $commission_cut_type = $_POST['commission_cut_type'] ?? 'fixed';
        $commission_cut_value = (float)($_POST['commission_cut_value'] ?? 0);
        
        $sql = "INSERT INTO app_settings (setting_key, setting_value) VALUES 
                ('commission_cut_type', ?), ('commission_cut_value', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sd', $commission_cut_type, $commission_cut_value);
        
        if ($stmt->execute()) {
            $success_message = 'تم حفظ الإعدادات بنجاح';
        } else {
            $error_message = 'فشل في حفظ الإعدادات';
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = 'خطأ: ' . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$current_settings = [];
try {
    $sql = "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('commission_cut_type', 'commission_cut_value')";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error_message = 'خطأ في جلب الإعدادات: ' . $e->getMessage();
}

$commission_cut_type = $current_settings['commission_cut_type'] ?? 'fixed';
$commission_cut_value = $current_settings['commission_cut_value'] ?? 0;
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">إعدادات العمولة</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="commission_cut_type">نوع خصم العمولة</label>
                                    <select class="form-control" id="commission_cut_type" name="commission_cut_type" required>
                                        <option value="fixed" <?php echo $commission_cut_type === 'fixed' ? 'selected' : ''; ?>>
                                            مبلغ ثابت
                                        </option>
                                        <option value="percentage" <?php echo $commission_cut_type === 'percentage' ? 'selected' : ''; ?>>
                                            نسبة مئوية
                                        </option>
                                    </select>
                                    <small class="form-text text-muted">
                                        اختر نوع الخصم من عمولة المندوب
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="commission_cut_value">قيمة الخصم</label>
                                    <input type="number" class="form-control" id="commission_cut_value" 
                                           name="commission_cut_value" step="0.01" min="0" 
                                           value="<?php echo htmlspecialchars($commission_cut_value); ?>" required>
                                    <small class="form-text text-muted">
                                        <?php echo $commission_cut_type === 'percentage' ? 'النسبة المئوية (مثال: 10)' : 'المبلغ بالجنيه (مثال: 5)'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> شرح الإعدادات:</h6>
                                    <ul class="mb-0">
                                        <li><strong>مبلغ ثابت:</strong> سيتم خصم مبلغ ثابت من عمولة كل مندوب</li>
                                        <li><strong>نسبة مئوية:</strong> سيتم خصم نسبة مئوية من عمولة كل مندوب</li>
                                        <li>هذه الإعدادات تطبق على جميع الشحنات الجديدة</li>
                                        <li>يمكن تعديل القيم لكل شحنة على حدة من صفحة إضافة الشحنة</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> حفظ الإعدادات
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right"></i> رجوع
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function(){
    // تحديث نص المساعدة عند تغيير نوع الخصم
    $('#commission_cut_type').on('change', function(){
        var type = $(this).val();
        var helpText = type === 'percentage' ? 'النسبة المئوية (مثال: 10)' : 'المبلغ بالجنيه (مثال: 5)';
        $('#commission_cut_value').next('.form-text').text(helpText);
    });
});
</script>

<?php include 'includes/footer.php'; ?>



