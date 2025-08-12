<?php
/**
 * Enhanced Payment System with Partial Payments, Delivery Tracking, and Returns
 * النظام المحدث للدفعات الجزئية مع تتبع التسليم والمرتجعات
 */

class EnhancedPaymentSystem {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * إضافة دفعة جديدة للشحنة
     * Add new payment to shipment
     */
    public function addPayment($parcel_id, $payment_amount, $payment_method = 'cash', $reference_number = '', $notes = '', $processed_by = 'system') {
        // التحقق من صحة البيانات
        if ($parcel_id <= 0 || $payment_amount <= 0) {
            return [
                'success' => false, 
                'message' => 'بيانات غير صالحة للدفع'
            ];
        }
        
        $this->db->begin_transaction();
        
        try {
            // جلب بيانات الشحنة الحالية
            $parcel = $this->getParcelFinancialData($parcel_id);
            if (!$parcel) {
                throw new Exception('الشحنة غير موجودة');
            }
            
            // التحقق من أن المبلغ لا يتجاوز الرصيد المتبقي
            if ($payment_amount > $parcel['remaining_balance']) {
                throw new Exception('مبلغ الدفع يتجاوز الرصيد المتبقي (' . number_format($parcel['remaining_balance'], 2) . ' جنيه)');
            }
            
            // حساب القيم الجديدة
            $new_amount_paid = $parcel['amount_paid_so_far'] + $payment_amount;
            $new_remaining_balance = $parcel['delivered_value'] - $new_amount_paid;
            
            // تحديد الحالة الجديدة
            $new_payment_status = $this->calculatePaymentStatus($new_amount_paid, $parcel['delivered_value']);
            $new_parcel_status = $this->getParcelStatusFromPaymentStatus($new_payment_status);
            
            // تحديث الشحنة
            $update_sql = "UPDATE parcels SET 
                amount_paid_so_far = ?,
                remaining_balance = ?,
                detailed_payment_status = ?,
                status = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $stmt = $this->db->prepare($update_sql);
            $stmt->bind_param("ddsii", $new_amount_paid, $new_remaining_balance, $new_payment_status, $new_parcel_status, $parcel_id);
            $stmt->execute();
            $stmt->close();
            
            // إضافة سجل في معاملات الدفع
            $this->recordPaymentTransaction($parcel_id, $payment_amount, $payment_method, $reference_number, $notes, $processed_by);
            
            // إضافة سجل في تاريخ الدفعات
            $this->recordPaymentHistory($parcel_id, $payment_amount, $payment_method, $new_remaining_balance, $new_payment_status, $reference_number, $notes, $processed_by);
            
            // إضافة سجل تتبع
            $this->addTrackingRecord($parcel_id, $new_parcel_status, "دفعة: " . number_format($payment_amount, 2) . " جنيه، المتبقي: " . number_format($new_remaining_balance, 2) . " جنيه");
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'تمت إضافة الدفعة بنجاح',
                'payment_amount' => $payment_amount,
                'total_paid' => $new_amount_paid,
                'remaining_balance' => $new_remaining_balance,
                'payment_status' => $new_payment_status,
                'parcel_status' => $new_parcel_status
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'خطأ في إضافة الدفعة: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تسجيل تسليم جزئي أو كامل
     * Record partial or full delivery
     */
    public function recordDelivery($parcel_id, $delivered_value, $delivery_notes = '', $processed_by = 'system') {
        if ($parcel_id <= 0 || $delivered_value <= 0) {
            return [
                'success' => false,
                'message' => 'بيانات غير صالحة للتسليم'
            ];
        }
        
        $this->db->begin_transaction();
        
        try {
            // جلب بيانات الشحنة
            $parcel = $this->getParcelFinancialData($parcel_id);
            if (!$parcel) {
                throw new Exception('الشحنة غير موجودة');
            }
            
            // التحقق من أن القيمة المسلمة لا تتجاوز الإجمالي
            if ($delivered_value > $parcel['total_shipment_value']) {
                throw new Exception('القيمة المسلمة تتجاوز إجمالي قيمة الشحنة');
            }
            
            // حساب القيم الجديدة
            $new_delivered_value = $parcel['delivered_value'] + $delivered_value;
            $new_remaining_balance = $new_delivered_value - $parcel['amount_paid_so_far'];
            
            // تحديد الحالة
            $delivery_status = ($new_delivered_value >= $parcel['total_shipment_value']) ? 'fully_delivered' : 'partially_delivered';
            $payment_status = $this->calculatePaymentStatus($parcel['amount_paid_so_far'], $new_delivered_value);
            $parcel_status = 4; // تم التسليم
            
            // تحديث الشحنة
            $update_sql = "UPDATE parcels SET 
                delivered_value = ?,
                remaining_balance = ?,
                detailed_payment_status = ?,
                status = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $stmt = $this->db->prepare($update_sql);
            $stmt->bind_param("ddsii", $new_delivered_value, $new_remaining_balance, $payment_status, $parcel_status, $parcel_id);
            $stmt->execute();
            $stmt->close();
            
            // إضافة سجل معاملة
            $this->recordDeliveryTransaction($parcel_id, $delivered_value, $delivery_notes, $processed_by);
            
            // إضافة سجل تتبع
            $this->addTrackingRecord($parcel_id, $parcel_status, "تسليم: " . number_format($delivered_value, 2) . " جنيه، إجمالي مسلم: " . number_format($new_delivered_value, 2) . " جنيه");
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'تم تسجيل التسليم بنجاح',
                'delivered_value' => $delivered_value,
                'total_delivered' => $new_delivered_value,
                'remaining_balance' => $new_remaining_balance,
                'delivery_status' => $delivery_status,
                'payment_status' => $payment_status
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'خطأ في تسجيل التسليم: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تسجيل مرتجع
     * Record return
     */
    public function recordReturn($parcel_id, $return_value, $return_reason = '', $items_count = 1, $processed_by = 'system') {
        if ($parcel_id <= 0 || $return_value <= 0) {
            return [
                'success' => false,
                'message' => 'بيانات غير صالحة للمرتجع'
            ];
        }
        
        $this->db->begin_transaction();
        
        try {
            // جلب بيانات الشحنة
            $parcel = $this->getParcelFinancialData($parcel_id);
            if (!$parcel) {
                throw new Exception('الشحنة غير موجودة');
            }
            
            // التحقق من أن قيمة المرتجع لا تتجاوز المسلم
            $new_returned_value = $parcel['returned_value'] + $return_value;
            if ($new_returned_value > $parcel['delivered_value']) {
                throw new Exception('قيمة المرتجع تتجاوز القيمة المسلمة');
            }
            
            // تحديث الشحنة
            $update_sql = "UPDATE parcels SET 
                returned_value = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $stmt = $this->db->prepare($update_sql);
            $stmt->bind_param("di", $new_returned_value, $parcel_id);
            $stmt->execute();
            $stmt->close();
            
            // إضافة سجل المرتجع التفصيلي
            $this->recordReturnDetail($parcel_id, $items_count, $return_value, $return_reason, $processed_by);
            
            // إضافة سجل معاملة
            $this->recordReturnTransaction($parcel_id, $return_value, $return_reason, $processed_by);
            
            // إضافة سجل تتبع
            $this->addTrackingRecord($parcel_id, 9, "مرتجع: " . number_format($return_value, 2) . " جنيه، السبب: " . $return_reason);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'تم تسجيل المرتجع بنجاح',
                'return_value' => $return_value,
                'total_returned' => $new_returned_value,
                'return_reason' => $return_reason
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'خطأ في تسجيل المرتجع: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * جلب البيانات المالية للشحنة
     * Get parcel financial data
     */
    public function getParcelFinancialData($parcel_id) {
        $sql = "SELECT 
            id, 
            tracking_number,
            IFNULL(total_shipment_value, 0) as total_shipment_value,
            IFNULL(delivered_value, 0) as delivered_value,
            IFNULL(returned_value, 0) as returned_value,
            IFNULL(amount_paid_so_far, 0) as amount_paid_so_far,
            IFNULL(remaining_balance, 0) as remaining_balance,
            detailed_payment_status,
            status,
            sender_phone,
            recipient_name
            FROM parcels WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * جلب تاريخ دفعات الشحنة
     * Get parcel payment history
     */
    public function getParcelPaymentHistory($parcel_id) {
        $sql = "SELECT 
            payment_amount,
            payment_method,
            payment_date,
            remaining_after_payment,
            payment_status_after,
            reference_number,
            notes,
            processed_by
            FROM parcel_payment_history 
            WHERE parcel_id = ? 
            ORDER BY payment_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        $stmt->close();
        
        return $payments;
    }
    
    /**
     * جلب تفاصيل المرتجعات
     * Get return details
     */
    public function getParcelReturns($parcel_id) {
        $sql = "SELECT 
            returned_items_count,
            returned_value,
            return_reason,
            return_date,
            return_method,
            processed_by,
            notes
            FROM parcel_returns 
            WHERE parcel_id = ? 
            ORDER BY return_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $returns = [];
        while ($row = $result->fetch_assoc()) {
            $returns[] = $row;
        }
        $stmt->close();
        
        return $returns;
    }
    
    /**
     * جلب ملخص مالي شامل للشحنة
     * Get comprehensive financial summary
     */
    public function getParcelFinancialSummary($parcel_id) {
        $parcel = $this->getParcelFinancialData($parcel_id);
        if (!$parcel) {
            return null;
        }
        
        $payments = $this->getParcelPaymentHistory($parcel_id);
        $returns = $this->getParcelReturns($parcel_id);
        
        return [
            'parcel_info' => $parcel,
            'payment_history' => $payments,
            'returns' => $returns,
            'summary' => [
                'total_shipment_value' => $parcel['total_shipment_value'],
                'delivered_value' => $parcel['delivered_value'],
                'returned_value' => $parcel['returned_value'],
                'amount_paid_so_far' => $parcel['amount_paid_so_far'],
                'remaining_balance' => $parcel['remaining_balance'],
                'payment_status' => $parcel['detailed_payment_status'],
                'delivery_percentage' => ($parcel['total_shipment_value'] > 0) ? 
                    round(($parcel['delivered_value'] / $parcel['total_shipment_value']) * 100, 2) : 0,
                'payment_percentage' => ($parcel['delivered_value'] > 0) ? 
                    round(($parcel['amount_paid_so_far'] / $parcel['delivered_value']) * 100, 2) : 0
            ]
        ];
    }
    
    /**
     * حساب حالة الدفع
     * Calculate payment status
     */
    private function calculatePaymentStatus($amount_paid, $delivered_value) {
        if ($amount_paid == 0) {
            return 'unpaid';
        } elseif ($amount_paid >= $delivered_value) {
            return 'fully_paid';
        } else {
            return 'partially_paid';
        }
    }
    
    /**
     * الحصول على حالة الشحنة من حالة الدفع
     * Get parcel status from payment status
     */
    private function getParcelStatusFromPaymentStatus($payment_status) {
        switch ($payment_status) {
            case 'fully_paid':
                return 5; // تم الدفع بنجاح
            case 'partially_paid':
                return 6; // تم الدفع جزئي
            case 'unpaid':
            default:
                return 4; // تم التسليم (بدون دفع)
        }
    }
    
    /**
     * تسجيل معاملة دفع
     * Record payment transaction
     */
    private function recordPaymentTransaction($parcel_id, $amount, $payment_method, $reference_number, $notes, $processed_by) {
        $sql = "INSERT INTO parcel_payment_transactions 
            (parcel_id, transaction_type, amount, payment_method, description, reference_number, created_by) 
            VALUES (?, 'payment', ?, ?, ?, ?, ?)";
        
        $description = "دفعة بقيمة " . number_format($amount, 2) . " جنيه";
        if (!empty($notes)) {
            $description .= " - " . $notes;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("idssss", $parcel_id, $amount, $payment_method, $description, $reference_number, $processed_by);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * تسجيل تاريخ الدفعة
     * Record payment history
     */
    private function recordPaymentHistory($parcel_id, $payment_amount, $payment_method, $remaining_after, $payment_status, $reference_number, $notes, $processed_by) {
        $sql = "INSERT INTO parcel_payment_history 
            (parcel_id, payment_amount, payment_method, remaining_after_payment, payment_status_after, reference_number, notes, processed_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("idsdssss", $parcel_id, $payment_amount, $payment_method, $remaining_after, $payment_status, $reference_number, $notes, $processed_by);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * تسجيل معاملة تسليم
     * Record delivery transaction
     */
    private function recordDeliveryTransaction($parcel_id, $delivered_value, $notes, $processed_by) {
        $sql = "INSERT INTO parcel_payment_transactions 
            (parcel_id, transaction_type, amount, description, created_by) 
            VALUES (?, 'delivery', ?, ?, ?)";
        
        $description = "تسليم بقيمة " . number_format($delivered_value, 2) . " جنيه";
        if (!empty($notes)) {
            $description .= " - " . $notes;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("idss", $parcel_id, $delivered_value, $description, $processed_by);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * تسجيل تفاصيل المرتجع
     * Record return details
     */
    private function recordReturnDetail($parcel_id, $items_count, $return_value, $return_reason, $processed_by) {
        $sql = "INSERT INTO parcel_returns 
            (parcel_id, returned_items_count, returned_value, return_reason, processed_by) 
            VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iidss", $parcel_id, $items_count, $return_value, $return_reason, $processed_by);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * تسجيل معاملة مرتجع
     * Record return transaction
     */
    private function recordReturnTransaction($parcel_id, $return_value, $return_reason, $processed_by) {
        $sql = "INSERT INTO parcel_payment_transactions 
            (parcel_id, transaction_type, amount, description, created_by) 
            VALUES (?, 'return', ?, ?, ?)";
        
        $description = "مرتجع بقيمة " . number_format($return_value, 2) . " جنيه";
        if (!empty($return_reason)) {
            $description .= " - السبب: " . $return_reason;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("idss", $parcel_id, $return_value, $description, $processed_by);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * إضافة سجل تتبع
     * Add tracking record
     */
    private function addTrackingRecord($parcel_id, $status, $remarks) {
        $sql = "INSERT INTO parcel_tracks (parcel_id, status, remarks, date_created) VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iis", $parcel_id, $status, $remarks);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * تحديث حالة الشحنة تلقائياً
     * Update shipment status automatically
     */
    public function updateShipmentStatus($parcel_id) {
        $parcel = $this->getParcelFinancialData($parcel_id);
        if (!$parcel) {
            return false;
        }
        
        $payment_status = $this->calculatePaymentStatus($parcel['amount_paid_so_far'], $parcel['delivered_value']);
        $parcel_status = $this->getParcelStatusFromPaymentStatus($payment_status);
        
        $sql = "UPDATE parcels SET 
            detailed_payment_status = ?,
            status = ?,
            updated_at = NOW()
            WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sii", $payment_status, $parcel_status, $parcel_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * إحصائيات مالية للفترة المحددة
     * Financial statistics for specified period
     */
    public function getFinancialStats($date_from, $date_to) {
        $sql = "SELECT 
            COUNT(*) as total_shipments,
            SUM(total_shipment_value) as total_shipment_value,
            SUM(delivered_value) as total_delivered_value,
            SUM(returned_value) as total_returned_value,
            SUM(amount_paid_so_far) as total_amount_paid,
            SUM(remaining_balance) as total_outstanding_balance,
            SUM(CASE WHEN detailed_payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN detailed_payment_status = 'partially_paid' THEN 1 ELSE 0 END) as partially_paid_count,
            SUM(CASE WHEN detailed_payment_status = 'fully_paid' THEN 1 ELSE 0 END) as fully_paid_count
            FROM parcels 
            WHERE DATE(date_created) BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result;
    }
}

/**
 * دالة مساعدة لمعالجة الدفع الجزئي المحدث
 * Helper function for updated partial payment processing
 */
function processEnhancedPartialPayment($parcel_id, $payment_amount, $payment_method = 'cash', $notes = '', $conn) {
    $payment_system = new EnhancedPaymentSystem($conn);
    return $payment_system->addPayment($parcel_id, $payment_amount, $payment_method, '', $notes, 'admin');
}

/**
 * دالة مساعدة لتسجيل التسليم
 * Helper function for delivery recording
 */
function recordEnhancedDelivery($parcel_id, $delivered_value, $notes = '', $conn) {
    $payment_system = new EnhancedPaymentSystem($conn);
    return $payment_system->recordDelivery($parcel_id, $delivered_value, $notes, 'admin');
}

/**
 * دالة مساعدة لتسجيل المرتجع
 * Helper function for return recording
 */
function recordEnhancedReturn($parcel_id, $return_value, $return_reason = '', $items_count = 1, $conn) {
    $payment_system = new EnhancedPaymentSystem($conn);
    return $payment_system->recordReturn($parcel_id, $return_value, $return_reason, $items_count, 'admin');
}

/**
 * دالة للحصول على ملخص مالي شامل
 * Function to get comprehensive financial summary
 */
function getEnhancedFinancialSummary($parcel_id, $conn) {
    $payment_system = new EnhancedPaymentSystem($conn);
    return $payment_system->getParcelFinancialSummary($parcel_id);
}

?>
