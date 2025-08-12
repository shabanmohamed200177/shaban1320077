<?php
/**
 * نظام إشعارات WhatsApp المجاني
 * يستخدم روابط wa.me لفتح WhatsApp مع الرسالة جاهزة
 */

class FreeWhatsAppNotifications {
    private $conn;
    private $company_phone;
    private $company_name;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->company_phone = "+201234567890"; // رقم واتساب الشركة
        $this->company_name = "شركة الشحن"; // اسم الشركة
    }
    
    /**
     * إرسال إشعار مجاني عند تغيير الحالة
     */
    public function sendFreeNotification($parcel_id, $new_status_id) {
        try {
            // جلب بيانات الشحنة
            $parcel_data = $this->getParcelData($parcel_id);
            if (!$parcel_data) {
                return false;
            }
            
            // جلب قالب الرسالة للحالة الجديدة
            $message_template = $this->getMessageTemplate($new_status_id);
            if (!$message_template) {
                return false;
            }
            
            $results = [];
            
            // إنشاء روابط WhatsApp للراسل والمستلم
            if ($message_template['send_to_sender']) {
                $sender_message = $this->buildMessage($message_template['sender_template'], $parcel_data);
                $sender_link = $this->createWhatsAppLink($parcel_data['sender_phone'], $sender_message);
                $results['sender'] = [
                    'phone' => $parcel_data['sender_phone'],
                    'message' => $sender_message,
                    'whatsapp_link' => $sender_link,
                    'type' => 'sender'
                ];
                
                // حفظ في قاعدة البيانات للمراجعة اللاحقة
                $this->logFreeNotification($parcel_id, $parcel_data['sender_phone'], 'sender', $new_status_id, $sender_message, $sender_link);
            }
            
            if ($message_template['send_to_recipient']) {
                $recipient_message = $this->buildMessage($message_template['recipient_template'], $parcel_data);
                $recipient_link = $this->createWhatsAppLink($parcel_data['recipient_phone'], $recipient_message);
                $results['recipient'] = [
                    'phone' => $parcel_data['recipient_phone'],
                    'message' => $recipient_message,
                    'whatsapp_link' => $recipient_link,
                    'type' => 'recipient'
                ];
                
                $this->logFreeNotification($parcel_id, $parcel_data['recipient_phone'], 'recipient', $new_status_id, $recipient_message, $recipient_link);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Free WhatsApp Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * إنشاء رابط WhatsApp مباشر
     */
    private function createWhatsAppLink($phone, $message) {
        $clean_phone = $this->cleanPhoneNumber($phone);
        $encoded_message = urlencode($message);
        return "https://wa.me/{$clean_phone}?text={$encoded_message}";
    }
    
    /**
     * جلب بيانات الشحنة
     */
    private function getParcelData($parcel_id) {
        $query = "
            SELECT 
                p.*,
                c.name as courier_name,
                c.phone as courier_phone,
                ps.name_ar as status_name,
                psr.reason_text as status_reason,
                g_rec.name as recipient_gov_name,
                ar_rec.name as recipient_area_name
            FROM parcels p
            LEFT JOIN couriers c ON p.courier_id = c.id
            LEFT JOIN parcel_status ps ON p.status = ps.id
            LEFT JOIN parcel_status_reasons psr ON p.status_reason_id = psr.id
            LEFT JOIN governorates g_rec ON p.recipient_governorate_id = g_rec.id
            LEFT JOIN areas ar_rec ON p.recipient_area_id = ar_rec.id
            WHERE p.id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * جلب قوالب الرسائل المجانية
     */
    private function getMessageTemplate($status_id) {
        $templates = [
            // قيد التنفيذ
            1 => [
                'send_to_sender' => true,
                'send_to_recipient' => false,
                'sender_template' => '🚚 تم استلام طلب شحنتك

عزيزي/عزيزتي {sender_name}

تم استلام طلب الشحن بنجاح ✅
📦 رقم الشحنة: {tracking_number}
📍 المستلم: {recipient_name}
💰 المبلغ: {cod_amount} جنيه

سيتم التواصل معك قريباً

{company_name}',
                'recipient_template' => ''
            ],
            
            // تم تسليمها للمندوب
            2 => [
                'send_to_sender' => true,
                'send_to_recipient' => true,
                'sender_template' => '📦 شحنتك مع المندوب

عزيزي/عزيزتي {sender_name}

تم تسليم شحنتك للمندوب ✅
📦 رقم الشحنة: {tracking_number}
👤 المندوب: {courier_name}
📱 هاتف المندوب: {courier_phone}

{company_name}',
                'recipient_template' => '📦 شحنة قادمة إليك

عزيزي/عزيزتي {recipient_name}

لديك شحنة قادمة من {sender_name}
📦 رقم التتبع: {tracking_number}
👤 المندوب: {courier_name}
📱 هاتف المندوب: {courier_phone}
💰 المبلغ المطلوب: {cod_amount} جنيه

{company_name}'
            ],
            
            // جاري التوصيل
            3 => [
                'send_to_sender' => false,
                'send_to_recipient' => true,
                'sender_template' => '',
                'recipient_template' => '🚛 المندوب في طريقه إليك

عزيزي/عزيزتي {recipient_name}

المندوب الآن قادم لتوصيل شحنتك
📦 رقم الشحنة: {tracking_number}
👤 المندوب: {courier_name}
📱 هاتف المندوب: {courier_phone}
💰 المبلغ المطلوب: {cod_amount} جنيه

يرجى التأكد من تواجدك

{company_name}'
            ],
            
            // تم التسليم بنجاح
            4 => [
                'send_to_sender' => true,
                'send_to_recipient' => true,
                'sender_template' => '✅ تم تسليم شحنتك بنجاح

عزيزي/عزيزتي {sender_name}

تم تسليم شحنتك بنجاح! 🎉
📦 رقم الشحنة: {tracking_number}
📍 المستلم: {recipient_name}

شكراً لاختيارك خدماتنا 🙏

{company_name}',
                'recipient_template' => '✅ شكراً لاستلام الشحنة

عزيزي/عزيزتي {recipient_name}

شكراً لاستلام الشحنة من {sender_name}
📦 رقم الشحنة: {tracking_number}

نتطلع لخدمتك مرة أخرى 😊

{company_name}'
            ],
            
            // تم الرفض
            8 => [
                'send_to_sender' => true,
                'send_to_recipient' => false,
                'sender_template' => '❌ تم رفض استلام الشحنة

عزيزي/عزيزتي {sender_name}

تم رفض استلام شحنتك
📦 رقم الشحنة: {tracking_number}
❗ السبب: {status_reason}
📝 ملاحظة: {status_reason_note}

الشحنة في طريق العودة
سيتم التواصل معك

{company_name}',
                'recipient_template' => ''
            ]
        ];
        
        return $templates[$status_id] ?? null;
    }
    
    /**
     * بناء الرسالة بالمتغيرات
     */
    private function buildMessage($template, $parcel_data) {
        $placeholders = [
            '{sender_name}' => $parcel_data['sender_name'] ?? '',
            '{recipient_name}' => $parcel_data['recipient_name'] ?? '',
            '{tracking_number}' => $parcel_data['tracking_number'] ?? '',
            '{cod_amount}' => number_format($parcel_data['cod_amount'] ?? 0, 2),
            '{courier_name}' => $parcel_data['courier_name'] ?? 'لم يحدد بعد',
            '{courier_phone}' => $parcel_data['courier_phone'] ?? '',
            '{status_name}' => $parcel_data['status_name'] ?? '',
            '{status_reason}' => $parcel_data['status_reason'] ?? '',
            '{status_reason_note}' => $parcel_data['status_reason_note'] ?? '',
            '{company_name}' => $this->company_name,
            '{company_phone}' => $this->company_phone
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * تنظيف رقم الهاتف
     */
    private function cleanPhoneNumber($phone) {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($clean, 0, 1) === '0') {
            $clean = '2' . $clean;
        } elseif (substr($clean, 0, 2) !== '20') {
            $clean = '20' . $clean;
        }
        
        return $clean;
    }
    
    /**
     * حفظ سجل الإشعار المجاني
     */
    private function logFreeNotification($parcel_id, $phone, $recipient_type, $status_id, $message, $whatsapp_link) {
        // إنشاء جدول السجلات المجانية إذا لم يكن موجوداً
        $this->createFreeNotificationTable();
        
        $query = "
            INSERT INTO free_notification_logs 
            (parcel_id, phone_number, recipient_type, status_id, message_content, whatsapp_link, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ississs", $parcel_id, $phone, $recipient_type, $status_id, $message, $whatsapp_link);
        return $stmt->execute();
    }
    
    /**
     * إنشاء جدول السجلات المجانية
     */
    private function createFreeNotificationTable() {
        $query = "
            CREATE TABLE IF NOT EXISTS free_notification_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                parcel_id INT NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                recipient_type ENUM('sender', 'recipient') NOT NULL,
                status_id INT NOT NULL,
                message_content TEXT NOT NULL,
                whatsapp_link TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                sent_manually BOOLEAN DEFAULT FALSE,
                sent_at TIMESTAMP NULL
            )
        ";
        
        $this->conn->query($query);
    }
    
    /**
     * جلب الإشعارات المعلقة للإرسال اليدوي
     */
    public function getPendingNotifications($limit = 20) {
        $this->createFreeNotificationTable();
        
        $query = "
            SELECT fnl.*, p.tracking_number, ps.name_ar as status_name
            FROM free_notification_logs fnl
            LEFT JOIN parcels p ON fnl.parcel_id = p.id
            LEFT JOIN parcel_status ps ON fnl.status_id = ps.id
            WHERE fnl.sent_manually = FALSE
            ORDER BY fnl.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * تحديد الإشعار كمُرسل يدوياً
     */
    public function markAsSent($notification_id) {
        $query = "UPDATE free_notification_logs SET sent_manually = TRUE, sent_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $notification_id);
        return $stmt->execute();
    }
}
?>











