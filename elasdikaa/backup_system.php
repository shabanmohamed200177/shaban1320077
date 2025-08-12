<?php
/**
 * نظام النسخ الاحتياطي الشامل - شركة الأصدقاء
 * Complete Backup System - Alasdeqaa Company
 */

session_start();
include 'db_connect.php';

// التحقق من صحة الجلسة والصلاحيات
if (!isset($_SESSION['login_id']) || $_SESSION['login_type'] != 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'غير مسموح بالوصول - المدير فقط']);
    exit;
}

class BackupSystem {
    private $conn;
    private $backup_dir;
    private $max_backups = 10; // الحد الأقصى للنسخ الاحتياطية
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->backup_dir = __DIR__ . '/backups';
        $this->createBackupDirectory();
        $this->createBackupTable();
    }
    
    /**
     * إنشاء مجلد النسخ الاحتياطية
     */
    private function createBackupDirectory() {
        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
        
        // إنشاء ملف .htaccess لحماية المجلد
        $htaccess_file = $this->backup_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
        
        // إنشاء ملف index.php لحماية إضافية
        $index_file = $this->backup_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\nheader('HTTP/1.0 403 Forbidden');\nexit('Access denied');\n?>");
        }
    }
    
    /**
     * إنشاء جدول النسخ الاحتياطية
     */
    private function createBackupTable() {
        $sql = "CREATE TABLE IF NOT EXISTS backups (
            id INT(11) NOT NULL AUTO_INCREMENT,
            backup_name VARCHAR(255) NOT NULL,
            backup_type ENUM('manual', 'automatic', 'scheduled') DEFAULT 'manual',
            file_path VARCHAR(500) NOT NULL,
            file_size BIGINT DEFAULT 0,
            database_name VARCHAR(100) DEFAULT NULL,
            tables_count INT DEFAULT 0,
            records_count INT DEFAULT 0,
            created_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('success', 'failed', 'in_progress') DEFAULT 'in_progress',
            error_message TEXT DEFAULT NULL,
            compression ENUM('none', 'gzip', 'zip') DEFAULT 'gzip',
            backup_hash VARCHAR(64) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_created_at (created_at),
            KEY idx_status (status),
            KEY idx_backup_type (backup_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->conn->query($sql);
    }
    
    /**
     * إنشاء نسخة احتياطية شاملة
     */
    public function createFullBackup($type = 'manual', $description = '') {
        try {
            $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '_' . uniqid();
            $backup_file = $this->backup_dir . '/' . $backup_name . '.sql.gz';
            
            // تسجيل بداية النسخة الاحتياطية
            $backup_id = $this->logBackupStart($backup_name, $type, $backup_file);
            
            // الحصول على معلومات قاعدة البيانات
            $db_info = $this->getDatabaseInfo();
            
            // إنشاء النسخة الاحتياطية
            $sql_content = $this->generateSQLDump();
            
            // ضغط وحفظ الملف
            $compressed_content = gzencode($sql_content, 9);
            
            if (file_put_contents($backup_file, $compressed_content) === false) {
                throw new Exception('فشل في كتابة ملف النسخة الاحتياطية');
            }
            
            // حساب hash للتحقق من سلامة الملف
            $file_hash = hash_file('sha256', $backup_file);
            $file_size = filesize($backup_file);
            
            // تحديث سجل النسخة الاحتياطية
            $metadata = [
                'description' => $description,
                'php_version' => PHP_VERSION,
                'mysql_version' => $this->conn->server_info,
                'system_info' => php_uname(),
                'backup_duration' => 0
            ];
            
            $this->updateBackupRecord($backup_id, 'success', $file_size, $file_hash, $db_info, $metadata);
            
            // تنظيف النسخ القديمة
            $this->cleanupOldBackups();
            
            // إضافة إشعار
            $this->addBackupNotification('success', $backup_name, $file_size);
            
            return [
                'status' => 'success',
                'message' => 'تم إنشاء النسخة الاحتياطية بنجاح',
                'backup_id' => $backup_id,
                'backup_name' => $backup_name,
                'file_size' => $file_size,
                'file_path' => $backup_file
            ];
            
        } catch (Exception $e) {
            // تحديث السجل بالخطأ
            if (isset($backup_id)) {
                $this->updateBackupRecord($backup_id, 'failed', 0, null, [], [], $e->getMessage());
            }
            
            // حذف الملف المعطل إن وجد
            if (isset($backup_file) && file_exists($backup_file)) {
                unlink($backup_file);
            }
            
            // إضافة إشعار خطأ
            $this->addBackupNotification('error', 'فشل النسخة الاحتياطية', 0, $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => 'فشل في إنشاء النسخة الاحتياطية: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * استعادة نسخة احتياطية
     */
    public function restoreBackup($backup_id) {
        try {
            // الحصول على معلومات النسخة الاحتياطية
            $backup_info = $this->getBackupInfo($backup_id);
            
            if (!$backup_info) {
                throw new Exception('النسخة الاحتياطية غير موجودة');
            }
            
            if (!file_exists($backup_info['file_path'])) {
                throw new Exception('ملف النسخة الاحتياطية غير موجود');
            }
            
            // التحقق من سلامة الملف
            $current_hash = hash_file('sha256', $backup_info['file_path']);
            if ($current_hash !== $backup_info['backup_hash']) {
                throw new Exception('ملف النسخة الاحتياطية تالف');
            }
            
            // قراءة وفك ضغط الملف
            $compressed_content = file_get_contents($backup_info['file_path']);
            $sql_content = gzdecode($compressed_content);
            
            if ($sql_content === false) {
                throw new Exception('فشل في فك ضغط النسخة الاحتياطية');
            }
            
            // إنشاء نسخة احتياطية سريعة قبل الاستعادة
            $this->createFullBackup('automatic', 'نسخة احتياطية قبل الاستعادة');
            
            // تعطيل فحص المفاتيح الأجنبية مؤقتاً
            $this->conn->query("SET foreign_key_checks = 0");
            
            // تقسيم وتنفيذ الاستعلامات
            $queries = explode(";\n", $sql_content);
            $executed_queries = 0;
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) && $query !== ';') {
                    if (!$this->conn->query($query)) {
                        throw new Exception('فشل في تنفيذ الاستعلام: ' . $this->conn->error);
                    }
                    $executed_queries++;
                }
            }
            
            // إعادة تفعيل فحص المفاتيح الأجنبية
            $this->conn->query("SET foreign_key_checks = 1");
            
            // إضافة إشعار نجاح
            $this->addBackupNotification('success', 'تم استعادة النسخة الاحتياطية', 0, "تم تنفيذ {$executed_queries} استعلام");
            
            return [
                'status' => 'success',
                'message' => 'تم استعادة النسخة الاحتياطية بنجاح',
                'executed_queries' => $executed_queries
            ];
            
        } catch (Exception $e) {
            // إعادة تفعيل فحص المفاتيح الأجنبية في حالة الخطأ
            $this->conn->query("SET foreign_key_checks = 1");
            
            // إضافة إشعار خطأ
            $this->addBackupNotification('error', 'فشل في استعادة النسخة الاحتياطية', 0, $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => 'فشل في استعادة النسخة الاحتياطية: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * جدولة النسخ الاحتياطية التلقائية
     */
    public function scheduleAutomaticBackups() {
        try {
            // التحقق من آخر نسخة احتياطية تلقائية
            $sql = "SELECT created_at FROM backups 
                    WHERE backup_type = 'automatic' 
                    ORDER BY created_at DESC 
                    LIMIT 1";
            
            $result = $this->conn->query($sql);
            $last_backup = $result->fetch_assoc();
            
            $should_backup = false;
            
            if (!$last_backup) {
                $should_backup = true;
            } else {
                $last_backup_time = strtotime($last_backup['created_at']);
                $current_time = time();
                $hours_passed = ($current_time - $last_backup_time) / 3600;
                
                // إنشاء نسخة احتياطية كل 12 ساعة
                if ($hours_passed >= 12) {
                    $should_backup = true;
                }
            }
            
            if ($should_backup) {
                return $this->createFullBackup('automatic', 'نسخة احتياطية تلقائية مجدولة');
            } else {
                return [
                    'status' => 'info',
                    'message' => 'لا حاجة لنسخة احتياطية الآن'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'فشل في جدولة النسخ الاحتياطية: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * الحصول على قائمة النسخ الاحتياطية
     */
    public function getBackupsList($limit = 20, $offset = 0) {
        try {
            $sql = "SELECT * FROM backups 
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $backups = [];
            
            while ($row = $result->fetch_assoc()) {
                // تنسيق حجم الملف
                $row['file_size_formatted'] = $this->formatFileSize($row['file_size']);
                
                // تنسيق التاريخ
                $row['created_at_formatted'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
                
                // فك تشفير metadata
                if ($row['metadata']) {
                    $row['metadata'] = json_decode($row['metadata'], true);
                }
                
                // التحقق من وجود الملف
                $row['file_exists'] = file_exists($row['file_path']);
                
                $backups[] = $row;
            }
            
            // عدد النسخ الإجمالي
            $count_sql = "SELECT COUNT(*) as total FROM backups";
            $count_result = $this->conn->query($count_sql);
            $total = $count_result->fetch_assoc()['total'];
            
            return [
                'status' => 'success',
                'backups' => $backups,
                'total' => $total
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * حذف نسخة احتياطية
     */
    public function deleteBackup($backup_id) {
        try {
            $backup_info = $this->getBackupInfo($backup_id);
            
            if (!$backup_info) {
                throw new Exception('النسخة الاحتياطية غير موجودة');
            }
            
            // حذف الملف
            if (file_exists($backup_info['file_path'])) {
                if (!unlink($backup_info['file_path'])) {
                    throw new Exception('فشل في حذف ملف النسخة الاحتياطية');
                }
            }
            
            // حذف السجل من قاعدة البيانات
            $sql = "DELETE FROM backups WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $backup_id);
            
            if (!$stmt->execute()) {
                throw new Exception('فشل في حذف سجل النسخة الاحتياطية');
            }
            
            return [
                'status' => 'success',
                'message' => 'تم حذف النسخة الاحتياطية بنجاح'
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * تنزيل نسخة احتياطية
     */
    public function downloadBackup($backup_id) {
        try {
            $backup_info = $this->getBackupInfo($backup_id);
            
            if (!$backup_info) {
                throw new Exception('النسخة الاحتياطية غير موجودة');
            }
            
            if (!file_exists($backup_info['file_path'])) {
                throw new Exception('ملف النسخة الاحتياطية غير موجود');
            }
            
            // إعداد headers للتنزيل
            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename="' . $backup_info['backup_name'] . '.sql.gz"');
            header('Content-Length: ' . filesize($backup_info['file_path']));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            
            // إرسال الملف
            readfile($backup_info['file_path']);
            exit;
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * إحصائيات النسخ الاحتياطية
     */
    public function getBackupStats() {
        try {
            $stats = [];
            
            // إجمالي النسخ
            $sql = "SELECT COUNT(*) as total FROM backups";
            $result = $this->conn->query($sql);
            $stats['total_backups'] = $result->fetch_assoc()['total'];
            
            // النسخ الناجحة
            $sql = "SELECT COUNT(*) as successful FROM backups WHERE status = 'success'";
            $result = $this->conn->query($sql);
            $stats['successful_backups'] = $result->fetch_assoc()['successful'];
            
            // النسخ الفاشلة
            $sql = "SELECT COUNT(*) as failed FROM backups WHERE status = 'failed'";
            $result = $this->conn->query($sql);
            $stats['failed_backups'] = $result->fetch_assoc()['failed'];
            
            // إجمالي حجم النسخ
            $sql = "SELECT SUM(file_size) as total_size FROM backups WHERE status = 'success'";
            $result = $this->conn->query($sql);
            $total_size = $result->fetch_assoc()['total_size'] ?? 0;
            $stats['total_size'] = $total_size;
            $stats['total_size_formatted'] = $this->formatFileSize($total_size);
            
            // آخر نسخة احتياطية
            $sql = "SELECT * FROM backups WHERE status = 'success' ORDER BY created_at DESC LIMIT 1";
            $result = $this->conn->query($sql);
            $stats['last_backup'] = $result->fetch_assoc();
            
            // متوسط حجم النسخة
            if ($stats['successful_backups'] > 0) {
                $avg_size = $total_size / $stats['successful_backups'];
                $stats['average_size'] = $avg_size;
                $stats['average_size_formatted'] = $this->formatFileSize($avg_size);
            } else {
                $stats['average_size'] = 0;
                $stats['average_size_formatted'] = '0 B';
            }
            
            // مساحة القرص المتاحة
            $stats['disk_free_space'] = disk_free_space($this->backup_dir);
            $stats['disk_free_space_formatted'] = $this->formatFileSize($stats['disk_free_space']);
            
            return [
                'status' => 'success',
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    // ========== الوظائف المساعدة الخاصة ==========
    
    private function logBackupStart($backup_name, $type, $file_path) {
        $sql = "INSERT INTO backups (backup_name, backup_type, file_path, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssi", $backup_name, $type, $file_path, $_SESSION['login_id']);
        $stmt->execute();
        
        return $this->conn->insert_id;
    }
    
    private function updateBackupRecord($backup_id, $status, $file_size, $file_hash, $db_info, $metadata, $error_message = null) {
        $sql = "UPDATE backups SET 
                status = ?, 
                file_size = ?, 
                backup_hash = ?, 
                database_name = ?, 
                tables_count = ?, 
                records_count = ?, 
                metadata = ?, 
                error_message = ? 
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $metadata_json = json_encode($metadata);
        
        $stmt->bind_param("sissiissi", 
            $status, $file_size, $file_hash, 
            $db_info['database_name'] ?? null, 
            $db_info['tables_count'] ?? 0, 
            $db_info['records_count'] ?? 0, 
            $metadata_json, $error_message, $backup_id
        );
        
        $stmt->execute();
    }
    
    private function getDatabaseInfo() {
        $info = [];
        
        // اسم قاعدة البيانات
        $result = $this->conn->query("SELECT DATABASE() as db_name");
        $info['database_name'] = $result->fetch_assoc()['db_name'];
        
        // عدد الجداول
        $result = $this->conn->query("SHOW TABLES");
        $info['tables_count'] = $result->num_rows;
        
        // عدد السجلات الإجمالي
        $total_records = 0;
        while ($table = $result->fetch_array()) {
            $table_name = $table[0];
            $count_result = $this->conn->query("SELECT COUNT(*) as count FROM `$table_name`");
            if ($count_result) {
                $total_records += $count_result->fetch_assoc()['count'];
            }
        }
        $info['records_count'] = $total_records;
        
        return $info;
    }
    
    private function generateSQLDump() {
        $dump = "-- نسخة احتياطية من قاعدة البيانات - شركة الأصدقاء\n";
        $dump .= "-- تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- MySQL Version: " . $this->conn->server_info . "\n";
        $dump .= "-- PHP Version: " . PHP_VERSION . "\n\n";
        
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $dump .= "SET AUTOCOMMIT = 0;\n";
        $dump .= "START TRANSACTION;\n\n";
        
        // الحصول على جميع الجداول
        $tables_result = $this->conn->query("SHOW TABLES");
        
        while ($table = $tables_result->fetch_array()) {
            $table_name = $table[0];
            
            // هيكل الجدول
            $create_result = $this->conn->query("SHOW CREATE TABLE `$table_name`");
            $create_row = $create_result->fetch_assoc();
            
            $dump .= "-- بنية الجدول `$table_name`\n";
            $dump .= "DROP TABLE IF EXISTS `$table_name`;\n";
            $dump .= $create_row['Create Table'] . ";\n\n";
            
            // بيانات الجدول
            $data_result = $this->conn->query("SELECT * FROM `$table_name`");
            
            if ($data_result->num_rows > 0) {
                $dump .= "-- بيانات الجدول `$table_name`\n";
                
                // الحصول على أسماء الأعمدة
                $fields = [];
                while ($field = $data_result->fetch_field()) {
                    $fields[] = "`{$field->name}`";
                }
                
                $dump .= "INSERT INTO `$table_name` (" . implode(", ", $fields) . ") VALUES\n";
                
                $rows = [];
                while ($row = $data_result->fetch_assoc()) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $this->conn->real_escape_string($value) . "'";
                        }
                    }
                    $rows[] = "(" . implode(", ", $values) . ")";
                }
                
                $dump .= implode(",\n", $rows) . ";\n\n";
            }
        }
        
        $dump .= "COMMIT;\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        return $dump;
    }
    
    private function getBackupInfo($backup_id) {
        $sql = "SELECT * FROM backups WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $backup_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    private function cleanupOldBackups() {
        // الاحتفاظ بآخر 10 نسخ فقط
        $sql = "SELECT id FROM backups WHERE status = 'success' ORDER BY created_at DESC LIMIT {$this->max_backups}, 999999";
        $result = $this->conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            $this->deleteBackup($row['id']);
        }
    }
    
    private function formatFileSize($size) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit_index = 0;
        
        while ($size >= 1024 && $unit_index < count($units) - 1) {
            $size /= 1024;
            $unit_index++;
        }
        
        return round($size, 2) . ' ' . $units[$unit_index];
    }
    
    private function addBackupNotification($type, $title, $file_size, $message = '') {
        // إضافة إشعار عن حالة النسخة الاحتياطية
        include_once 'notifications_system.php';
        
        if (class_exists('NotificationSystem')) {
            $notificationSystem = new NotificationSystem($this->conn);
            
            $notification_data = [
                'user_id' => null,
                'is_global' => 1,
                'title' => $title,
                'message' => $message ?: "حجم الملف: " . $this->formatFileSize($file_size),
                'type' => $type === 'success' ? 'success' : ($type === 'error' ? 'danger' : 'info'),
                'icon' => 'fas fa-database',
                'category' => 'backup',
                'priority' => $type === 'error' ? 'high' : 'medium'
            ];
            
            $notificationSystem->addNotification($notification_data);
        }
    }
}

// إنشاء كائن النظام
$backupSystem = new BackupSystem($conn);

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_backup':
            $type = $_POST['type'] ?? 'manual';
            $description = $_POST['description'] ?? '';
            $response = $backupSystem->createFullBackup($type, $description);
            break;
            
        case 'restore_backup':
            $backup_id = (int)$_POST['backup_id'];
            $response = $backupSystem->restoreBackup($backup_id);
            break;
            
        case 'delete_backup':
            $backup_id = (int)$_POST['backup_id'];
            $response = $backupSystem->deleteBackup($backup_id);
            break;
            
        case 'get_backups_list':
            $limit = (int)($_POST['limit'] ?? 20);
            $offset = (int)($_POST['offset'] ?? 0);
            $response = $backupSystem->getBackupsList($limit, $offset);
            break;
            
        case 'get_backup_stats':
            $response = $backupSystem->getBackupStats();
            break;
            
        case 'schedule_automatic':
            $response = $backupSystem->scheduleAutomaticBackups();
            break;
            
        default:
            $response = [
                'status' => 'error',
                'message' => 'عملية غير صحيحة'
            ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// تنزيل نسخة احتياطية
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    $backup_id = (int)$_GET['download'];
    $backupSystem->downloadBackup($backup_id);
    exit;
}

// عرض واجهة إدارة النسخ الاحتياطية
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include 'header.php';
    ?>
    
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">النسخ الاحتياطية</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="./">الرئيسية</a></li>
                            <li class="breadcrumb-item active">النسخ الاحتياطية</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                
                <!-- إحصائيات النسخ الاحتياطية -->
                <div class="row mb-3">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3 id="total-backups">-</h3>
                                <p>إجمالي النسخ</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-database"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3 id="successful-backups">-</h3>
                                <p>النسخ الناجحة</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3 id="total-size">-</h3>
                                <p>إجمالي الحجم</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-hdd"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-secondary">
                            <div class="inner">
                                <h3 id="disk-space">-</h3>
                                <p>المساحة المتاحة</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-server"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- أزرار التحكم -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">إدارة النسخ الاحتياطية</h3>
                            </div>
                            <div class="card-body">
                                <button type="button" class="btn btn-primary" onclick="createBackup('manual')">
                                    <i class="fas fa-plus"></i> إنشاء نسخة احتياطية يدوية
                                </button>
                                <button type="button" class="btn btn-info" onclick="scheduleAutomatic()">
                                    <i class="fas fa-clock"></i> تشغيل النسخ التلقائية
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="loadBackupsList()">
                                    <i class="fas fa-sync"></i> تحديث القائمة
                                </button>
                                <button type="button" class="btn btn-warning" onclick="loadBackupStats()">
                                    <i class="fas fa-chart-bar"></i> تحديث الإحصائيات
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- قائمة النسخ الاحتياطية -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">قائمة النسخ الاحتياطية</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="backups-table">
                                        <thead>
                                            <tr>
                                                <th>الرقم</th>
                                                <th>اسم النسخة</th>
                                                <th>النوع</th>
                                                <th>الحجم</th>
                                                <th>الحالة</th>
                                                <th>تاريخ الإنشاء</th>
                                                <th>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="backups-tbody">
                                            <tr>
                                                <td colspan="7" class="text-center">جاري التحميل...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </section>
    </div>
    
    <script>
    $(document).ready(function() {
        loadBackupStats();
        loadBackupsList();
        
        // تحديث تلقائي كل 5 دقائق
        setInterval(function() {
            loadBackupStats();
            loadBackupsList();
        }, 300000);
    });
    
    function loadBackupStats() {
        $.post('backup_system.php', {
            action: 'get_backup_stats'
        }, function(response) {
            if (response.status === 'success') {
                const stats = response.stats;
                $('#total-backups').text(stats.total_backups);
                $('#successful-backups').text(stats.successful_backups);
                $('#total-size').text(stats.total_size_formatted);
                $('#disk-space').text(stats.disk_free_space_formatted);
            }
        }, 'json');
    }
    
    function loadBackupsList() {
        $.post('backup_system.php', {
            action: 'get_backups_list',
            limit: 50
        }, function(response) {
            if (response.status === 'success') {
                displayBackupsList(response.backups);
            }
        }, 'json');
    }
    
    function displayBackupsList(backups) {
        let html = '';
        
        if (backups.length === 0) {
            html = '<tr><td colspan="7" class="text-center">لا توجد نسخ احتياطية</td></tr>';
        } else {
            backups.forEach(function(backup, index) {
                const statusClass = backup.status === 'success' ? 'success' : 
                                  backup.status === 'failed' ? 'danger' : 'warning';
                const statusText = backup.status === 'success' ? 'ناجحة' : 
                                 backup.status === 'failed' ? 'فاشلة' : 'قيد التنفيذ';
                
                const typeText = backup.backup_type === 'manual' ? 'يدوية' : 
                               backup.backup_type === 'automatic' ? 'تلقائية' : 'مجدولة';
                
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${backup.backup_name}</td>
                        <td><span class="badge badge-info">${typeText}</span></td>
                        <td>${backup.file_size_formatted}</td>
                        <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                        <td>${backup.created_at_formatted}</td>
                        <td>
                            ${backup.status === 'success' ? `
                                <button class="btn btn-sm btn-success" onclick="downloadBackup(${backup.id})" title="تنزيل">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="restoreBackup(${backup.id})" title="استعادة">
                                    <i class="fas fa-undo"></i>
                                </button>
                            ` : ''}
                            <button class="btn btn-sm btn-danger" onclick="deleteBackup(${backup.id})" title="حذف">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        
        $('#backups-tbody').html(html);
    }
    
    function createBackup(type) {
        const description = prompt('وصف النسخة الاحتياطية (اختياري):');
        
        if (description !== null) {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإنشاء...';
            btn.disabled = true;
            
            $.post('backup_system.php', {
                action: 'create_backup',
                type: type,
                description: description || ''
            }, function(response) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (response.status === 'success') {
                    alert_toast('تم إنشاء النسخة الاحتياطية بنجاح', 'success');
                    loadBackupStats();
                    loadBackupsList();
                } else {
                    alert_toast('فشل في إنشاء النسخة الاحتياطية: ' + response.message, 'error');
                }
            }, 'json');
        }
    }
    
    function restoreBackup(backupId) {
        if (confirm('هل أنت متأكد من استعادة هذه النسخة الاحتياطية؟ سيتم إنشاء نسخة احتياطية سريعة أولاً.')) {
            $.post('backup_system.php', {
                action: 'restore_backup',
                backup_id: backupId
            }, function(response) {
                if (response.status === 'success') {
                    alert_toast('تم استعادة النسخة الاحتياطية بنجاح', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alert_toast('فشل في استعادة النسخة الاحتياطية: ' + response.message, 'error');
                }
            }, 'json');
        }
    }
    
    function deleteBackup(backupId) {
        if (confirm('هل أنت متأكد من حذف هذه النسخة الاحتياطية؟')) {
            $.post('backup_system.php', {
                action: 'delete_backup',
                backup_id: backupId
            }, function(response) {
                if (response.status === 'success') {
                    alert_toast('تم حذف النسخة الاحتياطية بنجاح', 'success');
                    loadBackupStats();
                    loadBackupsList();
                } else {
                    alert_toast('فشل في حذف النسخة الاحتياطية: ' + response.message, 'error');
                }
            }, 'json');
        }
    }
    
    function downloadBackup(backupId) {
        window.open('backup_system.php?download=' + backupId, '_blank');
    }
    
    function scheduleAutomatic() {
        $.post('backup_system.php', {
            action: 'schedule_automatic'
        }, function(response) {
            if (response.status === 'success') {
                alert_toast('تم إنشاء النسخة الاحتياطية التلقائية بنجاح', 'success');
                loadBackupStats();
                loadBackupsList();
            } else if (response.status === 'info') {
                alert_toast(response.message, 'info');
            } else {
                alert_toast('فشل في النسخ التلقائية: ' + response.message, 'error');
            }
        }, 'json');
    }
    </script>
    
    <?php
    include 'footer.php';
}
?>