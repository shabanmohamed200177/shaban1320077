<?php
// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// إنشاء جدول agent_users إذا لم يكن موجوداً
$create_table = "CREATE TABLE IF NOT EXISTS agent_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
)";
$conn->query($create_table);

$success_message = '';
$error_message = '';
$edit_mode = false;
$agent_data = [];

// التحقق من وضع التعديل
if (isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_mode = true;
    $agent_id = intval($_GET['id']);
    
    // جلب بيانات الوكيل للتعديل
    $stmt = $conn->prepare("SELECT * FROM agents WHERE id = ?");
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $agent_data = $result->fetch_assoc();
        
        // التحقق من وجود حساب للوكيل
        $check_account = $conn->prepare("SELECT id FROM agent_users WHERE agent_id = ?");
        $check_account->bind_param('i', $agent_id);
        $check_account->execute();
        $agent_data['has_account'] = $check_account->get_result()->num_rows > 0;
    } else {
        header('Location: index.php?page=agent_list');
        exit;
    }
}

// معالجة تحديث بيانات الوكيل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_agent'])) {
    $agent_id = intval($_POST['agent_id']);
    $company_name = $_POST['company_name'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $geo_area = $_POST['geo_area'] ?? '';
    $coop_type = $_POST['coop_type'] ?? '';
    $commission_type = $_POST['commission_type'] ?? '';
    $commission_value = $_POST['commission_value'] ?? 0;
    $status = $_POST['status'] ?? 1;
    
    // معلومات الحساب
    $create_account = isset($_POST['create_account_existing']);
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($company_name)) {
        $error_message = "اسم الشركة مطلوب.";
    } elseif ($create_account) {
        // التحقق من بيانات الحساب
        if (empty($username) || empty($password)) {
            $error_message = "اسم المستخدم وكلمة المرور مطلوبان لإنشاء الحساب.";
        } elseif ($password !== $confirm_password) {
            $error_message = "كلمة المرور وتأكيدها غير متطابقان.";
        } elseif (strlen($password) < 6) {
            $error_message = "كلمة المرور يجب أن تكون 6 أحرف على الأقل.";
        } else {
            // التحقق من عدم وجود اسم المستخدم مسبقاً
            $check_username = $conn->prepare("SELECT id FROM agent_users WHERE username = ?");
            $check_username->bind_param('s', $username);
            $check_username->execute();
            if ($check_username->get_result()->num_rows > 0) {
                $error_message = "اسم المستخدم موجود مسبقاً. اختر اسم مختلف.";
            }
        }
    }
    
    if (empty($error_message)) {
        // تحديث بيانات الوكيل
        $update_stmt = $conn->prepare("UPDATE agents SET company_name = ?, contact_person = ?, phone = ?, email = ?, address = ?, geo_area = ?, coop_type = ?, commission_type = ?, commission_value = ?, status = ? WHERE id = ?");
        $update_stmt->bind_param('ssssssssdii', $company_name, $contact_person, $phone, $email, $address, $geo_area, $coop_type, $commission_type, $commission_value, $status, $agent_id);
        
        if ($update_stmt->execute()) {
            $success_message = "تم تحديث بيانات الوكيل بنجاح!";
            
            // إنشاء حساب للوكيل إذا تم طلب ذلك
            if ($create_account) {
                // تشفير كلمة المرور
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // إدراج حساب الوكيل
                $insert_user = $conn->prepare("INSERT INTO agent_users (agent_id, username, password) VALUES (?, ?, ?)");
                $insert_user->bind_param('iss', $agent_id, $username, $hashed_password);
                
                if ($insert_user->execute()) {
                    $success_message .= "<br>تم إنشاء حساب للوكيل بنجاح! يمكن للوكيل الآن تسجيل الدخول باستخدام: $username";
                } else {
                    $success_message .= "<br>تم تحديث البيانات، لكن حدث خطأ في إنشاء الحساب.";
                }
            }
            
            // إعادة جلب البيانات المحدثة
            $stmt = $conn->prepare("SELECT * FROM agents WHERE id = ?");
            $stmt->bind_param('i', $agent_id);
            $stmt->execute();
            $agent_data = $stmt->get_result()->fetch_assoc();
            
            // إعادة التحقق من وجود حساب
            $check_account = $conn->prepare("SELECT id FROM agent_users WHERE agent_id = ?");
            $check_account->bind_param('i', $agent_id);
            $check_account->execute();
            $agent_data['has_account'] = $check_account->get_result()->num_rows > 0;
        } else {
            $error_message = "حدث خطأ في تحديث بيانات الوكيل.";
        }
    }
}

// معالجة إضافة وكيل جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_agent'])) {
    $company_name = $_POST['company_name'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $geo_area = $_POST['geo_area'] ?? '';
    $coop_type = $_POST['coop_type'] ?? '';
    $commission_type = $_POST['commission_type'] ?? '';
    $commission_value = $_POST['commission_value'] ?? 0;
    $status = $_POST['status'] ?? 1;
    
    // معلومات الحساب
    $create_account = isset($_POST['create_account']);
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // التحقق من البيانات الأساسية
    if (empty($company_name)) {
        $error_message = "اسم الشركة مطلوب.";
    } elseif ($create_account) {
        // التحقق من بيانات الحساب
        if (empty($username) || empty($password)) {
            $error_message = "اسم المستخدم وكلمة المرور مطلوبان لإنشاء الحساب.";
        } elseif ($password !== $confirm_password) {
            $error_message = "كلمة المرور وتأكيدها غير متطابقان.";
        } elseif (strlen($password) < 6) {
            $error_message = "كلمة المرور يجب أن تكون 6 أحرف على الأقل.";
        } else {
            // التحقق من عدم وجود اسم المستخدم مسبقاً
            $check_username = $conn->prepare("SELECT id FROM agent_users WHERE username = ?");
            $check_username->bind_param('s', $username);
            $check_username->execute();
            if ($check_username->get_result()->num_rows > 0) {
                $error_message = "اسم المستخدم موجود مسبقاً. اختر اسم مختلف.";
            }
        }
    }
    
    if (empty($error_message)) {
        // إدراج بيانات الوكيل
        $insert_agent = $conn->prepare("INSERT INTO agents (company_name, contact_person, phone, email, address, geo_area, coop_type, commission_type, commission_value, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_agent->bind_param('ssssssssdi', $company_name, $contact_person, $phone, $email, $address, $geo_area, $coop_type, $commission_type, $commission_value, $status);
        
        if ($insert_agent->execute()) {
            $agent_id = $conn->insert_id;
            
            // إنشاء حساب للوكيل إذا تم طلب ذلك
            if ($create_account) {
                // تشفير كلمة المرور
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // إدراج حساب الوكيل
                $insert_user = $conn->prepare("INSERT INTO agent_users (agent_id, username, password) VALUES (?, ?, ?)");
                $insert_user->bind_param('iss', $agent_id, $username, $hashed_password);
                
                if ($insert_user->execute()) {
                    $success_message = "تم إضافة الوكيل وإنشاء حسابه بنجاح! يمكن للوكيل الآن تسجيل الدخول باستخدام: $username";
                } else {
                    $success_message = "تم إضافة الوكيل بنجاح، لكن حدث خطأ في إنشاء الحساب.";
                }
            } else {
                $success_message = "تم إضافة الوكيل بنجاح!";
            }
    } else {
            $error_message = "حدث خطأ في إضافة الوكيل.";
        }
    }
}

// جلب المحافظات
$governorates = $conn->query("SELECT * FROM governorates ORDER BY name");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? 'تعديل الوكيل' : 'إضافة وكيل جديد'; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; direction: rtl; background-color: #f4f7f6; }
        .card { border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: none; }
        .card-header { background-color: #17a2b8; color: white; border-radius: 1rem 1rem 0 0; padding: 1.5rem; text-align: center; }
        .form-control, .form-select { border-radius: 0.5rem; }
        .btn { border-radius: 0.5rem; }
        .feature-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        .account-section {
            background-color: #f8f9fa;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 2px dashed #17a2b8;
        }
        .form-check-input:checked {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .password-strength {
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
    </style>
</head>
<body>

<div class="container-fluid py-4" style="background-color: #f4f7f6; min-height: 100vh;">
    
    <!-- مميزات النظام الجديد -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="feature-card">
                <i class="fas fa-user-shield fa-2x mb-2"></i>
                <h5>نظام حسابات الوكلاء</h5>
                <p class="mb-0">إنشاء حساب منفصل لكل وكيل للوصول لشحناته</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h5>لوحة تحكم خاصة</h5>
                <p class="mb-0">إحصائيات وتقارير مخصصة لكل وكيل</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <i class="fas fa-mobile-alt fa-2x mb-2"></i>
                <h5>وصول محمول</h5>
                <p class="mb-0">واجهة متجاوبة للعمل من أي جهاز</p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <?php if ($edit_mode): ?>
                            <i class="fas fa-edit me-2"></i>تعديل بيانات الوكيل: <?php echo htmlspecialchars($agent_data['company_name']); ?>
                        <?php else: ?>
                            <i class="fas fa-user-plus me-2"></i>إضافة وكيل جديد
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="agentForm">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="agent_id" value="<?php echo $agent_data['id']; ?>">
                        <?php endif; ?>
                        <!-- معلومات الوكيل الأساسية -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">اسم الشركة <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" required 
                                           value="<?php echo htmlspecialchars($edit_mode ? $agent_data['company_name'] : ($_POST['company_name'] ?? '')); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">اسم المسؤول</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person"
                                           value="<?php echo htmlspecialchars($edit_mode ? $agent_data['contact_person'] : ($_POST['contact_person'] ?? '')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">رقم الهاتف</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($edit_mode ? $agent_data['phone'] : ($_POST['phone'] ?? '')); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">البريد الإلكتروني</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($edit_mode ? $agent_data['email'] : ($_POST['email'] ?? '')); ?>">
                                </div>
                            </div>
    </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="geo_area" class="form-label">النطاق الجغرافي</label>
                                    <input type="text" class="form-control" id="geo_area" name="geo_area"
                                           value="<?php echo htmlspecialchars($edit_mode ? $agent_data['geo_area'] : ($_POST['geo_area'] ?? '')); ?>">
        </div>
        </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="address" class="form-label">العنوان</label>
                                    <input type="text" class="form-control" id="address" name="address"
                                           value="<?php echo htmlspecialchars($edit_mode ? $agent_data['address'] : ($_POST['address'] ?? '')); ?>">
        </div>
        </div>
          </div>

                        <!-- نوع التعاون والعمولة -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="coop_type" class="form-label">نوع التعاون</label>
                                    <select class="form-select" id="coop_type" name="coop_type">
                                        <?php $selected_coop = $edit_mode ? $agent_data['coop_type'] : ($_POST['coop_type'] ?? 'exchange'); ?>
                                        <option value="exchange" <?php echo $selected_coop == 'exchange' ? 'selected' : ''; ?>>تبادل شحنات (شراكة متبادلة)</option>
                                        <option value="to_them" <?php echo $selected_coop == 'to_them' ? 'selected' : ''; ?>>نرسل لهم الطلبيات فقط</option>
                                        <option value="from_them" <?php echo $selected_coop == 'from_them' ? 'selected' : ''; ?>>يطلبون منا الشحن فقط</option>
                                    </select>
          </div>
        </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="commission_type" class="form-label">نوع العمولة</label>
                                    <select class="form-select" id="commission_type" name="commission_type">
                                        <?php $selected_commission = $edit_mode ? $agent_data['commission_type'] : ($_POST['commission_type'] ?? 'fixed'); ?>
                                        <option value="fixed" <?php echo $selected_commission == 'fixed' ? 'selected' : ''; ?>>مبلغ ثابت لكل شحنة</option>
                                        <option value="percent" <?php echo $selected_commission == 'percent' ? 'selected' : ''; ?>>نسبة مئوية من قيمة الشحنة</option>
          </select>
        </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="commission_value" class="form-label">قيمة العمولة <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="commission_value" name="commission_value" required min="0"
                                           value="<?php echo htmlspecialchars($edit_mode ? $agent_data['commission_value'] : ($_POST['commission_value'] ?? '')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">الحالة</label>
                                    <select class="form-select" id="status" name="status">
                                        <?php $selected_status = $edit_mode ? $agent_data['status'] : ($_POST['status'] ?? '1'); ?>
                                        <option value="1" <?php echo $selected_status == '1' ? 'selected' : ''; ?>>نشط</option>
                                        <option value="0" <?php echo $selected_status == '0' ? 'selected' : ''; ?>>غير نشط</option>
            </select>
          </div>
                            </div>
                        </div>

                        <?php if (!$edit_mode): ?>
                        <!-- قسم إنشاء الحساب -->
                        <hr class="my-4">
                        <div class="account-section">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="create_account" name="create_account" 
                                       <?php echo isset($_POST['create_account']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="create_account">
                                    <i class="fas fa-user-plus me-2"></i>إنشاء حساب للوكيل
                                </label>
                                <div class="text-muted mt-1">
                                    سيتمكن الوكيل من تسجيل الدخول لعرض شحناته وإدارتها
                                </div>
                            </div>

                            <div id="account_fields" style="display: <?php echo isset($_POST['create_account']) ? 'block' : 'none'; ?>;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                            <div class="form-text">يُستخدم لتسجيل الدخول</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="password" name="password">
                                            <div id="password_strength" class="password-strength"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                            <div id="password_match" class="password-strength"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>مميزات حساب الوكيل:</h6>
                                    <ul class="mb-0">
                                        <li>عرض شحناته فقط</li>
                                        <li>تحديث حالات الشحنات</li>
                                        <li>عرض الإحصائيات والتقارير المالية</li>
                                        <li>تحميل تقارير مخصصة</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($edit_mode && !$agent_data['has_account']): ?>
                        <!-- قسم إنشاء حساب للوكيل الحالي -->
                        <hr class="my-4">
                        <div class="account-section" style="background-color: #e8f4f8; border-color: #17a2b8;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>هذا الوكيل لا يملك حساب للدخول إلى النظام حالياً</strong><br>
                                يمكنك إنشاء حساب له الآن ليتمكن من الدخول وإدارة شحناته بنفسه
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="create_account_existing" name="create_account_existing" 
                                       <?php echo isset($_POST['create_account_existing']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="create_account_existing">
                                    <i class="fas fa-user-plus me-2"></i>إنشاء حساب لهذا الوكيل
                                </label>
                                <div class="text-muted mt-1">
                                    سيتمكن الوكيل من تسجيل الدخول لعرض شحناته وتحديث حالاتها
                                </div>
                            </div>

                            <div id="account_fields_existing" style="display: <?php echo isset($_POST['create_account_existing']) ? 'block' : 'none'; ?>;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="username_existing" class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="username_existing" name="username" 
                                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                            <div class="form-text">يُستخدم لتسجيل الدخول</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="password_existing" class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="password_existing" name="password">
                                            <div id="password_strength_existing" class="password-strength"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="confirm_password_existing" class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password_existing" name="confirm_password">
                                            <div id="password_match_existing" class="password-strength"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-success">
                                    <h6><i class="fas fa-check-circle me-2"></i>مميزات حساب الوكيل:</h6>
                                    <ul class="mb-0">
                                        <li>عرض شحناته فقط (المرسلة إليه والمستلمة منه)</li>
                                        <li>تحديث حالات الشحنات</li>
                                        <li>عرض الإحصائيات والتقارير المالية</li>
                                        <li>تحميل تقارير مخصصة</li>
                                        <li>العمل من أي جهاز ومن أي مكان</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($edit_mode && $agent_data['has_account']): ?>
                        <!-- عرض معلومات الحساب الموجود -->
                        <hr class="my-4">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>هذا الوكيل يملك حساب فعال في النظام</strong><br>
                            يمكنه تسجيل الدخول عبر: <a href="agent_login.php" target="_blank" class="btn btn-sm btn-primary">صفحة دخول الوكلاء</a>
                        </div>
                        <?php endif; ?>

                        <!-- أزرار التحكم -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                    <a href="index.php?page=agent_list" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>العودة للقائمة
                                    </a>
                                    <?php if ($edit_mode): ?>
                                        <button type="submit" name="update_agent" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i>تحديث البيانات
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="add_agent" class="btn btn-success btn-lg">
                                            <i class="fas fa-save me-2"></i>حفظ الوكيل
                                        </button>
                                    <?php endif; ?>
          </div>
        </div>
        </div>
      </form>
    </div>
  </div>
</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const createAccountCheck = document.getElementById('create_account');
    const accountFields = document.getElementById('account_fields');
    
    // التحقق من وجود العناصر (فقط في وضع الإضافة)
    if (createAccountCheck && accountFields) {
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('password_strength');
        const passwordMatch = document.getElementById('password_match');
        
        setupAccountSection(createAccountCheck, accountFields, passwordField, confirmPasswordField, passwordStrength, passwordMatch, 'username');
    }
    
    // للوكلاء الحاليين
    const createAccountExistingCheck = document.getElementById('create_account_existing');
    const accountFieldsExisting = document.getElementById('account_fields_existing');
    
    if (createAccountExistingCheck && accountFieldsExisting) {
        const passwordFieldExisting = document.getElementById('password_existing');
        const confirmPasswordFieldExisting = document.getElementById('confirm_password_existing');
        const passwordStrengthExisting = document.getElementById('password_strength_existing');
        const passwordMatchExisting = document.getElementById('password_match_existing');
        
        setupAccountSection(createAccountExistingCheck, accountFieldsExisting, passwordFieldExisting, confirmPasswordFieldExisting, passwordStrengthExisting, passwordMatchExisting, 'username_existing');
    }
    
    function setupAccountSection(checkElement, fieldsElement, passwordField, confirmPasswordField, passwordStrength, passwordMatch, usernameFieldId) {
        // إظهار/إخفاء حقول الحساب
        checkElement.addEventListener('change', function() {
            if (this.checked) {
                fieldsElement.style.display = 'block';
                document.getElementById(usernameFieldId).required = true;
                passwordField.required = true;
                confirmPasswordField.required = true;
            } else {
                fieldsElement.style.display = 'none';
                document.getElementById(usernameFieldId).required = false;
                passwordField.required = false;
                confirmPasswordField.required = false;
            }
        });

        // فحص قوة كلمة المرور
        passwordField.addEventListener('input', function() {
            const password = this.value;
            let strength = '';
            let className = '';

            if (password.length === 0) {
                strength = '';
            } else if (password.length < 6) {
                strength = 'ضعيفة - أقل من 6 أحرف';
                className = 'strength-weak';
            } else if (password.length >= 6 && password.length < 8) {
                strength = 'متوسطة';
                className = 'strength-medium';
            } else if (password.length >= 8) {
                strength = 'قوية';
                className = 'strength-strong';
            }

            passwordStrength.textContent = strength;
            passwordStrength.className = 'password-strength ' + className;
        });

        // فحص تطابق كلمة المرور
        confirmPasswordField.addEventListener('input', function() {
            const password = passwordField.value;
            const confirmPassword = this.value;
            
            if (confirmPassword === '') {
                passwordMatch.textContent = '';
                passwordMatch.className = 'password-strength';
            } else if (password === confirmPassword) {
                passwordMatch.textContent = 'متطابقة ✓';
                passwordMatch.className = 'password-strength strength-strong';
            } else {
                passwordMatch.textContent = 'غير متطابقة ✗';
                passwordMatch.className = 'password-strength strength-weak';
            }
        });

        // اقتراح اسم مستخدم تلقائي
        const companyNameField = document.getElementById('company_name');
        if (companyNameField) {
            companyNameField.addEventListener('input', function() {
                const companyName = this.value.trim();
                if (companyName && checkElement.checked) {
                    // إنشاء اسم مستخدم مقترح
                    const username = companyName.replace(/\s+/g, '_').toLowerCase().substring(0, 20);
                    document.getElementById(usernameFieldId).placeholder = 'مقترح: ' + username;
                }
            });
        }
    }
});
</script>

</body>
</html>