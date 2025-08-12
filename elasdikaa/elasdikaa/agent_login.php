<?php
/**
 * ملف تسجيل دخول الوكلاء - محدث مع نظام الأمان الجديد
 */

// تضمين نظام الأمان
require_once 'middleware.php';
require_once 'error_handler.php';

// بدء الجلسة مع الحماية
SecurityMiddleware::checkSession();

// الحماية من الوصول المباشر للإدارة - تحسين لتجنب تضارب الجلسات
if (isset($_SESSION['login_id']) || isset($_SESSION['user_id'])) {
    // بدلاً من تدمير الجلسة، نمسح فقط متغيرات الإدارة
    unset($_SESSION['login_id']);
    unset($_SESSION['user_id']);
    unset($_SESSION['login_firstname']);
    unset($_SESSION['login_lastname']);
    unset($_SESSION['login_email']);
    unset($_SESSION['login_type']);
    unset($_SESSION['login_branch_id']);
}

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

$error_message = '';
$login_attempts = 0;

// الحماية من هجمات القوة الغاشمة
if (isset($_SESSION['login_attempts'])) {
    $login_attempts = $_SESSION['login_attempts'];
}

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // الحماية من CSRF
    SecurityMiddleware::checkCSRF();
    
    // التحقق من حظر IP
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (SecurityMiddleware::isIPBlocked($ip_address)) {
        $error_message = "تم حظر عنوان IP الخاص بك مؤقتاً. يرجى المحاولة لاحقاً.";
    } elseif ($login_attempts >= 5) {
        $error_message = "تم تجاوز عدد المحاولات المسموح. يرجى المحاولة لاحقاً.";
    } else {
        $username = SecurityMiddleware::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // التحقق من صحة البيانات المدخلة
        if (empty($username) || empty($password)) {
            $error_message = "يرجى إدخال اسم المستخدم وكلمة المرور.";
            $_SESSION['login_attempts'] = $login_attempts + 1;
        } elseif (strlen($username) > 50) {
            $error_message = "اسم المستخدم طويل جداً.";
            $_SESSION['login_attempts'] = $login_attempts + 1;
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $error_message = "اسم المستخدم يحتوي على أحرف غير مسموحة.";
            $_SESSION['login_attempts'] = $login_attempts + 1;
        } else {
            // البحث عن المستخدم مع حماية من SQL Injection
            $stmt = $conn->prepare("SELECT au.*, a.company_name, a.status as agent_status 
                                   FROM agent_users au 
                                   JOIN agents a ON au.agent_id = a.id 
                                   WHERE au.username = ? AND au.status = 'active' LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // التحقق من حالة الوكيل
                if ($user['agent_status'] == 0) {
                    $error_message = "حسابك غير نشط. يرجى التواصل مع الإدارة.";
                    $_SESSION['login_attempts'] = $login_attempts + 1;
                } elseif (password_verify($password, $user['password'])) {
                    // تسجيل دخول ناجح - تنظيف الجلسة أولاً
                    session_regenerate_id(true);
                    $_SESSION = array(); // تنظيف كامل للجلسة
                    
                    $_SESSION['agent_user_id'] = (int)$user['id'];
                    $_SESSION['agent_id'] = (int)$user['agent_id'];
                    $_SESSION['agent_username'] = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
                    $_SESSION['agent_company'] = htmlspecialchars($user['company_name'], ENT_QUOTES, 'UTF-8');
                    $_SESSION['agent_logged_in'] = true;
                    $_SESSION['agent_login_time'] = time();
                    
                    // تحديث وقت آخر دخول
                    $update_login = $conn->prepare("UPDATE agent_users SET last_login = NOW() WHERE id = ?");
                    $update_login->bind_param('i', $user['id']);
                    $update_login->execute();
                    
                    header('Location: agent_dashboard.php');
                    exit;
                } else {
                    $error_message = "كلمة المرور غير صحيحة.";
                    SecurityMiddleware::logFailedLogin($username, $ip_address);
                    $_SESSION['login_attempts'] = $login_attempts + 1;
                }
            } else {
                $error_message = "اسم المستخدم غير موجود أو الحساب غير نشط.";
                SecurityMiddleware::logFailedLogin($username, $ip_address);
                $_SESSION['login_attempts'] = $login_attempts + 1;
            }
        }
    }
}

// إنشاء CSRF token
$csrf_token = SecurityMiddleware::generateCSRFToken();

// إذا كان مسجل دخول مسبقاً، توجيه للوحة التحكم
if (isset($_SESSION['agent_logged_in']) && $_SESSION['agent_logged_in']) {
    header('Location: agent_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الوكلاء</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap">
    <style>
        body {
            font-family: 'Tajawal', Arial, sans-serif;
            direction: rtl;
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        
        .login-card {
            background: white;
            border-radius: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            position: relative;
        }
        
        .login-header {
            background: linear-gradient(135deg, #17a2b8, #20c997);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        
        .login-header h1 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .login-header p {
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        .login-body {
            padding: clamp(1.5rem, 4vw, 3rem);
        }
        
        .form-control {
            border-radius: 1rem;
            padding: 0.875rem 1.25rem;
            border: 2px solid #e9ecef;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            transition: all 0.3s ease;
            background-color: #fafafa;
        }
        
        .form-control:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
            background-color: white;
            transform: translateY(-1px);
        }
        
        .input-group-text {
            background: linear-gradient(135deg, #17a2b8, #20c997);
            border: none;
            color: white;
            border-radius: 1rem 0 0 1rem;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #17a2b8, #20c997);
            border: none;
            border-radius: 1rem;
            padding: 0.875rem 2rem;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            font-weight: bold;
            color: white;
            width: 100%;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #20c997, #17a2b8);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(23, 162, 184, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 0.75rem 0;
            display: flex;
            align-items: center;
            font-size: clamp(0.85rem, 1.8vw, 1rem);
            transition: transform 0.2s ease;
        }
        
        .feature-list li:hover {
            transform: translateX(5px);
        }
        
        .feature-list i {
            color: #17a2b8;
            margin-left: 0.75rem;
            width: 20px;
            font-size: 1.1em;
        }
        
        .alert {
            border-radius: 1rem;
            border: none;
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
        }
        
        .security-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        /* التجاوب مع الشاشات الصغيرة */
        @media (max-width: 768px) {
            .login-container {
                padding: 0.5rem;
            }
            
            .login-card {
                border-radius: 1.5rem;
                margin: 0.5rem;
            }
            
            .login-header {
                padding: 1.5rem 1rem;
            }
            
            .login-body {
                padding: 1.5rem 1rem;
            }
            
            .row {
                margin: 0;
            }
            
            .col-md-6 {
                padding: 0;
            }
        }
        
        @media (max-width: 576px) {
            .login-card {
                border-radius: 1rem;
                margin: 0;
            }
            
            .feature-list li {
                padding: 0.5rem 0;
                font-size: 0.9rem;
            }
            
            .form-control {
                padding: 0.75rem 1rem;
            }
            
            .btn-login {
                padding: 0.75rem 1.5rem;
                margin-top: 1rem;
            }
        }
        
        /* تحسينات إضافية للأمان البصري */
        .security-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            margin-top: 1rem;
        }
        
        .security-badge i {
            margin-left: 0.5rem;
        }
        
        /* تأثيرات بصرية للحماية */
        .protected-form {
            position: relative;
        }
        
        .protected-form::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #17a2b8, #20c997, #17a2b8);
            border-radius: 1rem;
            z-index: -1;
            opacity: 0.1;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="row g-0">
            <div class="col-md-6">
                <div class="login-header">
                    <div class="security-indicator">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <i class="fas fa-shipping-fast fa-3x mb-3"></i>
                    <h1>مرحباً بك</h1>
                    <p class="mb-0">نظام إدارة الشحنات للوكلاء</p>
                    <div class="security-badge">
                        <i class="fas fa-lock"></i>
                        اتصال آمن ومشفر
                    </div>
                </div>
                <div class="p-4">
                    <h5 class="text-center mb-3">مميزات النظام</h5>
                    <ul class="feature-list">
                        <li><i class="fas fa-chart-line"></i> عرض إحصائيات شحناتك بالتفصيل</li>
                        <li><i class="fas fa-list-alt"></i> إدارة وتحديث حالات الشحنات</li>
                        <li><i class="fas fa-money-bill-wave"></i> متابعة الحسابات المالية</li>
                        <li><i class="fas fa-download"></i> تحميل التقارير والإحصائيات</li>
                        <li><i class="fas fa-mobile-alt"></i> واجهة متجاوبة مع جميع الأجهزة</li>
                        <li><i class="fas fa-clock"></i> عمل على مدار الساعة</li>
                        <li><i class="fas fa-shield-alt"></i> حماية عالية للبيانات</li>
                        <li><i class="fas fa-sync-alt"></i> تحديثات فورية للحالات</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="login-body">
                    <h3 class="text-center mb-4">تسجيل الدخول الآمن</h3>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>تم تسجيل الخروج بنجاح
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($login_attempts > 0): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>عدد المحاولات: <?php echo $login_attempts; ?>/5
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="protected-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">اسم المستخدم</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required
                                       autocomplete="username" 
                                       pattern="[a-zA-Z0-9_.-]+"
                                       maxlength="50"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <small class="form-text text-muted">أحرف وأرقام فقط، بدون مسافات</small>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">كلمة المرور</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required
                                       autocomplete="current-password"
                                       minlength="6">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" name="login" class="btn btn-login" <?php echo ($login_attempts >= 5) ? 'disabled' : ''; ?>>
                            <i class="fas fa-sign-in-alt me-2"></i>دخول آمن
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            محمي بتقنيات الأمان المتقدمة
                        </div>
                        <p class="text-muted mt-3">
                            <i class="fas fa-question-circle me-1"></i>
                            هل تحتاج مساعدة؟ 
                            <a href="tel:+201234567890" class="text-decoration-none">اتصل بالدعم الفني</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // إظهار/إخفاء كلمة المرور
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    const usernameField = document.getElementById('username');
    const loginForm = document.querySelector('form');
    const loginBtn = document.querySelector('.btn-login');
    
    // تطبيق الأمان على كلمة المرور
    togglePassword.addEventListener('click', function(e) {
        e.preventDefault();
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });
    
    // التحقق من صحة اسم المستخدم أثناء الكتابة
    usernameField.addEventListener('input', function() {
        const value = this.value;
        const isValid = /^[a-zA-Z0-9_.-]*$/.test(value);
        
        if (!isValid && value.length > 0) {
            this.classList.add('is-invalid');
            this.setCustomValidity('يُسمح بالأحرف والأرقام والرموز (_ . -) فقط');
        } else {
            this.classList.remove('is-invalid');
            this.setCustomValidity('');
        }
    });
    
    // منع إرسال النموذج عدة مرات
    let isSubmitting = false;
    loginForm.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        
        isSubmitting = true;
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري التحقق...';
        
        // السماح بالإرسال بعد 3 ثوان في حالة عدم التوجيه
        setTimeout(function() {
            isSubmitting = false;
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>دخول آمن';
        }, 3000);
    });
    
    // تركيز تلقائي على حقل اسم المستخدم
    usernameField.focus();
    
    // حماية من console
    console.clear();
    
    // منع النقر بالزر الأيمن (اختياري)
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
    
    // منع بعض اختصارات لوحة المفاتيح
    document.addEventListener('keydown', function(e) {
        // منع F12, Ctrl+Shift+I, Ctrl+U
        if (e.key === 'F12' || 
            (e.ctrlKey && e.shiftKey && e.key === 'I') ||
            (e.ctrlKey && e.key === 'u')) {
            e.preventDefault();
        }
    });
    
    // تطبيق تأثيرات بصرية إضافية
    const securityBadges = document.querySelectorAll('.security-badge');
    securityBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // مؤشر الأمان
    const securityIndicator = document.querySelector('.security-indicator');
    if (securityIndicator) {
        setInterval(function() {
            securityIndicator.style.animation = 'pulse 2s infinite';
        }, 1000);
    }
});

// حماية إضافية من التلاعب
Object.defineProperty(window, 'console', {
    value: {},
    writable: false,
    configurable: false
});
</script>

<style>
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.is-invalid {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

/* تحسينات إضافية للاستجابة */
@media (max-width: 480px) {
    .login-header h1 {
        font-size: 1.5rem;
    }
    
    .login-header i.fa-3x {
        font-size: 2rem;
    }
    
    .feature-list li {
        font-size: 0.85rem;
    }
}

@media (orientation: landscape) and (max-height: 600px) {
    .login-container {
        align-items: flex-start;
        padding-top: 1rem;
    }
    
    .login-header {
        padding: 1rem;
    }
    
    .login-body {
        padding: 1rem;
    }
}
</style>

</body>
</html>
