<?php
/**
 * فئة إدارة إشعارات WhatsApp التلقائية
 * تدعم عدة مقدمي خدمة API
 */

class WhatsAppNotificationManager {
    private $conn;
    private $config;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->loadConfig();
    }
    
    /**
     * تحميل إعدادات WhatsApp API
     */
    private function loadConfig() {
        $query = "SELECT * FROM whatsapp_config WHERE is_active = 1 LIMIT 1";
        $result = $this->conn->query($query);
        $this->config = $result->fetch_assoc();
    }
    
    /**
     * إرسال إشعار عند تغيير حالة الطلب
     */
    public function sendStatusChangeNotification($parcel_id, $new_status_id, $old_status_id = null) {
        try {
            // جلب بيانات الشحنة
            $parcel_data = $this->getParcelData($parcel_id);
            if (!$parcel_data) {
                throw new Exception("الشحنة غير موجودة");
            }
            
            // جلب إعدادات الإشعار لهذه الحالة
            $notification_settings = $this->getNotificationSettings($new_status_id);
            if (!$notification_settings) {
                return false; // لا توجد إعدادات للإشعار
            }
            
            $results = [];
            
            // إرسال للراسل
            if ($notification_settings['send_to_sender'] && $notification_settings['sender_message_template']) {
                $message = $this->buildMessage($notification_settings['sender_message_template'], $parcel_data);
                $result = $this->sendMessage($parcel_data['sender_phone'], $message, 'sender', $parcel_id, $new_status_id);
                $results['sender'] = $result;
            }
            
            // إرسال للمستلم
            if ($notification_settings['send_to_recipient'] && $notification_settings['recipient_message_template']) {
                $message = $this->buildMessage($notification_settings['recipient_message_template'], $parcel_data);
                $result = $this->sendMessage($parcel_data['recipient_phone'], $message, 'recipient', $parcel_id, $new_status_id);
                $results['recipient'] = $result;
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("WhatsApp Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب بيانات الشحنة الكاملة
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
                ar_rec.name as recipient_area_name,
                a.company_name as agent_company
            FROM parcels p
            LEFT JOIN couriers c ON p.courier_id = c.id
            LEFT JOIN parcel_status ps ON p.status = ps.id
            LEFT JOIN parcel_status_reasons psr ON p.status_reason_id = psr.id
            LEFT JOIN governorates g_rec ON p.recipient_governorate_id = g_rec.id
            LEFT JOIN areas ar_rec ON p.recipient_area_id = ar_rec.id
            LEFT JOIN agents a ON p.agent_id = a.id
            WHERE p.id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * جلب إعدادات الإشعار للحالة المحددة
     */
    private function getNotificationSettings($status_id) {
        $query = "SELECT * FROM notification_settings WHERE status_id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $status_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * بناء الرسالة باستخدام المتغيرات
     */
    private function buildMessage($template, $parcel_data) {
        $placeholders = [
            '{sender_name}' => $parcel_data['sender_name'] ?? '',
            '{recipient_name}' => $parcel_data['recipient_name'] ?? '',
            '{tracking_number}' => $parcel_data['tracking_number'] ?? '',
            '{cod_amount}' => number_format($parcel_data['cod_amount'] ?? 0, 2),
            '{recipient_phone}' => $parcel_data['recipient_phone'] ?? '',
            '{recipient_address}' => $parcel_data['recipient_address'] ?? '',
            '{courier_name}' => $parcel_data['courier_name'] ?? 'لم يحدد بعد',
            '{courier_phone}' => $parcel_data['courier_phone'] ?? '',
            '{status_name}' => $parcel_data['status_name'] ?? '',
            '{status_reason}' => $parcel_data['status_reason'] ?? '',
            '{status_reason_note}' => $parcel_data['status_reason_note'] ?? '',
            '{recipient_gov}' => $parcel_data['recipient_gov_name'] ?? '',
            '{recipient_area}' => $parcel_data['recipient_area_name'] ?? '',
            '{delivery_time}' => date('Y-m-d H:i'),
            '{company_name}' => 'شركة الشحن', // يمكن جعلها قابلة للتخصيص
            '{company_phone}' => $this->config['phone_number'] ?? ''
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * إرسال الرسالة عبر WhatsApp API
     */
    private function sendMessage($phone, $message, $recipient_type, $parcel_id, $status_id) {
        if (!$this->config || !$this->config['is_active']) {
            return $this->logNotification($parcel_id, $phone, $recipient_type, $status_id, $message, 'failed', 'WhatsApp API غير مفعل');
        }
        
        // تنظيف رقم الهاتف
        $clean_phone = $this->cleanPhoneNumber($phone);
        
        try {
            $api_response = null;
            $send_status = 'failed';
            
            switch ($this->config['api_provider']) {
                case 'twilio':
                    $api_response = $this->sendViaTwilio($clean_phone, $message);
                    break;
                case 'maytapi':
                    $api_response = $this->sendViaMaytapi($clean_phone, $message);
                    break;
                case 'chatapi':
                    $api_response = $this->sendViaChatAPI($clean_phone, $message);
                    break;
                case 'custom':
                    $api_response = $this->sendViaCustomAPI($clean_phone, $message);
                    break;
                default:
                    throw new Exception("مقدم خدمة API غير مدعوم");
            }
            
            if ($api_response && isset($api_response['success']) && $api_response['success']) {
                $send_status = 'sent';
            }
            
            return $this->logNotification($parcel_id, $clean_phone, $recipient_type, $status_id, $message, $send_status, json_encode($api_response));
            
        } catch (Exception $e) {
            return $this->logNotification($parcel_id, $clean_phone, $recipient_type, $status_id, $message, 'failed', $e->getMessage());
        }
    }
    
    /**
     * تسجيل محاولة الإرسال في قاعدة البيانات
     */
    private function logNotification($parcel_id, $phone, $recipient_type, $status_id, $message, $send_status, $response = null) {
        $query = "
            INSERT INTO notification_logs 
            (parcel_id, phone_number, recipient_type, status_id, message_content, send_status, api_response, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $sent_at = ($send_status === 'sent') ? date('Y-m-d H:i:s') : null;
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ississss", $parcel_id, $phone, $recipient_type, $status_id, $message, $send_status, $response, $sent_at);
        
        return $stmt->execute();
    }
    
    /**
     * تنظيف رقم الهاتف
     */
    private function cleanPhoneNumber($phone) {
        // إزالة المسافات والرموز الخاصة
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        
        // إضافة كود مصر إذا لم يكن موجوداً
        if (substr($clean, 0, 1) === '0') {
            $clean = '+2' . $clean;
        } elseif (substr($clean, 0, 1) !== '+') {
            $clean = '+20' . $clean;
        }
        
        return $clean;
    }
    
    /**
     * إرسال عبر Twilio
     */
    private function sendViaTwilio($phone, $message) {
        // مثال للتكامل مع Twilio
        // يحتاج إلى مكتبة Twilio SDK أو استدعاء REST API
        
        $url = 'https://api.twilio.com/2010-04-01/Accounts/YOUR_ACCOUNT_SID/Messages.json';
        
        $data = [
            'From' => 'whatsapp:' . $this->config['phone_number'],
            'To' => 'whatsapp:' . $phone,
            'Body' => $message
        ];
        
        return $this->makeHttpRequest($url, $data, [
            'Authorization: Basic ' . base64_encode($this->config['api_token'])
        ]);
    }
    
    /**
     * إرسال عبر Maytapi
     */
    private function sendViaMaytapi($phone, $message) {
        $url = $this->config['api_url'] . '/api/v1/sendMessage';
        
        $data = [
            'to_number' => $phone,
            'message' => $message,
            'type' => 'text'
        ];
        
        return $this->makeHttpRequest($url, $data, [
            'Content-Type: application/json',
            'x-maytapi-key: ' . $this->config['api_token']
        ]);
    }
    
    /**
     * إرسال عبر ChatAPI
     */
    private function sendViaChatAPI($phone, $message) {
        $url = $this->config['api_url'] . '/sendMessage?token=' . $this->config['api_token'];
        
        $data = [
            'phone' => $phone,
            'body' => $message
        ];
        
        return $this->makeHttpRequest($url, $data);
    }
    
    /**
     * إرسال عبر API مخصص
     */
    private function sendViaCustomAPI($phone, $message) {
        // يمكن تخصيصه حسب الحاجة
        return ['success' => false, 'message' => 'Custom API not implemented'];
    }
    
    /**
     * تنفيذ طلب HTTP
     */
    private function makeHttpRequest($url, $data, $headers = []) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json'
            ], $headers),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded_response = json_decode($response, true);
        
        return [
            'success' => ($http_code >= 200 && $http_code < 300),
            'http_code' => $http_code,
            'response' => $decoded_response,
            'raw_response' => $response
        ];
    }
    
    /**
     * الحصول على سجل الإشعارات لشحنة معينة
     */
    public function getNotificationLogs($parcel_id) {
        $query = "
            SELECT nl.*, ps.name_ar as status_name
            FROM notification_logs nl
            LEFT JOIN parcel_status ps ON nl.status_id = ps.id
            WHERE nl.parcel_id = ?
            ORDER BY nl.created_at DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * إعادة محاولة إرسال الإشعارات الفاشلة
     */
    public function retryFailedNotifications($limit = 10) {
        $query = "
            SELECT * FROM notification_logs 
            WHERE send_status = 'failed' AND retry_count < 3
            ORDER BY created_at ASC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $failed_notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($failed_notifications as $notification) {
            $result = $this->sendMessage(
                $notification['phone_number'],
                $notification['message_content'],
                $notification['recipient_type'],
                $notification['parcel_id'],
                $notification['status_id']
            );
            
            // تحديث عداد المحاولات
            $update_query = "UPDATE notification_logs SET retry_count = retry_count + 1 WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bind_param("i", $notification['id']);
            $update_stmt->execute();
        }
    }
}
?>











