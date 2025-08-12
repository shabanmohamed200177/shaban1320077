-- ========================================
-- تصميم قاعدة البيانات لنظام الدفع الجزئي الموحد
-- ========================================
-- 
-- تاريخ الإنشاء: 2025-01-11
-- الإصدار: 1.0.0
-- المطور: نظام الشحن الموحد
-- 
-- هذا الملف يحتوي على تصميم قاعدة البيانات
-- للنظام الجديد مع جميع الجداول والأعمدة المطلوبة
-- 

-- ========================================
-- 1. تحديث جدول parcels (الأعمدة الموحدة)
-- ========================================

-- إضافة أعمدة جديدة لجدول parcels
ALTER TABLE parcels 
ADD COLUMN IF NOT EXISTS remaining_balance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'المبلغ المتبقي للدفع',
ADD COLUMN IF NOT EXISTS last_payment_date TIMESTAMP NULL COMMENT 'تاريخ آخر دفعة',
ADD COLUMN IF NOT EXISTS payment_updated_by VARCHAR(100) COMMENT 'من قام بتحديث الدفع',
ADD COLUMN IF NOT EXISTS payment_updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'وقت آخر تحديث للدفع';

-- تحديث الأعمدة الموجودة لتوحيد القيم
ALTER TABLE parcels 
MODIFY COLUMN payment_status ENUM('unpaid','partial_paid','fully_paid') DEFAULT 'unpaid' COMMENT 'حالة الدفع الموحدة';

-- إزالة الأعمدة المكررة أو غير المستخدمة
-- ALTER TABLE parcels DROP COLUMN IF EXISTS detailed_payment_status;
-- ALTER TABLE parcels DROP COLUMN IF EXISTS amount_paid_so_far;

-- ========================================
-- 2. تحديث جدول customer_payments
-- ========================================

-- إضافة أعمدة جديدة لجدول customer_payments
ALTER TABLE customer_payments 
ADD COLUMN IF NOT EXISTS user_type ENUM('courier','admin','agent') DEFAULT 'courier' COMMENT 'نوع المستخدم',
ADD COLUMN IF NOT EXISTS user_id INT COMMENT 'معرف المستخدم',
ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','completed','failed','cancelled') DEFAULT 'completed' COMMENT 'حالة الدفع';

-- إضافة فهارس لتحسين الأداء
CREATE INDEX IF NOT EXISTS idx_customer_payments_parcel_id ON customer_payments(parcel_id);
CREATE INDEX IF NOT EXISTS idx_customer_payments_user_id ON customer_payments(user_id);
CREATE INDEX IF NOT EXISTS idx_customer_payments_payment_date ON customer_payments(payment_date);

-- ========================================
-- 3. إنشاء جدول operation_logs (جديد)
-- ========================================

CREATE TABLE IF NOT EXISTS operation_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL COMMENT 'معرف المستخدم',
    user_type ENUM('courier','admin','agent') NOT NULL COMMENT 'نوع المستخدم',
    action VARCHAR(100) NOT NULL COMMENT 'العملية المنجزة',
    parcel_id INT NULL COMMENT 'معرف الشحنة (إذا كان متعلقاً بشحنة)',
    details TEXT COMMENT 'تفاصيل العملية',
    ip_address VARCHAR(45) COMMENT 'عنوان IP',
    user_agent TEXT COMMENT 'معلومات المتصفح',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'وقت العملية',
    
    INDEX idx_operation_logs_user_id (user_id),
    INDEX idx_operation_logs_parcel_id (parcel_id),
    INDEX idx_operation_logs_action (action),
    INDEX idx_operation_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل العمليات للمراقبة والأمان';

-- ========================================
-- 4. إنشاء جدول payment_methods (جديد)
-- ========================================

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    method_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'رمز طريقة الدفع',
    method_name_ar VARCHAR(100) NOT NULL COMMENT 'اسم طريقة الدفع بالعربية',
    method_name_en VARCHAR(100) NOT NULL COMMENT 'اسم طريقة الدفع بالإنجليزية',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'هل الطريقة نشطة',
    sort_order INT DEFAULT 0 COMMENT 'ترتيب العرض',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_payment_methods_active (is_active),
    INDEX idx_payment_methods_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='طرق الدفع المتاحة';

-- إدخال طرق الدفع الأساسية
INSERT INTO payment_methods (method_code, method_name_ar, method_name_en, sort_order) VALUES
('cash', 'نقداً', 'Cash', 1),
('transfer', 'تحويل بنكي', 'Bank Transfer', 2),
('check', 'شيك', 'Check', 3),
('online', 'دفع إلكتروني', 'Online Payment', 4),
('card', 'بطاقة ائتمان', 'Credit Card', 5)
ON DUPLICATE KEY UPDATE 
    method_name_ar = VALUES(method_name_ar),
    method_name_en = VALUES(method_name_en),
    sort_order = VALUES(sort_order);

-- ========================================
-- 5. إنشاء جدول payment_settings (جديد)
-- ========================================

CREATE TABLE IF NOT EXISTS payment_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL COMMENT 'مفتاح الإعداد',
    setting_value TEXT COMMENT 'قيمة الإعداد',
    setting_type ENUM('string','number','boolean','json') DEFAULT 'string' COMMENT 'نوع القيمة',
    description TEXT COMMENT 'وصف الإعداد',
    is_system BOOLEAN DEFAULT FALSE COMMENT 'هل إعداد نظامي',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_payment_settings_key (setting_key),
    INDEX idx_payment_settings_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='إعدادات نظام الدفع';

-- إدخال الإعدادات الأساسية
INSERT INTO payment_settings (setting_key, setting_value, setting_type, description, is_system) VALUES
('max_partial_payment_amount', '10000', 'number', 'أقصى مبلغ للدفع الجزئي', TRUE),
('min_partial_payment_amount', '1', 'number', 'أقل مبلغ للدفع الجزئي', TRUE),
('allow_overpayment', 'false', 'boolean', 'السماح بدفع مبلغ أكبر من المطلوب', TRUE),
('auto_update_status', 'true', 'boolean', 'تحديث حالة الشحنة تلقائياً', TRUE),
('require_payment_note', 'false', 'boolean', 'إلزامية ملاحظة الدفع', TRUE),
('payment_reminder_days', '3', 'number', 'عدد أيام تذكير الدفع', TRUE)
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    setting_type = VALUES(setting_type),
    description = VALUES(description),
    is_system = VALUES(is_system);

-- ========================================
-- 6. إنشاء جدول payment_approvals (جديد)
-- ========================================

CREATE TABLE IF NOT EXISTS payment_approvals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL COMMENT 'معرف الدفعة',
    approver_id INT NOT NULL COMMENT 'معرف الموافق',
    approver_type ENUM('admin','manager','supervisor') NOT NULL COMMENT 'نوع الموافق',
    approval_status ENUM('pending','approved','rejected') DEFAULT 'pending' COMMENT 'حالة الموافقة',
    approval_notes TEXT COMMENT 'ملاحظات الموافقة',
    approved_at TIMESTAMP NULL COMMENT 'وقت الموافقة',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (payment_id) REFERENCES customer_payments(id) ON DELETE CASCADE,
    INDEX idx_payment_approvals_payment_id (payment_id),
    INDEX idx_payment_approvals_approver_id (approver_id),
    INDEX idx_payment_approvals_status (approval_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='موافقات الدفعات';

-- ========================================
-- 7. إنشاء جدول payment_reports (جديد)
-- ========================================

CREATE TABLE IF NOT EXISTS payment_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_type ENUM('daily','weekly','monthly','custom') NOT NULL COMMENT 'نوع التقرير',
    report_date DATE NOT NULL COMMENT 'تاريخ التقرير',
    report_data JSON COMMENT 'بيانات التقرير',
    generated_by INT NOT NULL COMMENT 'من قام بتوليد التقرير',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'وقت التوليد',
    
    INDEX idx_payment_reports_type_date (report_type, report_date),
    INDEX idx_payment_reports_generated_by (generated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تقارير الدفعات';

-- ========================================
-- 8. إنشاء Views لسهولة الاستعلامات
-- ========================================

-- View لعرض معلومات الدفع الشاملة
CREATE OR REPLACE VIEW payment_summary_view AS
SELECT 
    p.id as parcel_id,
    p.tracking_number,
    p.cod_amount,
    p.paid_amount,
    p.remaining_balance,
    p.payment_status,
    p.last_payment_date,
    p.payment_updated_by,
    p.payment_updated_at,
    c.name as courier_name,
    a.company_name as agent_name,
    p.shipment_direction,
    p.status as parcel_status,
    ps.name_ar as status_name
FROM parcels p
LEFT JOIN couriers c ON p.courier_id = c.id
LEFT JOIN agents a ON p.agent_id = a.id
LEFT JOIN parcel_status ps ON p.status = ps.id;

-- View لعرض تاريخ الدفعات
CREATE OR REPLACE VIEW payment_history_view AS
SELECT 
    cp.id as payment_id,
    cp.parcel_id,
    cp.payment_amount,
    cp.payment_date,
    cp.payment_method,
    cp.payment_note,
    cp.collected_by,
    cp.user_type,
    cp.user_id,
    cp.ip_address,
    p.tracking_number,
    p.cod_amount,
    p.remaining_balance,
    pm.method_name_ar as payment_method_name
FROM customer_payments cp
LEFT JOIN parcels p ON cp.parcel_id = p.id
LEFT JOIN payment_methods pm ON cp.payment_method = pm.method_code
WHERE cp.payment_status = 'completed'
ORDER BY cp.payment_date DESC;

-- ========================================
-- 9. إنشاء Stored Procedures
-- ========================================

DELIMITER //

-- إجراء لتحديث حالة الدفع تلقائياً
CREATE PROCEDURE UpdatePaymentStatus(IN parcel_id_param INT)
BEGIN
    DECLARE current_paid DECIMAL(12,2);
    DECLARE current_cod DECIMAL(12,2);
    DECLARE new_status VARCHAR(20);
    DECLARE new_parcel_status INT;
    
    -- جلب البيانات الحالية
    SELECT paid_amount, cod_amount INTO current_paid, current_cod
    FROM parcels WHERE id = parcel_id_param;
    
    -- حساب المتبقي
    UPDATE parcels 
    SET remaining_balance = cod_amount - paid_amount
    WHERE id = parcel_id_param;
    
    -- تحديد حالة الدفع
    IF current_paid >= current_cod THEN
        SET new_status = 'fully_paid';
        SET new_parcel_status = 5; -- تم الدفع بنجاح
    ELSEIF current_paid > 0 THEN
        SET new_status = 'partial_paid';
        SET new_parcel_status = 6; -- تم الدفع جزئي
    ELSE
        SET new_status = 'unpaid';
        SET new_parcel_status = 4; -- تم التسليم بنجاح
    END IF;
    
    -- تحديث الحالة
    UPDATE parcels 
    SET payment_status = new_status, 
        status = new_parcel_status,
        payment_updated_at = NOW()
    WHERE id = parcel_id_param;
    
    -- تسجيل العملية
    INSERT INTO operation_logs (user_id, user_type, action, parcel_id, details)
    VALUES (0, 'system', 'update_payment_status', parcel_id_param, 
            CONCAT('تم تحديث حالة الدفع إلى: ', new_status));
END //

-- إجراء لتسجيل دفعة جديدة
CREATE PROCEDURE RecordNewPayment(
    IN parcel_id_param INT,
    IN payment_amount_param DECIMAL(12,2),
    IN payment_method_param VARCHAR(20),
    IN payment_note_param TEXT,
    IN collected_by_param VARCHAR(100),
    IN user_type_param VARCHAR(20),
    IN user_id_param INT,
    IN ip_address_param VARCHAR(45)
)
BEGIN
    DECLARE current_paid DECIMAL(12,2);
    DECLARE current_cod DECIMAL(12,2);
    DECLARE payment_id INT;
    
    START TRANSACTION;
    
    -- التحقق من صحة البيانات
    IF payment_amount_param <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'مبلغ الدفعة يجب أن يكون أكبر من صفر';
    END IF;
    
    -- جلب البيانات الحالية
    SELECT paid_amount, cod_amount INTO current_paid, current_cod
    FROM parcels WHERE id = parcel_id_param;
    
    -- التحقق من عدم تجاوز المبلغ المطلوب
    IF (current_paid + payment_amount_param) > current_cod THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'مبلغ الدفعة يتجاوز المبلغ المطلوب';
    END IF;
    
    -- تسجيل الدفعة
    INSERT INTO customer_payments (
        parcel_id, payment_amount, payment_method, payment_note, 
        collected_by, user_type, user_id, ip_address
    ) VALUES (
        parcel_id_param, payment_amount_param, payment_method_param, payment_note_param,
        collected_by_param, user_type_param, user_id_param, ip_address_param
    );
    
    SET payment_id = LAST_INSERT_ID();
    
    -- تحديث الشحنة
    UPDATE parcels 
    SET paid_amount = paid_amount + payment_amount_param,
        last_payment_date = NOW(),
        payment_updated_by = collected_by_param,
        payment_updated_at = NOW()
    WHERE id = parcel_id_param;
    
    -- تحديث حالة الدفع
    CALL UpdatePaymentStatus(parcel_id_param);
    
    -- تسجيل العملية
    INSERT INTO operation_logs (user_id, user_type, action, parcel_id, details)
    VALUES (user_id_param, user_type_param, 'record_payment', parcel_id_param, 
            CONCAT('تم تسجيل دفعة: ', payment_amount_param, ' جنيه'));
    
    COMMIT;
    
    -- إرجاع معرف الدفعة
    SELECT payment_id as new_payment_id;
END //

DELIMITER ;

-- ========================================
-- 10. إنشاء Triggers
-- ========================================

DELIMITER //

-- Trigger لتحديث المتبقي عند تغيير المدفوع
CREATE TRIGGER update_remaining_balance_trigger
AFTER UPDATE ON parcels
FOR EACH ROW
BEGIN
    IF OLD.paid_amount != NEW.paid_amount THEN
        UPDATE parcels 
        SET remaining_balance = cod_amount - paid_amount
        WHERE id = NEW.id;
    END IF;
END //

-- Trigger لتسجيل العمليات عند تحديث الدفع
CREATE TRIGGER log_payment_update_trigger
AFTER UPDATE ON parcels
FOR EACH ROW
BEGIN
    IF OLD.payment_status != NEW.payment_status OR OLD.paid_amount != NEW.paid_amount THEN
        INSERT INTO operation_logs (user_id, user_type, action, parcel_id, details)
        VALUES (
            COALESCE(NEW.payment_updated_by, 'system'),
            'system',
            'payment_updated',
            NEW.id,
            CONCAT('تم تحديث الدفع: ', OLD.payment_status, ' -> ', NEW.payment_status)
        );
    END IF;
END //

DELIMITER ;

-- ========================================
-- 11. إنشاء Indexes لتحسين الأداء
-- ========================================

-- فهارس لجدول parcels
CREATE INDEX IF NOT EXISTS idx_parcels_payment_status ON parcels(payment_status);
CREATE INDEX IF NOT EXISTS idx_parcels_remaining_balance ON parcels(remaining_balance);
CREATE INDEX IF NOT EXISTS idx_parcels_last_payment_date ON parcels(last_payment_date);
CREATE INDEX IF NOT EXISTS idx_parcels_courier_id ON parcels(courier_id);
CREATE INDEX IF NOT EXISTS idx_parcels_agent_id ON parcels(agent_id);

-- فهارس لجدول customer_payments
CREATE INDEX IF NOT EXISTS idx_customer_payments_user_type ON customer_payments(user_type);
CREATE INDEX IF NOT EXISTS idx_customer_payments_collected_by ON customer_payments(collected_by);

-- فهارس لجدول operation_logs
CREATE INDEX IF NOT EXISTS idx_operation_logs_user_type ON operation_logs(user_type);
CREATE INDEX IF NOT EXISTS idx_operation_logs_action ON operation_logs(action);

-- ========================================
-- 12. إدخال بيانات تجريبية (اختياري)
-- ========================================

-- إدخال بيانات تجريبية للاختبار
-- يمكن حذف هذا القسم في الإنتاج

-- ========================================
-- نهاية تصميم قاعدة البيانات
-- ========================================
-- 
-- هذا التصميم يوفر أساساً قوياً لنظام الدفع الجزئي الموحد
-- مع جميع الجداول والأعمدة والعلاقات المطلوبة
-- 
-- تاريخ آخر تحديث: 2025-01-11
-- الإصدار: 1.0.0


