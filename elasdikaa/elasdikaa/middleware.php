<?php
/**
 * ملف Middleware للأمان - المرحلة الأولى من الإصلاحات
 * يحتوي على جميع الدوال الأمنية الأساسية
 */

class SecurityMiddleware {
    
    /**
     * التحقق من CSRF Token
     */
    public static function checkCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
                $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                if (self::isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'CSRF token invalid', 'code' => 'CSRF_ERROR']);
                } else {
                    die('CSRF token invalid - Access Denied');
                }
                exit();
            }
        }
    }
    
    /**
     * إنشاء CSRF Token جديد
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * التحقق من الجلسة
     */
    public static function checkSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // محاولة استعادة جلسة المندوب من تذكرني إن لم يكن مسجلاً
        if (!isset($_SESSION['courier_id'])) {
            self::restoreCourierSessionFromRememberCookie();
        }
        
        // التحقق من انتهاء صلاحية الجلسة (30 دقيقة)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            session_unset();
            session_destroy();
            header('Location: login.php?expired=1');
            exit();
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * التحقق من تسجيل الدخول
     */
    public static function requireLogin() {
        $isLoggedIn = (
            isset($_SESSION['login_id']) ||   // legacy admin/staff session key
            isset($_SESSION['user_id']) ||
            isset($_SESSION['agent_id']) ||
            isset($_SESSION['courier_id'])
        );

        if (!$isLoggedIn) {
            if (self::isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired', 'redirect' => 'login.php']);
                exit();
            } else {
                header('Location: login.php');
                exit();
            }
        }
    }
    
    /**
     * التحقق من نوع المستخدم
     */
    public static function requireUserType($allowed_types = []) {
        $user_type = null;
        
        if (isset($_SESSION['login_type'])) {
            $user_type = $_SESSION['login_type'];
        } elseif (isset($_SESSION['agent_id'])) {
            $user_type = 'agent';
        } elseif (isset($_SESSION['courier_id'])) {
            $user_type = 'courier';
        }
        
        if (!in_array($user_type, $allowed_types)) {
            if (self::isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Access denied', 'code' => 'ACCESS_DENIED']);
                exit();
            } else {
                die('Access Denied - Insufficient permissions');
            }
        }
        
        return $user_type;
    }
    
    /**
     * تنظيف البيانات المدخلة
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * التحقق من صحة المعرف
     */
    public static function validateId($id) {
        return filter_var($id, FILTER_VALIDATE_INT) && $id > 0;
    }
    
    /**
     * التحقق من صحة البريد الإلكتروني
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * التحقق من صحة رقم الهاتف
     */
    public static function validatePhone($phone) {
        // نمط عربي للهواتف
        return preg_match('/^(\+966|966|0)?[5][0-9]{8}$/', $phone);
    }
    
    /**
     * التحقق من نوع الطلب
     */
    private static function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * تسجيل محاولات تسجيل الدخول الفاشلة
     */
    public static function logFailedLogin($username, $ip_address) {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        
        $_SESSION['login_attempts'][] = [
            'username' => $username,
            'ip' => $ip_address,
            'timestamp' => time()
        ];
        
        // الاحتفاظ بآخر 10 محاولات فقط
        if (count($_SESSION['login_attempts']) > 10) {
            array_shift($_SESSION['login_attempts']);
        }
    }
    
    /**
     * التحقق من حظر IP
     */
    public static function isIPBlocked($ip_address) {
        if (!isset($_SESSION['login_attempts'])) {
            return false;
        }
        
        $recent_attempts = array_filter($_SESSION['login_attempts'], function($attempt) {
            return (time() - $attempt['timestamp']) < 900; // 15 دقيقة
        });
        
        $failed_attempts = count($recent_attempts);
        return $failed_attempts >= 5;
    }

    /**
     * استعادة جلسة المندوب من كوكي "تذكرني" إن كانت صالحة
     */
    private static function restoreCourierSessionFromRememberCookie() {
        if (empty($_COOKIE['courier_remember'])) {
            return;
        }
        $raw = base64_decode($_COOKIE['courier_remember'], true);
        if ($raw === false) {
            return;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['cid']) || empty($data['t'])) {
            return;
        }

        // اتصال قاعدة البيانات
        $dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'db_connect.php';
        if (!file_exists($dbPath)) {
            return;
        }
        require_once $dbPath;

        $cid = (int)$data['cid'];
        $token_hash = hash('sha256', (string)$data['t'], true);

        // التحقق من صلاحية التوكن وعدم إبطاله
        $sql = "SELECT 1 FROM courier_auth_tokens WHERE courier_id = ? AND token_hash = ? AND revoked_at IS NULL AND expires_at > NOW() LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            // تمرير كلا الوسيطين كسلاسل لتفادي تعارض الأنواع مع VARBINARY
            $cid_str = (string)$cid;
            $stmt->bind_param('ss', $cid_str, $token_hash);
            $stmt->execute();
            $res = $stmt->get_result();
            $valid = ($res && $res->num_rows === 1);
            $stmt->close();
            if (!$valid) {
                return;
            }
        } else {
            return;
        }

        // تحميل بيانات المندوب وتثبيت الجلسة
        if ($stmt2 = $conn->prepare('SELECT id, name FROM couriers WHERE id = ? AND status = 1 LIMIT 1')) {
            $stmt2->bind_param('i', $cid);
            $stmt2->execute();
            $courier = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($courier) {
                $_SESSION['courier_id'] = (int)$courier['id'];
                $_SESSION['courier_name'] = $courier['name'] ?? '';
            }
        }
    }
}

/**
 * دالة مساعدة لإنشاء CSRF input
 */
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . SecurityMiddleware::generateCSRFToken() . '">';
}

/**
 * دالة مساعدة لإنشاء CSRF meta tag
 */
function csrf_meta() {
    return '<meta name="csrf-token" content="' . SecurityMiddleware::generateCSRFToken() . '">';
}
?>
