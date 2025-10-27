<?php
/**
 * نظام معالجة الأخطاء الشامل - المرحلة الأولى من الإصلاحات
 */

class ErrorHandler {
    
    private static $log_file = 'logs/error_log.txt';
    private static $is_initialized = false;
    
    /**
     * تهيئة معالج الأخطاء
     */
    public static function init() {
        if (self::$is_initialized) {
            return;
        }
        
        // إنشاء مجلد السجلات إذا لم يكن موجوداً
        $log_dir = dirname(self::$log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // تعيين معالج الأخطاء
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
        
        // تعيين مستوى عرض الأخطاء
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            error_reporting(0);
            ini_set('display_errors', 0);
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
        
        self::$is_initialized = true;
    }
    
    /**
     * معالجة الأخطاء العادية
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        $error_type = self::getErrorType($errno);
        $error_message = "[$error_type] $errstr in $errfile on line $errline";
        
        self::logError($error_message);
        
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            return true; // لا تعرض الأخطاء في الإنتاج
        }
        
        // عرض الأخطاء في التطوير
        if (self::isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'System Error',
                'message' => $errstr,
                'file' => basename($errfile),
                'line' => $errline
            ]);
        } else {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 10px; border-radius: 5px;'>";
            echo "<strong>Error:</strong> $errstr<br>";
            echo "<strong>File:</strong> $errfile<br>";
            echo "<strong>Line:</strong> $errline";
            echo "</div>";
        }
        
        return true;
    }
    
    /**
     * معالجة الاستثناءات
     */
    public static function handleException($exception) {
        $error_message = "Exception: " . $exception->getMessage() . 
                        " in " . $exception->getFile() . 
                        " on line " . $exception->getLine() . 
                        "\nStack trace:\n" . $exception->getTraceAsString();
        
        self::logError($error_message);
        
        if (self::isAjaxRequest()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'error' => 'System Exception',
                'message' => $exception->getMessage(),
                'code' => 'EXCEPTION_ERROR'
            ]);
        } else {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                self::showProductionError();
            } else {
                self::showDevelopmentError($exception);
            }
        }
        
        exit();
    }
    
    /**
     * معالجة الأخطاء القاتلة
     */
    public static function handleFatalError() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $error_message = "Fatal Error: " . $error['message'] . 
                            " in " . $error['file'] . 
                            " on line " . $error['line'];
            
            self::logError($error_message);
            
            if (self::isAjaxRequest()) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'error' => 'Fatal System Error',
                    'message' => 'A critical error occurred',
                    'code' => 'FATAL_ERROR'
                ]);
            } else {
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                    self::showProductionError();
                } else {
                    self::showFatalError($error);
                }
            }
        }
    }
    
    /**
     * معالجة أخطاء قاعدة البيانات
     */
    public static function handleDatabaseError($error, $conn = null) {
        $error_message = "Database Error: " . $error;
        
        if ($conn && method_exists($conn, 'error')) {
            $error_message .= " (MySQL Error: " . $conn->error . ")";
        }
        
        self::logError($error_message);
        
        if (self::isAjaxRequest()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'error' => 'Database Error',
                'message' => 'A database error occurred',
                'code' => 'DB_ERROR'
            ]);
        } else {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                self::showProductionError();
            } else {
                echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 10px; border-radius: 5px;'>";
                echo "<strong>Database Error:</strong> $error";
                echo "</div>";
            }
        }
        
        exit();
    }
    
    /**
     * تسجيل الأخطاء في الملف
     */
    private static function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $request_uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        
        $log_entry = "[$timestamp] [$ip] [$request_uri] $message\n";
        $log_entry .= "User Agent: $user_agent\n";
        $log_entry .= "----------------------------------------\n";
        
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * عرض صفحة الخطأ للإنتاج
     */
    private static function showProductionError() {
        http_response_code(500);
        echo '<!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>خطأ في النظام</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .error-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error-icon { font-size: 60px; color: #dc3545; margin-bottom: 20px; }
                h1 { color: #333; margin-bottom: 20px; }
                p { color: #666; line-height: 1.6; }
                .back-btn { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h1>عذراً، حدث خطأ في النظام</h1>
                <p>نعتذر عن هذا الخطأ. تم تسجيل المشكلة وسيتم حلها قريباً.</p>
                <p>يرجى المحاولة مرة أخرى أو التواصل مع الدعم الفني.</p>
                <a href="javascript:history.back()" class="back-btn">العودة للصفحة السابقة</a>
            </div>
        </body>
        </html>';
    }
    
    /**
     * عرض تفاصيل الخطأ للتطوير
     */
    private static function showDevelopmentError($exception) {
        echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 15px; border-radius: 5px; font-family: monospace;">';
        echo "<h3>Exception Details:</h3>";
        echo "<strong>Message:</strong> " . $exception->getMessage() . "<br>";
        echo "<strong>File:</strong> " . $exception->getFile() . "<br>";
        echo "<strong>Line:</strong> " . $exception->getLine() . "<br>";
        echo "<strong>Code:</strong> " . $exception->getCode() . "<br>";
        echo "<h4>Stack Trace:</h4>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
        echo "</div>";
    }
    
    /**
     * عرض تفاصيل الخطأ القاتل للتطوير
     */
    private static function showFatalError($error) {
        echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 15px; border-radius: 5px; font-family: monospace;">';
        echo "<h3>Fatal Error Details:</h3>";
        echo "<strong>Type:</strong> " . self::getErrorType($error['type']) . "<br>";
        echo "<strong>Message:</strong> " . $error['message'] . "<br>";
        echo "<strong>File:</strong> " . $error['file'] . "<br>";
        echo "<strong>Line:</strong> " . $error['line'] . "<br>";
        echo "</div>";
    }
    
    /**
     * الحصول على نوع الخطأ
     */
    private static function getErrorType($errno) {
        switch ($errno) {
            case E_ERROR: return 'E_ERROR';
            case E_WARNING: return 'E_WARNING';
            case E_PARSE: return 'E_PARSE';
            case E_NOTICE: return 'E_NOTICE';
            case E_CORE_ERROR: return 'E_CORE_ERROR';
            case E_CORE_WARNING: return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
            case E_USER_ERROR: return 'E_USER_ERROR';
            case E_USER_WARNING: return 'E_USER_WARNING';
            case E_USER_NOTICE: return 'E_USER_NOTICE';
            case E_STRICT: return 'E_STRICT';
            case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: return 'E_DEPRECATED';
            case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
            default: return 'UNKNOWN';
        }
    }
    
    /**
     * التحقق من نوع الطلب
     */
    private static function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

/**
 * دالة مساعدة لمعالجة أخطاء قاعدة البيانات
 */
function handleDatabaseError($error, $conn = null) {
    ErrorHandler::handleDatabaseError($error, $conn);
}

/**
 * دالة مساعدة لمعالجة الأخطاء العامة
 */
function handleError($message, $type = 'ERROR') {
    $error_message = "[$type] $message";
    ErrorHandler::logError($error_message);
}

// تهيئة معالج الأخطاء
ErrorHandler::init();
?>



