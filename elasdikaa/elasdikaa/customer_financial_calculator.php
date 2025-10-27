<?php
/**
 * نظام حساب الماليات المتقدم للعملاء
 * يشمل: الدفعات الجزئية، المرتجعات، حساب الصافي، خصم الشحن
 */

include 'db_connect.php';

class CustomerFinancialCalculator {
    private $db;
    
    public function __construct($connection) {
        $this->db = $connection;
    }
    
    /**
     * حساب الماليات الشاملة للعميل
     */
    public function calculateCustomerFinancials($customer_id) {
        $customer_id = intval($customer_id);
        
        if ($customer_id <= 0) {
            return ['error' => 'معرف العميل غير صالح'];
        }
        
        // الحصول على جميع الشحنات
        $shipments = $this->getCustomerShipments($customer_id);
        
        $financials = [
            'customer_id' => $customer_id,
            'total_shipments' => count($shipments),
            'delivered_shipments' => 0,
            'returned_shipments' => 0,
            'pending_shipments' => 0,
            
            // الأموال
            'total_cod_amount' => 0,           // إجمالي قيمة COD
            'total_shipping_fees' => 0,       // إجمالي رسوم الشحن
            'total_net_amount' => 0,          // الصافي المستحق للعميل
            'total_paid_amount' => 0,         // المدفوع للعميل
            'total_returned_amount' => 0,     // قيمة المرتجعات
            'remaining_balance' => 0,         // الرصيد المتبقي
            
            // تفاصيل الشحن
            'sender_pay_shipping' => 0,       // عدد الشحنات التي دفع فيها المرسل
            'recipient_pay_shipping' => 0,    // عدد الشحنات التي دفع فيها المستلم
            'sender_shipping_total' => 0,     // مجموع رسوم الشحن على المرسل
            'recipient_shipping_total' => 0,  // مجموع رسوم الشحن على المستلم
            
            // إحصائيات
            'shipments_details' => []
        ];
        
        foreach ($shipments as $shipment) {
            $cod_amount = floatval($shipment['cod_amount']);
            $shipping_fees = floatval($shipment['shipping_fees']);
            $paid_amount = floatval($shipment['paid_amount']);
            $shipping_payer = $shipment['shipping_payer']; // 'sender' or 'recipient'
            $status = intval($shipment['status']);
            $shipment_direction = $shipment['shipment_direction'] ?? 'to_agent'; // إضافة shipment_direction
            $delivery_agent_fee = floatval($shipment['delivery_agent_fee'] ?? 0); // إضافة delivery_agent_fee
            
            // حساب الإحصائيات
            if ($status == 4) { // تم التسليم
                $financials['delivered_shipments']++;
            } elseif ($status == 5) { // تم الدفع بنجاح
                $financials['delivered_shipments']++;
            } elseif ($status == 6) { // دفع جزئي
                $financials['delivered_shipments']++;
            } elseif (in_array($status, [8, 9, 10])) { // مرتجعات
                $financials['returned_shipments']++;
                $financials['total_returned_amount'] += $cod_amount;
            } else {
                $financials['pending_shipments']++;
            }
            
            // حساب الأموال للشحنات المسلمة فقط (بما في ذلك الدفع الجزئي)
            if (in_array($status, [4, 5, 6])) { // تم التسليم، تم الدفع، دفع جزئي
                $financials['total_cod_amount'] += $cod_amount;
                $financials['total_shipping_fees'] += $shipping_fees;
                $financials['total_paid_amount'] += $paid_amount;
                
                // حساب الصافي المستحق للعميل بناءً على نوع الشحنة والدفع الجزئي
                $remaining_cod = $cod_amount - $paid_amount;
                
                if ($remaining_cod > 0) {
                    // ما زال هناك مبلغ متبقي
                    if ($shipment_direction === 'to_agent') {
                        // شحنة إرسال لوكيل
                        if ($shipping_payer === 'sender') {
                            // الشحن على المرسل: المستحق = المتبقي - رسوم الشحن
                            $net_amount = max(0, $remaining_cod - $shipping_fees);
                            $financials['sender_pay_shipping']++;
                            $financials['sender_shipping_total'] += $shipping_fees;
                        } else {
                            // الشحن على المستلم: المستحق = المتبقي
                            $net_amount = $remaining_cod;
                            $financials['recipient_pay_shipping']++;
                            $financials['recipient_shipping_total'] += $shipping_fees;
                        }
                    } else {
                        // شحنة استلام من وكيل
                        // المستحق للعميل = المتبقي من COD (كامل) لأن العميل هو المستلم
                        $net_amount = $remaining_cod;
                        $financials['recipient_pay_shipping']++;
                        $financials['recipient_shipping_total'] += $delivery_agent_fee;
                    }
                } else {
                    // تم الدفع بالكامل
                    $net_amount = 0;
                    if ($shipment_direction === 'to_agent') {
                        if ($shipping_payer === 'sender') {
                            $financials['sender_pay_shipping']++;
                            $financials['sender_shipping_total'] += $shipping_fees;
                        } else {
                            $financials['recipient_pay_shipping']++;
                            $financials['recipient_shipping_total'] += $shipping_fees;
                        }
                    } else {
                        $financials['recipient_pay_shipping']++;
                        $financials['recipient_shipping_total'] += $delivery_agent_fee;
                    }
                }
                
                $financials['total_net_amount'] += $net_amount;
                
                // تفاصيل الشحنة
                $financials['shipments_details'][] = [
                    'id' => $shipment['id'],
                    'tracking_number' => $shipment['tracking_number'],
                    'cod_amount' => $cod_amount,
                    'shipping_fees' => $shipping_fees,
                    'shipping_payer' => $shipping_payer,
                    'paid_amount' => $paid_amount,
                    'net_amount' => $net_amount, // المبلغ المستحق للعميل
                    'remaining_cod' => $remaining_cod, // المتبقي من قيمة الشحنة
                    'status' => $status,
                    'shipment_direction' => $shipment_direction, // إضافة اتجاه الشحنة
                    'delivery_agent_fee' => $delivery_agent_fee // إضافة عمولة المندوب
                ];
            }
        }
        
        // حساب الرصيد المتبقي (المبلغ المستحق للعميل الإجمالي)
        $financials['remaining_balance'] = $financials['total_net_amount'];
        
        return $financials;
    }
    
    /**
     * الحصول على شحنات العميل
     */
    private function getCustomerShipments($customer_id) {
        // الحصول على رقم هاتف العميل أولاً
        $customer_query = "SELECT phone FROM customers WHERE id = ?";
        $stmt = $this->db->prepare($customer_query);
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $customer_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$customer_data) {
            return [];
        }
        
        $query = "
            SELECT p.*, ps.name_ar as status_name
            FROM parcels p
            LEFT JOIN parcel_status ps ON p.status = ps.id
            WHERE p.sender_phone = ?
            ORDER BY p.date_created DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $customer_data['phone']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $shipments = [];
        while ($row = $result->fetch_assoc()) {
            $shipments[] = $row;
        }
        $stmt->close();
        
        return $shipments;
    }
    
    /**
     * تسجيل دفعة جزئية للعميل
     */
    public function processPartialPayment($customer_id, $payment_amount, $selected_shipments = [], $notes = '', $payment_method = 'partial_payment', $reference_number = '') {
        $customer_id = intval($customer_id);
        $payment_amount = floatval($payment_amount);
        
        if ($customer_id <= 0 || $payment_amount <= 0) {
            return ['error' => 'بيانات الدفعة غير صالحة'];
        }
        
        // بدء المعاملة
        $this->db->begin_transaction();
        
        try {
            // حساب الماليات الحالية
            $financials = $this->calculateCustomerFinancials($customer_id);
            
            if (isset($financials['error'])) {
                throw new Exception($financials['error']);
            }
            
            // التحقق من وجود رصيد مستحق
            if ($financials['remaining_balance'] <= 0) {
                throw new Exception('لا يوجد رصيد مستحق لهذا العميل');
            }
            
            // التحقق من صحة مبلغ الدفعة
            if ($payment_amount > $financials['remaining_balance']) {
                throw new Exception('مبلغ الدفعة أكبر من الرصيد المستحق');
            }
            
            // تسجيل الدفعة الرئيسية
            $payment_id = $this->recordCustomerPayment($customer_id, $payment_amount, $notes, $payment_method, $reference_number);
            
            // توزيع المبلغ على الشحنات
            $remaining_payment = $payment_amount;
            
            if (!empty($selected_shipments)) {
                // دفع شحنات محددة
                foreach ($selected_shipments as $shipment_id) {
                    if ($remaining_payment <= 0) break;
                    
                    $shipment_id = intval($shipment_id);
                    $remaining_payment = $this->payShipment($shipment_id, $remaining_payment, $payment_id);
                }
            } else {
                // دفع تلقائي (أقدم الشحنات أولاً)
                foreach ($financials['shipments_details'] as $shipment) {
                    if ($remaining_payment <= 0) break;
                    
                    if ($shipment['net_amount'] > 0) {
                        $remaining_payment = $this->payShipment($shipment['id'], $remaining_payment, $payment_id);
                    }
                }
            }
            
            // تحديث رصيد العميل
            $this->updateCustomerBalance($customer_id);
            
            // تسجيل حركة الرصيد
            $this->recordBalanceTransaction($customer_id, $payment_amount, 'debit', "دفعة جزئية للعميل", $payment_id);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'amount_paid' => $payment_amount,
                'remaining_balance' => $financials['remaining_balance'] - $payment_amount,
                'message' => 'تم تسجيل الدفعة بنجاح'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * تسجيل دفعة العميل
     */
    private function recordCustomerPayment($customer_id, $amount, $notes, $payment_method = 'partial_payment', $reference_number = '') {
        // التحقق من وجود جدول customer_payments
        $table_check = $this->db->query("SHOW TABLES LIKE 'customer_payments'");
        
        if ($table_check && $table_check->num_rows > 0) {
            $query = "
                INSERT INTO customer_payments 
                (customer_id, amount, payment_method, reference_number, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("idsss", $customer_id, $amount, $payment_method, $reference_number, $notes);
            $stmt->execute();
            $payment_id = $this->db->insert_id;
            $stmt->close();
            
            return $payment_id;
        }
        
        return 0;
    }
    
    /**
     * دفع شحنة محددة
     */
    private function payShipment($shipment_id, $available_amount, $payment_id) {
        // الحصول على تفاصيل الشحنة
        $query = "SELECT * FROM parcels WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $shipment_id);
        $stmt->execute();
        $shipment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$shipment) {
            return $available_amount;
        }
        
        $cod_amount = floatval($shipment['cod_amount']);
        $shipping_fees = floatval($shipment['shipping_fees']);
        $paid_amount = floatval($shipment['paid_amount']);
        $shipping_payer = $shipment['shipping_payer'];
        
        // حساب الصافي
        $net_amount = $cod_amount;
        if ($shipping_payer === 'sender') {
            $net_amount -= $shipping_fees;
        }
        
        $remaining_for_shipment = $net_amount - $paid_amount;
        
        if ($remaining_for_shipment <= 0) {
            return $available_amount;
        }
        
        // حساب المبلغ المدفوع لهذه الشحنة
        $payment_for_shipment = min($available_amount, $remaining_for_shipment);
        $new_paid_amount = $paid_amount + $payment_for_shipment;
        
        // تحديث الشحنة
        $new_payment_status = 'partial_paid';
        if ($new_paid_amount >= $net_amount) {
            $new_payment_status = 'paid';
        }
        
        $update_query = "
            UPDATE parcels 
            SET paid_amount = ?, payment_status = ?, updated_at = NOW()
            WHERE id = ?
        ";
        $stmt = $this->db->prepare($update_query);
        $stmt->bind_param("dsi", $new_paid_amount, $new_payment_status, $shipment_id);
        $stmt->execute();
        $stmt->close();
        
        // تسجيل تفاصيل الدفعة للشحنة
        $this->recordShipmentPaymentDetail($shipment_id, $payment_id, $payment_for_shipment);
        
        return $available_amount - $payment_for_shipment;
    }
    
    /**
     * تسجيل تفاصيل دفعة الشحنة
     */
    private function recordShipmentPaymentDetail($shipment_id, $payment_id, $amount) {
        // إنشاء جدول تفاصيل الدفعات إذا لم يكن موجوداً
        $create_table = "
            CREATE TABLE IF NOT EXISTS customer_payment_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_id INT NOT NULL,
                shipment_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_payment (payment_id),
                INDEX idx_shipment (shipment_id)
            )
        ";
        $this->db->query($create_table);
        
        $query = "
            INSERT INTO customer_payment_details 
            (payment_id, shipment_id, amount, created_at)
            VALUES (?, ?, ?, NOW())
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iid", $payment_id, $shipment_id, $amount);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * تحديث رصيد العميل
     */
    private function updateCustomerBalance($customer_id) {
        $financials = $this->calculateCustomerFinancials($customer_id);
        
        if (isset($financials['error'])) {
            return;
        }
        
        // التحقق من وجود عمود balance
        $column_check = $this->db->query("SHOW COLUMNS FROM customers LIKE 'balance'");
        
        if ($column_check && $column_check->num_rows > 0) {
            $query = "UPDATE customers SET balance = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("di", $financials['remaining_balance'], $customer_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * تسجيل حركة رصيد
     */
    private function recordBalanceTransaction($customer_id, $amount, $type, $description, $reference_id = null) {
        // التحقق من وجود جدول customer_balances
        $table_check = $this->db->query("SHOW TABLES LIKE 'customer_balances'");
        
        if ($table_check && $table_check->num_rows > 0) {
            // الحصول على الرصيد السابق
            $balance_before = $this->getCustomerCurrentBalance($customer_id);
            $balance_after = $type === 'credit' ? $balance_before + $amount : $balance_before - $amount;
            
            $query = "
                INSERT INTO customer_balances 
                (customer_id, type, amount, description, balance_before, balance_after, 
                 reference_type, reference_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'payment', ?, 'completed', NOW())
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("isdsddi", $customer_id, $type, $amount, $description, 
                             $balance_before, $balance_after, $reference_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * الحصول على الرصيد الحالي للعميل
     */
    private function getCustomerCurrentBalance($customer_id) {
        $column_check = $this->db->query("SHOW COLUMNS FROM customers LIKE 'balance'");
        
        if ($column_check && $column_check->num_rows > 0) {
            $query = "SELECT balance FROM customers WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            return floatval($result['balance'] ?? 0);
        }
        
        return 0;
    }
    
    /**
     * الحصول على تقرير مالي مفصل للعميل
     */
    public function getDetailedFinancialReport($customer_id) {
        $financials = $this->calculateCustomerFinancials($customer_id);
        
        if (isset($financials['error'])) {
            return $financials;
        }
        
        // إضافة تاريخ المدفوعات
        $payments = $this->getCustomerPayments($customer_id);
        $financials['payments_history'] = $payments;
        
        return $financials;
    }
    
    /**
     * الحصول على سجل مدفوعات العميل
     */
    private function getCustomerPayments($customer_id) {
        $table_check = $this->db->query("SHOW TABLES LIKE 'customer_payments'");
        
        if ($table_check && $table_check->num_rows > 0) {
            $query = "
                SELECT cp.*, 
                       GROUP_CONCAT(
                           CONCAT(cpd.shipment_id, ':', cpd.amount) 
                           SEPARATOR ';'
                       ) as shipment_details
                FROM customer_payments cp
                LEFT JOIN customer_payment_details cpd ON cp.id = cpd.payment_id
                WHERE cp.customer_id = ?
                GROUP BY cp.id
                ORDER BY cp.created_at DESC
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $payments = [];
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            $stmt->close();
            
            return $payments;
        }
        
        return [];
    }
}
?>
