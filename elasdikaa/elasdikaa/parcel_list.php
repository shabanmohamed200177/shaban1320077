<?php
// ملف: parcel_list.php - صفحة قائمة الشحنات المحسنة

// تفعيل عرض الأخطاء للمساعدة في تصحيح أي مشاكل
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// التأكد من وجود ملف الاتصال بقاعدة البيانات
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    include 'db_connect.php';
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'PaymentService.php';

// تعريف مصفوفة حالات الشحنات الجديدة بناءً على parcel_status (1).sql
$status_arr = array(
    1  => 'قيد التنفيذ',
    2  => 'تم تسليمها للمندوب',
    3  => 'جاري التوصيل',
    4  => 'تم التسليم بنجاح',
    5  => 'تم الدفع بنجاح',
    6  => 'تم الدفع جزئي',
    7  => 'تم تأجيل الطلب',
    8  => 'تم الرفض',
    9  => 'المرتجعات',
    10 => 'مرتجع للمخزن',
    11 => 'مرتجع للفرع',
    12 => 'مرتجع للعميل'
);

// جلب حالات الشحنة من قاعدة البيانات للـ modal
$parcel_statuses = [];
$status_query = $conn->query("SELECT * FROM parcel_status ORDER BY id");
if ($status_query) {
    while ($status_row = $status_query->fetch_assoc()) {
        $parcel_statuses[] = $status_row;
    }
} else {
    // في حالة عدم وجود جدول parcel_status، استخدم المصفوفة الثابتة
    foreach ($status_arr as $id => $name) {
        $parcel_statuses[] = ['id' => $id, 'name_ar' => $name];
    }
}

// -----------------------------------------------------------
// منطق معالجة عمليات POST (تم إزالة أزرار حذف الكل وتصفير الأرصدة)
// -----------------------------------------------------------

// -----------------------------------------------------------
// دالة إنشاء الإشعارات المجانية المبسطة
// -----------------------------------------------------------
function createFreeNotification($conn, $parcel_id, $status_id) {
    try {
        // إنشاء جدول الإشعارات إذا لم يكن موجوداً
        $create_table = "
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
        $conn->query($create_table);
        
        // جلب بيانات الشحنة
        $parcel_query = "
            SELECT p.*, c.name as courier_name, c.phone as courier_phone, ps.name_ar as status_name,
                   a.company_name as agent_name, psr.reason_text as status_reason
            FROM parcels p
            LEFT JOIN couriers c ON p.courier_id = c.id
            LEFT JOIN parcel_status ps ON p.status = ps.id
            LEFT JOIN agents a ON p.agent_id = a.id
            LEFT JOIN parcel_status_reasons psr ON p.status_reason_id = psr.id
            WHERE p.id = ?
        ";
        $stmt = $conn->prepare($parcel_query);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $parcel = $stmt->get_result()->fetch_assoc();
        
        if (!$parcel) return false;
        
        // التحقق من أن الشحنة مستلمة من وكيل
        if ($parcel['shipment_direction'] !== 'from_agent') {
            return false; // لا نرسل إشعارات للشحنات المرسلة للوكلاء
        }
        
        // قوالب الرسائل للشحنات المستلمة من الوكيل
        $templates = [
            1 => [ // قيد التنفيذ
                'sender' => "📦 تم استلام شحنتك للتوصيل\n\nعزيزي {sender_name}\n\nتم استلام شحنتك وهي الآن قيد التجهيز للتوصيل 📋\n\n📦 رقم الشحنة: {tracking_number}\n📍 المستلم: {recipient_name}\n📱 هاتف المستلم: {recipient_phone}\n💰 قيمة التحصيل: {cod_amount} جنيه\n\nسيتم تحديثك عند تكليف مندوب للتوصيل\n\nشركة الشحن"
            ],
            2 => [ // تم تسليمها للمندوب
                'sender' => "🚚 شحنتك مع المندوب للتوصيل\n\nعزيزي {sender_name}\n\nتم تكليف مندوب بتوصيل شحنتك ✅\n\n📦 رقم الشحنة: {tracking_number}\n👤 المندوب: {courier_name}\n📱 هاتف المندوب: {courier_phone}\n📍 المستلم: {recipient_name}\n💰 قيمة التحصيل: {cod_amount} جنيه\n\nسيتم التوصيل قريباً\n\nشركة الشحن",
                'recipient' => "📦 شحنة قادمة إليك\n\nعزيزي {recipient_name}\n\nلديك شحنة قادمة من {sender_name} 📬\n\n📦 رقم التتبع: {tracking_number}\n👤 المندوب: {courier_name}\n📱 هاتف المندوب: {courier_phone}\n💰 المبلغ المطلوب: {cod_amount} جنيه\n\nالشحنة الآن مع المندوب وسيتم التواصل معك قريباً\n\nشركة الشحن"
            ],
            3 => [ // جاري التوصيل
                'recipient' => "🚛 المندوب في طريقه إليك الآن\n\nعزيزي {recipient_name}\n\nالمندوب الآن قادم لتوصيل شحنتك من {sender_name} 🚚\n\n📦 رقم الشحنة: {tracking_number}\n👤 المندوب: {courier_name}\n📱 هاتف المندوب: {courier_phone}\n💰 المبلغ المطلوب: {cod_amount} جنيه\n\n⚠️ يرجى التأكد من:\n✅ تواجدك في العنوان\n✅ توفر المبلغ المطلوب\n\nشركة الشحن"
            ],
            4 => [ // تم التسليم بنجاح
                'sender' => "✅ تم تسليم شحنتك بنجاح\n\nعزيزي {sender_name}\n\nنخبرك بسعادة أن شحنتك تم تسليمها بنجاح! 🎉\n\n📦 رقم الشحنة: {tracking_number}\n📍 تم التسليم إلى: {recipient_name}\n💰 تم تحصيل: {cod_amount} جنيه\n⏰ وقت التسليم: الآن\n\nشكراً لثقتك في خدماتنا 🙏\nسيتم تحويل مستحقاتك حسب الاتفاق\n\nشركة الشحن",
                'recipient' => "✅ شكراً لاستلام الشحنة\n\nعزيزي {recipient_name}\n\nشكراً لاستلام الشحنة من {sender_name} 🙏\n\n📦 رقم الشحنة: {tracking_number}\n💰 المبلغ المدفوع: {cod_amount} جنيه\n⏰ وقت الاستلام: الآن\n\nنأمل أن تكون راضي عن الخدمة\nنتطلع لخدمتك مرة أخرى 😊\n\nشركة الشحن"
            ],
            5 => [ // تم الدفع بنجاح
                'sender' => "💰 تم تحصيل مبلغ شحنتك\n\nعزيزي {sender_name}\n\nتم تحصيل مبلغ شحنتك بنجاح ✅\n\n📦 رقم الشحنة: {tracking_number}\n💰 المبلغ المحصل: {cod_amount} جنيه\n📍 من المستلم: {recipient_name}\n\nسيتم تحويل مستحقاتك خلال فترة التسوية المتفق عليها 💸\n\nشكراً لثقتك بخدماتنا 🙏\n\nشركة الشحن"
            ],
            8 => [ // تم الرفض
                'sender' => "❌ تم رفض استلام الشحنة\n\nعزيزي {sender_name}\n\nللأسف تم رفض استلام شحنتك\n\n📦 رقم الشحنة: {tracking_number}\n📍 المستلم: {recipient_name}\n❗ سبب الرفض: {status_reason}\n📝 ملاحظة: {status_reason_note}\n\n🔄 الشحنة الآن في طريق العودة\nسيتم التواصل معك لترتيب الاستلام أو إعادة المحاولة\n\nللاستفسار: اتصل بنا\n\nشركة الشحن"
            ]
        ];
        
        if (!isset($templates[$status_id])) return false;
        
        $count = 0;
        foreach ($templates[$status_id] as $type => $template) {
            $phone = ($type === 'sender') ? $parcel['sender_phone'] : $parcel['recipient_phone'];
            $name = ($type === 'sender') ? $parcel['sender_name'] : $parcel['recipient_name'];
            
            // بناء الرسالة
            $message = str_replace([
                '{sender_name}', '{recipient_name}', '{tracking_number}', 
                '{courier_name}', '{courier_phone}', '{cod_amount}',
                '{agent_name}', '{recipient_phone}', '{status_reason}', '{status_reason_note}'
            ], [
                $parcel['sender_name'], $parcel['recipient_name'], $parcel['tracking_number'],
                $parcel['courier_name'] ?: 'لم يحدد بعد', $parcel['courier_phone'] ?: '', 
                number_format($parcel['cod_amount'], 2), $parcel['agent_name'] ?: 'الوكيل',
                $parcel['recipient_phone'], $parcel['status_reason'] ?: '', $parcel['status_reason_note'] ?: ''
            ], $template);
            
            // تنظيف رقم الهاتف
            $clean_phone = preg_replace('/[^0-9]/', '', $phone);
            if (substr($clean_phone, 0, 1) === '0') {
                $clean_phone = '2' . $clean_phone;
            } elseif (substr($clean_phone, 0, 2) !== '20') {
                $clean_phone = '20' . $clean_phone;
            }
            
            // إنشاء رابط WhatsApp
            $whatsapp_link = "https://wa.me/" . $clean_phone . "?text=" . urlencode($message);
            
            // حفظ في قاعدة البيانات
            $insert_query = "INSERT INTO free_notification_logs (parcel_id, phone_number, recipient_type, status_id, message_content, whatsapp_link) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ississ", $parcel_id, $phone, $type, $status_id, $message, $whatsapp_link);
            if ($insert_stmt->execute()) {
                $count++;
            }
        }
        
        return $count > 0;
        
    } catch (Exception $e) {
        error_log("Free notification error: " . $e->getMessage());
        return false;
    }
}

// -----------------------------------------------------------
// منطق معالجة طلبات AJAX (تغيير الحالة، الدفع، الحذف الفردي، الدفع الجزئي)
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'حدث خطأ غير معروف.'];

    $action = $_POST['action'];
    $parcel_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($action === 'change_status' && $parcel_id > 0) {
        $new_status_input = $_POST['new_status'] ?? '';
        $status_reason_id = isset($_POST['status_reason_id']) ? intval($_POST['status_reason_id']) : null;
        $status_reason_note = isset($_POST['status_reason_note']) ? trim($_POST['status_reason_note']) : '';
        
        // معالجة خاصة للدفع الجزئي
        if ($new_status_input === 'partial_payment') {
            $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
            $returned_items = isset($_POST['returned_items']) ? intval($_POST['returned_items']) : 0;
            $payment_note = isset($_POST['payment_note']) ? trim($_POST['payment_note']) : '';
            
            if ($partial_amount <= 0) {
                $response['message'] = 'يجب إدخال مبلغ صحيح للدفع الجزئي.';
                echo json_encode($response);
                exit;
            }
            
            // استدعاء منطق الدفع الجزئي الموجود
            $_POST['action'] = 'partial_payment';
            $_POST['partial_amount'] = $partial_amount;
            $_POST['note'] = $payment_note;
            
            // إعادة المعالجة مع action مختلف
            $action = 'partial_payment';
        } else {
            $new_status = intval($new_status_input);
            
            // التحقق من إرسال السبب للحالات التي تتطلب ذلك (7=تأجيل، 8=رفض)
            if (($new_status == 7 || $new_status == 8) && empty($status_reason_id)) {
                $response['message'] = 'يجب اختيار سبب لهذه الحالة.';
                echo json_encode($response);
                exit;
            }
            
            // معالجة خاصة للمرتجعات (حالة 9 أو 10)
            if ($new_status == 9 || $new_status == 10) {
                include_once 'partial_payment_calculator.php';
                
                // جلب بيانات الشحنة لحساب قيمة المرتجع
                $parcel_query = "SELECT cod_amount, paid_amount, shipping_fees, shipping_payer FROM parcels WHERE id = ?";
                $parcel_stmt = $conn->prepare($parcel_query);
                $parcel_stmt->bind_param("i", $parcel_id);
                $parcel_stmt->execute();
                $parcel_result = $parcel_stmt->get_result();
                
                if ($parcel_result->num_rows > 0) {
                    $parcel_data = $parcel_result->fetch_assoc();
                    
                    // حساب قيمة المرتجع باستخدام نفس منطق الدفع الجزئي
                    $calculation = PartialPaymentCalculator::calculateCustomerDue(
                        $parcel_data['cod_amount'],
                        $parcel_data['paid_amount'] ?: 0,
                        $parcel_data['shipping_fees'] ?: 0,
                        $parcel_data['shipping_payer'] ?: 'sender'
                    );
                    
                    // تحديث بيانات المرتجع
                    $update_return_query = "UPDATE parcels SET return_value = ?, return_date = NOW(), return_reason = ? WHERE id = ?";
                    $update_return_stmt = $conn->prepare($update_return_query);
                    $return_reason = $status_reason_note ?: ($new_status == 9 ? 'لم يتم تسليم الشحنة' : 'تم تسليم المرتجع للعميل');
                    $update_return_stmt->bind_param("dsi", $calculation['customer_due'], $return_reason, $parcel_id);
                    $update_return_stmt->execute();
                }
            }
        }
    }
    
        if ($action === 'change_status' && $parcel_id > 0 && isset($new_status)) {
        // استخدام include مباشر للمعالج لتجنب مشاكل cURL
        
        // تحضير البيانات في $_POST للمعالج
        $_POST['action'] = 'change_status';
        $_POST['id'] = $parcel_id;
        $_POST['new_status'] = $new_status;
        $_POST['remarks'] = $status_reason_note ?: '';
        $_POST['status_reason_id'] = $status_reason_id ?: '';
        $_POST['status_reason_note'] = $status_reason_note ?: '';
        
        // بدء output buffering لالتقاط نتيجة المعالج
        ob_start();
        
        // التأكد من أن الاتصال لا يزال نشطاً
        if (!$conn || $conn->ping() === false) {
            include 'db_connect.php';
        }
        
        // استدعاء المعالج المباشر
        include 'FINAL_DIRECT_STATUS_UPDATE.php';
        
        // الحصول على النتيجة
        $result_json = ob_get_clean();
        
        // تحليل النتيجة
        $result_data = json_decode($result_json, true);
        
        if ($result_data && $result_data['status'] === 'success') {
            $response = ['status' => 'success', 'message' => $result_data['message']];
            
            // إضافة إشعارات WhatsApp
            try {
                $notification_result = createFreeNotification($conn, $parcel_id, $new_status);
                if ($notification_result) {
                    $response['notification_status'] = 'تم إنشاء روابط الإشعارات المجانية';
                    $response['notification_type'] = 'free';
                }
            } catch (Exception $notification_error) {
                error_log("WhatsApp Notification Error: " . $notification_error->getMessage());
            }
        } else {
            $response = ['status' => 'error', 'message' => $result_data['message'] ?? 'فشل في تحديث الحالة'];
        }
    }

    if ($action === 'mark_as_paid' && $parcel_id > 0) {
        // جلب القيم لحساب إجمالي المطلوب وتحديد الجزء الذي سيتم تسجيله كتحصيل
        $parcel_stmt = $conn->prepare("SELECT cod_amount, paid_amount, shipping_fees, shipping_payer, courier_id FROM parcels WHERE id = ?");
        $parcel_stmt->bind_param("i", $parcel_id);
        $parcel_stmt->execute();
        $parcel_res = $parcel_stmt->get_result();
        $parcel_row = $parcel_res->fetch_assoc();

        $cod_amount = floatval($parcel_row['cod_amount'] ?? 0);
        $paid_amount_current = floatval($parcel_row['paid_amount'] ?? 0);
        $shipping_fees = floatval($parcel_row['shipping_fees'] ?? 0);
        $shipping_payer = $parcel_row['shipping_payer'] ?? 'sender';
        $courier_id_for_collection = isset($parcel_row['courier_id']) ? intval($parcel_row['courier_id']) : null;

        $total_to_collect_from_recipient = $cod_amount + (($shipping_payer === 'recipient') ? $shipping_fees : 0);
        $amount_to_collect_now = max(0, $total_to_collect_from_recipient - $paid_amount_current);

        $conn->begin_transaction();
        try {
            // إن وُجد مبلغ متبقٍ، سجّل تحصيله
            if ($amount_to_collect_now > 0) {
                $null_note = null;
                if ($courier_id_for_collection > 0) {
                    $ins_stmt = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, method, note) VALUES (?, ?, ?, 'cash', ?)");
                    $ins_stmt->bind_param("iids", $parcel_id, $courier_id_for_collection, $amount_to_collect_now, $null_note);
                } else {
                    $ins_stmt = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, method, note) VALUES (?, NULL, ?, 'cash', ?)");
                    $ins_stmt->bind_param("ids", $parcel_id, $amount_to_collect_now, $null_note);
                }
                $ins_stmt->execute();
            }

            // تحديث حالة الشحنة إلى "تم الدفع بنجاح" وتثبيت paid_amount على الإجمالي المطلوب
            $upd_stmt = $conn->prepare("UPDATE parcels SET is_paid = 1, paid_at = NOW(), payment_status = 'paid', status = 5, paid_amount = ? WHERE id = ?");
            $upd_stmt->bind_param("di", $total_to_collect_from_recipient, $parcel_id);
            $upd_stmt->execute();

            // إضافة سجل تتبع جديد لحالة "تم الدفع بنجاح"
            $sql_track = "INSERT INTO parcel_tracks (parcel_id, status) VALUES (?, 5)";
            $stmt_track = $conn->prepare($sql_track);
            $stmt_track->bind_param("i", $parcel_id);
            $stmt_track->execute();

            $conn->commit();
            $response = ['status' => 'success', 'message' => 'تم دفع الشحنة بالكامل بنجاح وتسجيل التحصيل وتحديث الحالة.'];
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $response['message'] = 'فشل تحديث حالة الدفع: ' . $e->getMessage();
        }
    }

    if (($action === 'partial_payment' || $action === 'partial_pay_parcel') && $parcel_id > 0) {
        $collected_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : (isset($_POST['amount']) ? floatval($_POST['amount']) : 0);
        if ($parcel_id <= 0 || $collected_amount <= 0) {
            $response = ['status' => 'error', 'message' => 'بيانات غير صالحة'];
        } else {
            try {
                // جلب المندوب المرتبط بالشحنة إن وجد لتمييز التحصيل
                $courier_id_for_collection = null;
                if ($cstmt = $conn->prepare('SELECT courier_id FROM parcels WHERE id = ?')) {
                    $cstmt->bind_param('i', $parcel_id);
                    $cstmt->execute();
                    $cres = $cstmt->get_result()->fetch_assoc();
                    if ($cres && isset($cres['courier_id'])) {
                        $courier_id_for_collection = (int)$cres['courier_id'] ?: null;
                    }
                    $cstmt->close();
                }

                $adminName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'admin';
                $result = PaymentService::applyPartialPayment(
                    $conn,
                    $parcel_id,
                    $collected_amount,
                    'admin',
                    null,
                    $courier_id_for_collection,
                    $adminName
                );

                $response = [
                    'status' => 'success',
                    'message' => 'تم تسجيل الدفع الجزئي بنجاح',
                    'data' => $result
                ];
            } catch (Throwable $e) {
                $response = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
    }
    
    // معالج جلب بيانات الدفع للشحنة
    if ($action === 'get_parcel_payment_data' && $parcel_id > 0) {
        $query = "SELECT cod_amount, paid_amount, shipping_fees, shipping_payer, tracking_number FROM parcels WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $response = [
                'status' => 'success',
                'data' => [
                    'cod_amount' => floatval($data['cod_amount'] ?? 0),
                    'paid_amount' => floatval($data['paid_amount'] ?? 0),
                    'shipping_fees' => floatval($data['shipping_fees'] ?? 0),
                    'shipping_payer' => $data['shipping_payer'] ?? 'sender',
                    'tracking_number' => $data['tracking_number']
                ]
            ];
        } else {
            $response = ['status' => 'error', 'message' => 'الشحنة غير موجودة'];
        }
    }
    
    if ($action === 'delete_parcel' && $parcel_id > 0) {
        $conn->begin_transaction();
        try {
            // الحصول على معلومات الشحنة قبل الحذف
            $parcel_info = $conn->prepare("SELECT tracking_number FROM parcels WHERE id = ?");
            $parcel_info->bind_param("i", $parcel_id);
            $parcel_info->execute();
            $parcel_result = $parcel_info->get_result();
            
            if ($parcel_result->num_rows === 0) {
                throw new Exception('الشحنة غير موجودة');
            }
            
            $parcel_data = $parcel_result->fetch_assoc();
            $tracking_number = $parcel_data['tracking_number'];
            
            // حذف البيانات المرتبطة بالترتيب الصحيح
            
            // 1. حذف ملاحظات الشحنة
            $stmt1 = $conn->prepare("DELETE FROM parcels_notes WHERE parcel_id = ?");
            $stmt1->bind_param("i", $parcel_id);
            $stmt1->execute();
            
            // 2. حذف سجلات التتبع
            $stmt2 = $conn->prepare("DELETE FROM parcel_tracks WHERE parcel_id = ?");
            $stmt2->bind_param("i", $parcel_id);
            $stmt2->execute();
            
            // 3. حذف سجلات التحصيل (إن وجدت)
            $stmt3 = $conn->prepare("DELETE FROM parcel_collections WHERE parcel_id = ?");
            $stmt3->bind_param("i", $parcel_id);
            $stmt3->execute();
            
            // 4. حذف سجلات الدفع (إن وجدت)
            $stmt4 = $conn->prepare("DELETE FROM customer_payments WHERE parcel_id = ?");
            $stmt4->bind_param("i", $parcel_id);
            $stmt4->execute();
            
            // 5. حذف الإشعارات (إن وجدت)
            $stmt5 = $conn->prepare("DELETE FROM free_notification_logs WHERE parcel_id = ?");
            $stmt5->bind_param("i", $parcel_id);
            $stmt5->execute();
            
            // 6. أخيراً حذف الشحنة نفسها
            $stmt6 = $conn->prepare("DELETE FROM parcels WHERE id = ?");
            $stmt6->bind_param("i", $parcel_id);
            $stmt6->execute();
            
            if ($stmt6->affected_rows === 0) {
                throw new Exception('فشل في حذف الشحنة');
            }
            
            $conn->commit();
            $response = ['status' => 'success', 'message' => "تم حذف الشحنة #$tracking_number وجميع البيانات المرتبطة بها بنجاح"];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['status' => 'error', 'message' => 'فشل حذف الشحنة: ' . $e->getMessage()];
        }
    }

    if ($action === 'get_status_reasons') {
        try {
            $status_id = intval($_POST['status_id']);
            $sql = "SELECT id, reason_code, reason_text FROM parcel_status_reasons WHERE status_id = ? AND is_active = 1 ORDER BY sort_order ASC, reason_text ASC";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
            }
            $stmt->bind_param("i", $status_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $reasons = $result->fetch_all(MYSQLI_ASSOC);
            $response = ['status' => 'success', 'data' => $reasons];
        } catch (Exception $e) {
            $response = ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    if ($action === 'get_parcel_payment_data') {
        try {
            $parcel_id = intval($_POST['parcel_id']);
            $sql = "SELECT cod_amount, paid_amount, shipping_fees, shipping_payer FROM parcels WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
            }
            $stmt->bind_param("i", $parcel_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            if ($data) {
                $response = ['status' => 'success', 'data' => $data];
            } else {
                $response = ['status' => 'error', 'message' => 'لا توجد بيانات لهذه الشحنة'];
            }
        } catch (Exception $e) {
            $response = ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    echo json_encode($response);
    exit;
}

// -----------------------------------------------------------
// جلب البيانات الأساسية للفلترة والعرض
// -----------------------------------------------------------
$governorates_result = $conn->query("SELECT id, name FROM governorates ORDER BY name ASC");
$governorates = $governorates_result->fetch_all(MYSQLI_ASSOC);

$status_result = $conn->query("SELECT id, name_ar FROM parcel_status ORDER BY id ASC");
$parcel_statuses = $status_result->fetch_all(MYSQLI_ASSOC);

$agents_result = $conn->query("SELECT id, company_name FROM agents WHERE status = 1 ORDER BY company_name ASC");
$agents = $agents_result->fetch_all(MYSQLI_ASSOC);

$couriers_result = $conn->query("SELECT id, name FROM couriers WHERE status = 1 ORDER BY name ASC");
$couriers = $couriers_result->fetch_all(MYSQLI_ASSOC);

// -----------------------------------------------------------
// بناء استعلام البحث والفلترة الرئيسي
// -----------------------------------------------------------
// تحقق إذا كان الطلب AJAX للفلترة
$is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$where = "WHERE 1=1";
$params = [];
$param_types = "";

// فلترة حسب رقم التتبع
if (!empty($_GET['tracking_number'])) {
    $where .= " AND p.tracking_number LIKE ?";
    $params[] = '%' . $_GET['tracking_number'] . '%';
    $param_types .= "s";
}

// فلترة حسب الحالة
if (!empty($_GET['status']) && $_GET['status'] != -1) {
    $where .= " AND p.status = ?";
    $params[] = intval($_GET['status']);
    $param_types .= "i";
}

// فلترة حسب المحافظة (المستلم)
if (!empty($_GET['governorate_id'])) {
    $where .= " AND p.recipient_governorate_id = ?";
    $params[] = intval($_GET['governorate_id']);
    $param_types .= "i";
}

// فلترة حسب المنطقة (المستلم)
if (!empty($_GET['area'])) {
    $where .= " AND ar_rec.name LIKE ?";
    $params[] = '%' . $_GET['area'] . '%';
    $param_types .= "s";
}

// فلترة حسب الوكيل
if (!empty($_GET['agent_id'])) {
    $where .= " AND p.agent_id = ?";
    $params[] = intval($_GET['agent_id']);
    $param_types .= "i";
}

// فلترة حسب المندوب
if (!empty($_GET['courier_id'])) {
    $where .= " AND p.courier_id = ?";
    $params[] = intval($_GET['courier_id']);
    $param_types .= "i";
}

// فلترة حسب نوع الشحنة
if (!empty($_GET['shipment_direction'])) {
    if ($_GET['shipment_direction'] == 'to_agent') {
        // إرسال إلى وكيل: الشحنات التي لها وكيل (مستلم)
        $where .= " AND p.agent_id IS NOT NULL";
    } elseif ($_GET['shipment_direction'] == 'from_agent') {
        // استلام من وكيل: الشحنات التي ليس لها وكيل (شحنات عادية)
        $where .= " AND (p.agent_id IS NULL OR p.agent_id = 0)";
    }
}

// فلترة حسب البحث السريع
if (!empty($_GET['search'])) {
    $where .= " AND (p.tracking_number LIKE ? OR p.recipient_name LIKE ? OR p.sender_name LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $param_types .= "sss";
}

// فلترة حسب الراسل (الاسم أو الهاتف)
if (!empty($_GET['sender_search'])) {
    $where .= " AND (p.sender_name LIKE ? OR p.sender_phone LIKE ?)";
    $params[] = '%' . $_GET['sender_search'] . '%';
    $params[] = '%' . $_GET['sender_search'] . '%';
    $param_types .= "ss";
}

// فلترة حسب المستلم (الاسم أو الهاتف)
if (!empty($_GET['recipient_search'])) {
    $where .= " AND (p.recipient_name LIKE ? OR p.recipient_phone LIKE ?)";
    $params[] = '%' . $_GET['recipient_search'] . '%';
    $params[] = '%' . $_GET['recipient_search'] . '%';
    $param_types .= "ss";
}

// فلترة حسب التاريخ
if (!empty($_GET['start_date'])) {
    $where .= " AND DATE(p.date_created) >= ?";
    $params[] = $_GET['start_date'];
    $param_types .= "s";
}
if (!empty($_GET['end_date'])) {
    $where .= " AND DATE(p.date_created) <= ?";
    $params[] = $_GET['end_date'];
    $param_types .= "s";
}

// فلترة إضافية للمستخدمين غير المديرين
if (isset($_SESSION['login_type']) && $_SESSION['login_type'] != 1) { 
    $where .= " AND (p.agent_id = ? OR p.courier_id = ?)";
    $params[] = $_SESSION['login_id'];
    $params[] = $_SESSION['login_id'];
    $param_types .= "ii";
}

$sql = "SELECT p.*,
               c.name as courier_name,
               ar_rec.name as recipient_area_name,
               g_rec.name as recipient_gov_name,
               a.company_name as agent_name,
               g_send.name as sender_gov_name,
               ar_send.name as sender_area_name,
               p.shipment_direction as shipment_direction,
               COALESCE(p.delivery_agent_fee, 0) as delivery_agent_fee,
               COALESCE(p.agent_share, 0) as agent_share,
               COALESCE(p.customer_due_amount, 0) as customer_due_amount,
               ps.name_ar as status_name
        FROM parcels p
        LEFT JOIN couriers c ON p.courier_id = c.id
        LEFT JOIN areas ar_rec ON p.recipient_area_id = ar_rec.id
        LEFT JOIN governorates g_rec ON p.recipient_governorate_id = g_rec.id
        LEFT JOIN agents a ON p.agent_id = a.id
        LEFT JOIN governorates g_send ON p.sender_governorate_id = g_send.id
        LEFT JOIN areas ar_send ON p.sender_area_id = ar_send.id
        LEFT JOIN parcel_status ps ON p.status = ps.id
        $where
        ORDER BY p.date_created DESC";



$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("خطأ في إعداد الاستعلام: " . $conn->error . "<br>SQL: " . $sql);
}

if ($param_types && !empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

if (!$stmt->execute()) {
    die("خطأ في تنفيذ الاستعلام: " . $stmt->error . "<br>SQL: " . $sql);
}

$qry = $stmt->get_result();
if (!$qry) {
    die("خطأ في الحصول على النتائج: " . $stmt->error . "<br>SQL: " . $sql);
}

// التحقق من عدد النتائج
$num_rows = $qry->num_rows;





// تعريف مصفوفة حالات الشحنات (للعرض في الجدول)
$status_arr = array(
    1  => 'قيد التنفيذ',
    2  => 'تم تسليمها للمندوب',
    3  => 'جاري التوصيل',
    4  => 'تم التسليم بنجاح',
    5  => 'تم الدفع بنجاح',
    6  => 'تم الدفع جزئي',
    7  => 'تم تأجيل الطلب',
    8  => 'تم الرفض',
    9  => 'المرتجعات',
    10 => 'مرتجع للمخزن',
    11 => 'مرتجع للفرع',
    12 => 'مرتجع للعميل'
);

// إذا كان الطلب AJAX، قم فقط بإرجاع صفوف الجدول
if ($is_ajax_request) {
    ob_start(); // Start output buffering
    $i = 1;
    $row_count = 0;
    while ($row = $qry->fetch_assoc()):
        $row_count++;
        // Calculate total to collect for display in list
        $cod_amount = $row['cod_amount'] ?? 0;
        $shipping_fees = $row['shipping_fees'] ?? 0;
        $shipping_payer = $row['shipping_payer'] ?? 'sender';
        $shipment_direction = $row['shipment_direction'] ?? 'to_agent';
        $delivery_agent_fee = $row['delivery_agent_fee'] ?? 0;
        
        // إجمالي المطلوب من المستلم عند الباب: لا يُخصم منه عمولات داخلية
        $total_to_collect_display = $cod_amount;
            if ($shipping_payer == 'recipient') {
            $total_to_collect_display += $shipping_fees;
        }

        // الصافي المعروض حسب اتجاه الشحنة
        if ($shipment_direction == 'to_agent') {
            // العميل (الراسل) شحن عبرنا إلى وكيل
            if ($shipping_payer == 'sender') {
                $net_for_display = $cod_amount - $shipping_fees; // صافي العميل بعد خصم الشحن
            } else {
                $net_for_display = $cod_amount; // الشحن على المستلم، صافي العميل هو كامل COD
            }
        } else {
            // استلام من وكيل: صافي الوكيل بعد خصم عمولة المندوب
            $net_for_display = $cod_amount - $delivery_agent_fee;
        }
    ?>
                                    <tr>
                                    <td class="text-center no-print">
                                        <input type="checkbox" class="form-check-input shipment-checkbox" value="<?php echo $row['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="tracking-info">
                                            <strong class="text-primary"><?php echo htmlspecialchars($row['tracking_number']); ?></strong>
                                            <div class="text-muted small"><?php echo date('Y-m-d', strtotime($row['date_created'])); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sender-info">
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($row['sender_name']); ?>
                                                <?php if (isset($row['client_id_fk']) && $row['client_id_fk']): ?>
                                                    <a href="customer_profile.php?id=<?php echo $row['client_id_fk']; ?>" 
                                                       class="btn btn-sm btn-outline-primary ms-2" 
                                                       title="عرض ملف العميل" 
                                                       target="_blank">
                                                        <i class="fas fa-user-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['sender_phone']); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['sender_gov_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="recipient-info">
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['recipient_name']); ?></div>
                                            <div class="text-muted small">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['recipient_phone']); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['recipient_area_name'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($row['recipient_gov_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $shipment_direction = $row['shipment_direction'] ?? 'to_agent';
                                        if ($shipment_direction == 'to_agent') {
                                            echo '<span class="badge bg-primary">إرسال إلى وكيل</span>';
                                            } else {
                                            echo '<span class="badge bg-info">استلام من وكيل</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($row['courier_name'])): ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($row['courier_name']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">لا يوجد مندوب</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $status_val = isset($row['status']) ? intval($row['status']) : 1;
                                        $status_name = $status_arr[$status_val] ?? "غير معروف";
                                        
                                        // تحديد لون badge بناءً على الحالة
                                        $badge_class = '';
                                        switch($status_val) {
                                            case 1: $badge_class = 'bg-primary'; break;
                                            case 2: $badge_class = 'bg-info'; break;
                                            case 3: $badge_class = 'bg-warning'; break;
                                            case 4: $badge_class = 'bg-success'; break;
                                            case 5: $badge_class = 'bg-success'; break;
                                            case 6: $badge_class = 'bg-warning'; break;
                                            case 7: $badge_class = 'bg-secondary'; break;
                                            case 8: $badge_class = 'bg-danger'; break;
                                            case 9: $badge_class = 'bg-dark'; break;
                                            case 10: $badge_class = 'bg-dark'; break;
                                            case 11: $badge_class = 'bg-dark'; break;
                                            case 12: $badge_class = 'bg-dark'; break;
                                            default: $badge_class = 'bg-secondary'; break;
                                        }
                                        
                                        echo "<span class='badge ".$badge_class."'>".$status_name."</span>";
                                        ?>
                                    </td>
                                    <td class="text-center">
                                                <strong class="text-primary"><?php echo number_format($row['cod_amount'] ?? 0, 2); ?> جنيه</strong>
                                    </td>
                                    <td class="text-center">
                                        <strong class="text-info"><?php echo number_format($net_for_display, 2); ?> جنيه</strong>
                                    </td>
                                    <td class="text-center">
                                                <strong class="text-success"><?php echo number_format($total_to_collect_display, 2); ?> جنيه</strong>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary view-details-btn" data-id="<?php echo $row['id']; ?>" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                            <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a href="index.php?page=new_parcel&id=<?php echo $row['id'] ?>" class="dropdown-item">
                                                        <i class="fas fa-edit text-primary"></i> تعديل الشحنة
                                                    </a>
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item change_status_btn" data-id="<?php echo $row['id'] ?>">
                                                        <i class="fas fa-sync-alt text-warning"></i> تغيير الحالة
                                                    </button>
                                                </li>

                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button type="button" class="dropdown-item delete_parcel_btn text-danger" data-id="<?php echo $row['id'] ?>">
                                                        <i class="fas fa-trash"></i> حذف
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
    <?php endwhile; 
    
    // إذا لم توجد نتائج، أظهر رسالة
    if ($row_count === 0) {
        echo '<tr><td colspan="10" class="text-center py-4"><i class="fas fa-search"></i> لا توجد شحنات مطابقة لمعايير البحث</td></tr>';
    }
    
    echo ob_get_clean(); // End output buffering and echo content
    exit; // Exit to prevent rendering the full HTML below
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قائمة الشحنات</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; direction: rtl; }
        .page-title { font-weight: bold; font-size: 2rem; color: #1976d2; margin-bottom: 20px; letter-spacing: 1px; }
        .card-header { background-color: #fff; border-bottom: 1px solid #dee2e6; padding: 1.25rem; }
        .card-outline-primary { border-top: 3px solid #1976d2; }
        .filter-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .filter-box .form-label { font-weight: 500; color: #495057; }
        .btn-action { margin-left: 5px; }
        .status-badge { font-weight: 600; padding: 0.5em 0.8em; border-radius: 20px; }
        table thead th { text-align: right; }
        table tbody td { vertical-align: middle !important; }
        .modal-body-scroll { max-height: 70vh; overflow-y: auto; }
        .btn-group-sm > .btn, .btn-sm { border-radius: 1.2rem; margin-left: 5px; /* Added spacing */ margin-right: 5px; }
        .form-control, .form-select { border-radius: 0.7rem; }
        /* Added spacing for table cells */
        #parcel_list_table td, #parcel_list_table th { padding: 10px !important; }

        /* ألوان مخصصة للحالات الجديدة */
        .status-badge.bg-status-1 { background-color: #6c757d !important; } /* قيد التنفيذ */
        .status-badge.bg-status-2 { background-color: #ffc107 !important; color: #333 !important; } /* تم تسليمها للمندوب */
        .status-badge.bg-status-3 { background-color: #0d6efd !important; } /* جاري التوصيل */
        .status-badge.bg-status-4 { background-color: #28a745 !important; } /* تم التسليم بنجاح */
        .status-badge.bg-status-5 { background-color: #17a2b8 !important; } /* تم الدفع بنجاح */
        .status-badge.bg-status-6 { background-color: #fd7e14 !important; } /* تم الدفع جزئي */
        .status-badge.bg-status-7 { background-color: #6f42c1 !important; } /* تم تأجيل الطلب */
        .status-badge.bg-status-8 { background-color: #dc3545 !important; } /* تم الرفض */
        .status-badge.bg-status-9 { background-color: #007bff !important; } /* المرتجعات */
        .status-badge.bg-status-10 { background-color: #6610f2 !important; } /* مرتجع للمخزن */
        .status-badge.bg-status-11 { background-color: #20c997 !important; } /* مرتجع للفرع */
        .status-badge.bg-status-12 { background-color: #e83e8c !important; } /* مرتجع للعميل */
        
        /* تحسينات إضافية للجدول */
        .tracking-info {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .sender-info, .recipient-info {
            padding: 8px;
            border-radius: 6px;
            background: rgba(248, 249, 250, 0.5);
        }
        
        .financial-info, .payment-info {
            padding: 8px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05) !important;
            transform: scale(1.01);
            transition: all 0.2s ease;
        }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.375rem;
        }
        
        .dropdown-menu {
            border-radius: 8px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        /* تحسينات إضافية */
        .summary-item {
            padding: 15px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            margin: 5px;
        }
        
        .summary-item h4 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .summary-item p {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .table-primary {
            background-color: #e3f2fd !important;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa !important;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .badge {
            font-size: 0.75rem;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .small {
            font-size: 0.875rem;
        }

        /* تنسيق modal محسن */
        .modal-xl {
            max-width: 90% !important;
        }

        .modal-content {
            border-radius: 15px !important;
            overflow: hidden;
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: none !important;
        }

        .modal-body {
            padding: 0 !important;
        }

        .shadow-lg {
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175) !important;
        }

        /* تحسينات إضافية للـ modal */
        #parcelDetailsModal .modal-dialog {
            margin: 2rem auto;
        }

        #parcelDetailsModal .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media print {
            .modal-header {
                display: none !important;
            }
            .modal-body {
                padding: 0 !important;
            }
        }
        
        /* تحسينات الجدول والتصميم المتجاوب */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        #parcel_list_table {
            min-width: 1200px;
            font-size: 0.9rem;
        }
        
        #parcel_list_table th,
        #parcel_list_table td {
            white-space: nowrap;
            padding: 8px 12px !important;
            vertical-align: middle !important;
        }
        
        #parcel_list_table th {
            background-color: #e3f2fd !important;
            font-weight: 600;
            text-align: center;
            border-bottom: 2px solid #1976d2;
        }
        
        #parcel_list_table td {
            border-bottom: 1px solid #dee2e6;
        }
        
        /* تحسين عرض البيانات في الخلايا */
        .tracking-info,
        .sender-info,
        .recipient-info {
            min-width: 120px;
            max-width: 180px;
            word-wrap: break-word;
            white-space: normal;
            text-align: center;
        }
        
        .tracking-info strong {
            font-size: 0.9rem;
            display: block;
            margin-bottom: 2px;
        }
        
        .sender-info .fw-bold,
        .recipient-info .fw-bold {
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        
        .sender-info .text-muted,
        .recipient-info .text-muted {
            font-size: 0.75rem;
            line-height: 1.2;
        }
        
        /* تحسين الأزرار */
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            margin: 0 1px;
        }
        
        /* تحسين الفلاتر */
        .filter-container {
            background: transparent;
            border: none;
            padding: 15px 0;
            margin-bottom: 0;
        }
        
        .filter-container .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .filter-container .form-control,
        .filter-container .form-select {
            border-radius: 6px;
            border: 1px solid #ced4da;
        }
        
        /* تحسين البطاقات */
        .card {
            border-radius: 8px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.25rem;
        }
        
        /* تحسين الحالات */
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        /* تحسين الإجماليات */
        .table-bordered {
            border: 1px solid #dee2e6;
        }
        
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        /* تحسين الطباعة */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .table-responsive {
                overflow: visible;
            }
            
            #parcel_list_table {
                min-width: auto;
                width: 100%;
            }
        }
        
        /* تحسين layout العام */
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        /* تحسين الشاشات الصغيرة */
        @media (max-width: 768px) {
            .filter-container {
                padding: 10px 0;
            }
            
            .filter-container .col-lg-3,
            .filter-container .col-lg-2 {
                margin-bottom: 15px;
            }
            
            .d-flex.gap-2 {
                gap: 0.5rem !important;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .page-title {
                font-size: 1.25rem;
                text-align: center;
                margin-bottom: 1rem;
            }
            
            .col-md-6:last-child {
                text-align: center;
                margin-top: 1rem;
            }
        }

        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .btn-group-sm .btn {
                padding: 0.2rem 0.4rem;
                font-size: 0.7rem;
            }
            
            .d-flex.gap-2.flex-wrap {
                justify-content: center;
            }
            
            .btn-sm {
                margin: 0.2rem;
            }
        }

        /* تحسين العرض الكامل */
        .w-100 {
            width: 100% !important;
        }

        body {
            overflow-x: auto;
        }
    </style>
</head>
<body>

<!-- Header وأزرار التحكم -->
<div class="w-100 bg-white shadow-sm border-bottom mb-3">
    <div class="container-fluid py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
            <div class="page-title"><i class="fas fa-boxes"></i> قائمة الشحنات المحسنة</div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                        <a class="btn btn-success btn-sm" href="./index.php?page=new_parcel">
                            <i class="fa fa-plus"></i> إضافة شحنة جديدة
                        </a>
                </div>
            </div>
        </div>
                        </div>
                        </div>

<!-- قسم الفلاتر والبحث -->
<div class="w-100 bg-light border-bottom mb-3">
    <div class="container-fluid py-3">
                    <div class="filter-container">
                        <form id="filterForm" class="row g-3" method="GET">
                            <input type="hidden" name="page" value="parcel_list">
                            
                            <!-- الصف الأول من الفلاتر -->
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label for="search_input" class="form-label">بحث سريع</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search_input" name="search" placeholder="رقم التتبع، المرجع أو اسم المستلم" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                    <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </div>

                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label for="tracking_number" class="form-label">رقم الشحنة</label>
                                <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="رقم الشحنة" value="<?php echo htmlspecialchars($_GET['tracking_number'] ?? ''); ?>">
                            </div>

                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label for="sender_search" class="form-label">الراسل (الاسم/الهاتف)</label>
                                <input type="text" class="form-control" id="sender_search" name="sender_search" placeholder="اسم أو هاتف الراسل" value="<?php echo htmlspecialchars($_GET['sender_search'] ?? ''); ?>">
                            </div>

                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label for="recipient_search" class="form-label">المستلم (الاسم/الهاتف)</label>
                                <input type="text" class="form-control" id="recipient_search" name="recipient_search" placeholder="اسم أو هاتف المستلم" value="<?php echo htmlspecialchars($_GET['recipient_search'] ?? ''); ?>">
                            </div>

                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label for="status_select" class="form-label">حالة الشحنة</label>
                                <select class="form-select" id="status_select" name="status">
                                    <option value="-1" <?php echo (!isset($_GET['status']) || $_GET['status'] == -1) ? 'selected' : ''; ?>>جميع الحالات</option>
                                    <?php foreach($status_arr as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == $key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- الصف الثاني من الفلاتر -->
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label for="governorate_filter" class="form-label">المحافظة</label>
                                <select class="form-select" id="governorate_filter" name="governorate_id">
                                    <option value="">كل المحافظات</option>
                                        <?php
                                    $governorates = $conn->query("SELECT * FROM governorates ORDER BY name");
                                    while($gov = $governorates->fetch_assoc()):
                                        $selected = (isset($_GET['governorate_id']) && $_GET['governorate_id'] == $gov['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $gov['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($gov['name']); ?></option>
                                        <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label for="area_input" class="form-label">المنطقة</label>
                                <input type="text" class="form-control" id="area_input" name="area" placeholder="اسم المنطقة" value="<?php echo htmlspecialchars($_GET['area'] ?? ''); ?>">
                            </div>

                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label for="agent_filter" class="form-label">الوكيل</label>
                                <select class="form-select" id="agent_filter" name="agent_id">
                                    <option value="">كل الوكلاء</option>
                                    <?php
                                    $agents = $conn->query("SELECT * FROM agents ORDER BY company_name");
                                    while($agent = $agents->fetch_assoc()):
                                        $selected = (isset($_GET['agent_id']) && $_GET['agent_id'] == $agent['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $agent['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($agent['company_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label for="courier_filter" class="form-label">المندوب</label>
                                <select class="form-select" id="courier_filter" name="courier_id">
                                    <option value="">كل المندوبين</option>
                                    <?php
                                    $couriers = $conn->query("SELECT * FROM couriers ORDER BY name");
                                    while($courier = $couriers->fetch_assoc()):
                                        $selected = (isset($_GET['courier_id']) && $_GET['courier_id'] == $courier['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $courier['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($courier['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label for="shipment_direction_filter" class="form-label">نوع الشحنة</label>
                                <select class="form-select" id="shipment_direction_filter" name="shipment_direction">
                                    <option value="">كل الأنواع</option>
                                    <option value="to_agent" <?php echo (isset($_GET['shipment_direction']) && $_GET['shipment_direction'] == 'to_agent') ? 'selected' : ''; ?>>إرسال إلى وكيل</option>
                                    <option value="from_agent" <?php echo (isset($_GET['shipment_direction']) && $_GET['shipment_direction'] == 'from_agent') ? 'selected' : ''; ?>>استلام من وكيل</option>
                                </select>
                            </div>

                            <div class="col-12 d-flex align-items-end flex-wrap">
                                <button type="submit" class="btn btn-info me-2 mb-2"><i class="fas fa-filter me-2"></i> تطبيق الفلاتر</button>
                                <button type="button" class="btn btn-secondary mb-2" onclick="clearFilters()"><i class="fas fa-times me-2"></i> مسح الفلاتر</button>
                            </div>
                        </form>
                        
                        <hr>
                        
                        <!-- فلترة التاريخ -->
                        <form id="dateFilterForm" class="row g-3" method="GET">
                            <input type="hidden" name="page" value="parcel_list">
                            <!-- نقل جميع الفلاتر الحالية كـ hidden inputs -->
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <input type="hidden" name="tracking_number" value="<?php echo htmlspecialchars($_GET['tracking_number'] ?? ''); ?>">
                            <input type="hidden" name="sender_search" value="<?php echo htmlspecialchars($_GET['sender_search'] ?? ''); ?>">
                            <input type="hidden" name="recipient_search" value="<?php echo htmlspecialchars($_GET['recipient_search'] ?? ''); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($_GET['status'] ?? ''); ?>">
                            <input type="hidden" name="governorate_id" value="<?php echo htmlspecialchars($_GET['governorate_id'] ?? ''); ?>">
                            <input type="hidden" name="area" value="<?php echo htmlspecialchars($_GET['area'] ?? ''); ?>">
                            <input type="hidden" name="agent_id" value="<?php echo htmlspecialchars($_GET['agent_id'] ?? ''); ?>">
                            <input type="hidden" name="courier_id" value="<?php echo htmlspecialchars($_GET['courier_id'] ?? ''); ?>">
                            <input type="hidden" name="shipment_direction" value="<?php echo htmlspecialchars($_GET['shipment_direction'] ?? ''); ?>">

                            <div class="col-lg-4 col-md-6 col-sm-12">
                                <label for="start_date_input" class="form-label">من تاريخ</label>
                                <input type="date" class="form-control" id="start_date_input" name="start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                            </div>

                            <div class="col-lg-4 col-md-6 col-sm-12">
                                <label for="end_date_input" class="form-label">إلى تاريخ</label>
                                <input type="date" class="form-control" id="end_date_input" name="end_date" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-lg-4 col-md-12 d-flex align-items-end">
                                <button type="submit" class="btn btn-info w-100"><i class="fas fa-calendar-alt me-2"></i> تصفية بالتاريخ</button>
                            </div>
                        </form>
        </div>
                    </div>
                    </div>

<!-- قسم الجدول والبيانات -->
<div class="w-100">
    <div class="container-fluid">
                    <div id="parcel_list_container">
                        <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped" id="parcel_list_table">
                            <thead class="table-primary">
                                <tr>
                                    <th class="text-center no-print" width="4%">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th width="15%">رقم الشحنة</th>
                                    <th width="20%">بيانات الراسل</th>
                                    <th width="15%">اسم المستلم</th>
                                    <th width="10%">الحالة</th>
                                    <th width="10%">القيمة (COD)</th>
                                    <th width="10%">المبلغ المدفوع</th>

                                    <th width="10%">التاريخ</th>
                                    <th width="5%" class="no-print">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                while ($row = $qry->fetch_assoc()):
        // Calculate total to collect from recipient at the door (no internal commissions involved)
                                    $cod_amount = $row['cod_amount'] ?? 0;
                                    $shipping_fees = $row['shipping_fees'] ?? 0;
                                    $shipping_payer = $row['shipping_payer'] ?? 'sender';
                                    $delivery_agent_fee = $row['delivery_agent_fee'] ?? 0;
        $shipment_direction = $row['shipment_direction'] ?? 'to_agent';
                                            $total_to_collect_display = $cod_amount;
        if ($shipping_payer == 'recipient') {
            $total_to_collect_display += $shipping_fees;
                                    }
                                    
                                    // Calculate net for display (client if to_agent, agent if from_agent)
                                    if ($shipment_direction == 'to_agent') {
                                        if ($shipping_payer == 'sender') {
                                            $net_for_display = $cod_amount - $shipping_fees;
                                        } else {
                                            $net_for_display = $cod_amount;
                                        }
                                    } else {
                                        // استلام من وكيل: صافي الوكيل بعد خصم عمولة المندوب
                                        $net_for_display = $cod_amount - $delivery_agent_fee;
                                    }
                                ?>
                                <tr>
                                    <td class="text-center no-print">
                                        <input type="checkbox" class="form-check-input shipment-checkbox" value="<?php echo $row['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="tracking-info">
                                            <strong class="text-primary"><?php echo htmlspecialchars($row['tracking_number']); ?></strong>
                                            <div class="text-muted small"><?php echo date('Y-m-d', strtotime($row['date_created'])); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sender-info">
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['sender_name']); ?></div>
                                            <div class="text-muted small">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['sender_phone']); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['sender_gov_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="recipient-info">
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['recipient_name']); ?></div>
                                            <div class="text-muted small">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['recipient_phone']); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['recipient_area_name'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($row['recipient_gov_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $status_val = isset($row['status']) ? intval($row['status']) : 1;
                                        $status_name = $status_arr[$status_val] ?? "غير معروف";
                                        
                                        // تحديد لون badge بناءً على الحالة
                                        $badge_class = '';
                                        switch($status_val) {
                                            case 1: $badge_class = 'bg-primary'; break;
                                            case 2: $badge_class = 'bg-info'; break;
                                            case 3: $badge_class = 'bg-warning'; break;
                                            case 4: $badge_class = 'bg-success'; break;
                                            case 5: $badge_class = 'bg-success'; break;
                                            case 6: $badge_class = 'bg-warning'; break;
                                            case 7: $badge_class = 'bg-secondary'; break;
                                            case 8: $badge_class = 'bg-danger'; break;
                                            case 9: $badge_class = 'bg-dark'; break;
                                            case 10: $badge_class = 'bg-dark'; break;
                                            case 11: $badge_class = 'bg-dark'; break;
                                            case 12: $badge_class = 'bg-dark'; break;
                                            default: $badge_class = 'bg-secondary'; break;
                                        }
                                        
                                        echo "<span class='badge ".$badge_class."'>".$status_name."</span>";
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="text-center">
                                            <strong class="text-primary d-block"><?php echo number_format($row['cod_amount'] ?? 0, 2); ?> جنيه</strong>
                                            <small class="text-muted">قيمة الشحنة</small>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center">
                                        <div class="text-center">
                                            <?php 
                                            // عرض المبلغ المدفوع: استخدم paid_amount أولاً، وإن كان صفرًا واستخدم النظام القديم فاعرض partial_paid
                                            $paid_amount = floatval($row['paid_amount'] ?? 0);
                                            if ($paid_amount <= 0 && isset($row['partial_paid'])) {
                                                $paid_amount = floatval($row['partial_paid']);
                                            }
                                            if ($paid_amount > 0): ?>
                                                <strong class="text-success d-block"><?php echo number_format($paid_amount, 2); ?> جنيه</strong>
                                                <small class="text-muted">محصل</small>
                                            <?php else: ?>
                                                <span class="text-muted d-block">0.00 جنيه</span>
                                                <small class="text-muted">لم يحصل</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>


                                    <td class="text-center">
                                        <?php echo date('Y-m-d', strtotime($row['date_created'])); ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary view-details-btn" data-id="<?php echo $row['id']; ?>" title="عرض التفاصيل">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <button type="button" class="dropdown-item change_status_btn" data-id="<?php echo $row['id'] ?>">
                                                            <i class="fas fa-sync-alt text-warning"></i> تغيير الحالة
                                                        </button>
                                                    </li>

                                                    <li>
                                                        <button type="button" class="dropdown-item view_parcel_options_btn" data-id="<?php echo $row['id'] ?>">
                                                            <i class="fas fa-print text-info"></i> طباعة
                                                        </button>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <button type="button" class="dropdown-item delete_parcel_btn text-danger" data-id="<?php echo $row['id'] ?>">
                                                            <i class="fas fa-trash"></i> حذف
                                                        </button>
                                                    </li>
                                                </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                
                                <?php if ($qry->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-search"></i> لا توجد شحنات مطابقة لمعايير البحث
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
            </div>
        </div>
    </div>
</div>






<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeStatusModalLabel">تغيير حالة الشحنة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="changeStatusForm">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="id" id="parcel_id_status">
                    <input type="hidden" name="parcel_id" id="parcel_id_status_alt">
                    <input type="hidden" name="status_reason_id" id="hidden_status_reason_id">
                    <input type="hidden" name="status_reason_note" id="hidden_status_reason_note">
                    
                    <div class="form-group mb-3">
                        <label for="new_status" class="form-label">الحالة الجديدة</label>
                        <select name="new_status" id="new_status" class="form-select" required>
                            <?php foreach ($parcel_statuses as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['id']); ?>"><?php echo htmlspecialchars($s['name_ar']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- قسم اختيار السبب (يظهر للتأجيل والرفض) -->
                    <div id="status_reason_section" class="d-none">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span id="reason_help_text">هذه الحالة تتطلب تحديد سبب.</span>
                        </div>
                        <div class="form-group mb-3">
                            <label for="status_reason_select_list" class="form-label" id="reason_label_list">اختر السبب:</label>
                            <select id="status_reason_select_list" class="form-select">
                                <option value="">-- اختر السبب --</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="status_reason_note_list" class="form-label">ملاحظة إضافية (اختيارية)</label>
                            <textarea id="status_reason_note_list" class="form-control" rows="2" placeholder="اكتب ملاحظة إضافية حول السبب..."></textarea>
                        </div>
                    </div>
                    
                    <!-- قسم الدفع الجزئي -->
                    <div id="partial_payment_section" class="d-none">
                        <div class="alert alert-warning">
                            <i class="fas fa-dollar-sign"></i>
                            <span>أدخل تفاصيل الدفع الجزئي</span>
                        </div>
                        <div class="form-group mb-3">
                            <label for="partial_amount_input" class="form-label">المبلغ المُحصل من الشحنة</label>
                            <input type="number" step="0.01" id="partial_amount_input" class="form-control" placeholder="أدخل المبلغ الذي تم تحصيله">
                            <div class="form-text text-muted">المبلغ الذي تم تحصيله فعلياً من الشحنة (سيتم خصم رسوم الشحن تلقائياً إذا كانت على المرسل)</div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="returned_items_input" class="form-label">عدد القطع المرتجعة</label>
                            <input type="number" id="returned_items_input" class="form-control" placeholder="أدخل عدد القطع المرتجعة" value="0">
                        </div>
                        <div class="form-group mb-3">
                            <label for="payment_note_input" class="form-label">ملاحظة الدفع</label>
                            <textarea id="payment_note_input" class="form-control" rows="2" placeholder="ملاحظة حول الدفع الجزئي..."></textarea>
                        </div>
                        <div class="text-muted small">
                            <p class="mb-1">المبلغ الإجمالي: <span id="modal_total_amount_change"></span> جنيه</p>
                            <p class="mb-1">المدفوع سابقاً: <span id="modal_paid_amount_change"></span> جنيه</p>
                            <p class="mb-1">المتبقي: <span id="modal_remaining_amount_change"></span> جنيه</p>
                            <p class="mb-1">رسوم الشحن: <span id="modal_shipping_fees"></span> جنيه (على <span id="modal_shipping_payer"></span>)</p>
                        </div>
                        
                        <!-- معاينة حساب المبلغ المستحق -->
                        <div id="customer_due_preview" class="alert alert-info d-none">
                            <h6><i class="fas fa-calculator"></i> حساب المبلغ المستحق للعميل:</h6>
                            <div id="calculation_breakdown"></div>
                            <hr>
                            <strong>المبلغ المستحق للعميل: <span id="final_customer_due" class="text-success"></span> جنيه</strong>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="payParcelOptionsModal" tabindex="-1" aria-labelledby="payParcelOptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payParcelOptionsModalLabel">خيارات الدفع</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p>اختر طريقة الدفع للشحنة:</p>
                <button type="button" class="btn btn-success btn-lg mb-3 w-75" id="fullPaymentBtn"><i class="fas fa-check-circle"></i> دفع كامل</button><br>
                <button type="button" class="btn btn-warning btn-lg w-75" id="partialPaymentBtn"><i class="fas fa-dollar-sign"></i> دفع جزئي</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="partialPaymentInputModal" tabindex="-1" aria-labelledby="partialPaymentInputModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="partialPaymentInputModalLabel">إدخال المبلغ المدفوع جزئياً</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="partialPaymentForm">
                    <input type="hidden" name="action" value="partial_payment">
                    <input type="hidden" name="id" id="partial_parcel_id">
                    <div class="form-group mb-3">
                        <label for="partial_amount" class="form-label">المبلغ المدفوع</label>
                        <input type="number" step="0.01" name="partial_amount" id="partial_amount" class="form-control" placeholder="أدخل المبلغ المدفوع" required>
                    </div>
                    <p class="text-muted">المبلغ الإجمالي للشحنة: <span id="modal_total_cod_amount"></span> جنيه</p>
                    <p class="text-muted">المبلغ المدفوع سابقاً: <span id="modal_already_paid_amount"></span> جنيه</p>
                    <p class="text-muted">المبلغ المتبقي: <span id="modal_remaining_cod_amount"></span> جنيه</p>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> تسجيل الدفع الجزئي</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من حذف هذه الشحنة؟ هذا الإجراء لا يمكن التراجع عنه!</p>
                <input type="hidden" id="delete_parcel_id_confirm">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">حذف</button>
            </div>
        </div>
    </div>
</div>




<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="simple_print_export.js"></script>
<script>
    $(document).ready(function() {
        // تهيئة DataTables
        // Note: DataTables will re-initialize on each AJAX filter, so we need to destroy it first
        let dataTable = $('#parcel_list_table').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Arabic.json"
            },
            "paging": true,
            "ordering": true,
            "info": true,
            "searching": false // إخفاء البحث الافتراضي لأننا نستخدم فلتر مخصص
        });

        // دالة لجلب المناطق بناءً على المحافظة
        $('#governorate_id').on('change', function() {
            const govId = $(this).val();
            const areaSelect = $('#area_id');
            areaSelect.html('<option value="">جارٍ التحميل...</option>');

            if (govId) {
                $.ajax({
                    url: 'new_parcel.php', // يمكن استخدام نفس الصفحة أو إنشاء endpoint جديد
                    method: 'POST',
                    data: { action: 'get_areas_by_gov', gov_id: govId },
                    dataType: 'json',
                    success: function(response) {
                        areaSelect.html('<option value="">كل المناطق</option>');
                        if (response.status === 'success' && response.data.length > 0) {
                            $.each(response.data, function(index, area) {
                                areaSelect.append(`<option value="${area.id}">${area.name}</option>`);
                            });
                        }
                    },
                    error: function() {
                        areaSelect.html('<option value="">حدث خطأ</option>');
                    }
                });
            } else {
                areaSelect.html('<option value="">كل المناطق</option>');
            }
        });

        // AJAX Filtering
        $('#filter_form').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            const formData = $(this).serialize();
            const filterBtn = $(this).find('button[type="submit"]');
            filterBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> فلترة...');

            // Show loading spinner in table body
            $('#parcel_list_table tbody').html(`
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">جارٍ تحميل الشحنات...</p>
                    </td>
                </tr>
            `);

            $.ajax({
                url: 'parcel_list.php?' + formData, // Send filter params as GET for simpler handling
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, // Identify as AJAX request
                success: function(response) {
                    // Destroy existing DataTable instance before updating tbody
                    if (dataTable) {
                        dataTable.destroy();
                    }
                    $('#parcel_list_table tbody').html(response); // Update table body with filtered data
                    // Re-initialize DataTable
                    dataTable = $('#parcel_list_table').DataTable({
                        "language": {
                            "url": "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Arabic.json"
                        },
                        "paging": true,
                        "ordering": true,
                        "info": true,
                        "searching": false
                    });
                },
                error: function() {
                    $('#parcel_list_table tbody').html(`
                        <tr>
                            <td colspan="9" class="text-center text-danger py-5">
                                <i class="fas fa-exclamation-triangle"></i> حدث خطأ أثناء الفلترة. يرجى المحاولة مرة أخرى.
                            </td>
                        </tr>
                    `);
                },
                complete: function() {
                    filterBtn.prop('disabled', false).html('<i class="fa fa-filter"></i> فلترة');
                }
            });
        });


        // فتح modal عرض التفاصيل بملء الشاشة
        $(document).on('click', '.view-details-btn', function() {
            const parcelId = $(this).data('id');
            
            // إنشاء modal عادي مع تصميم محسن
            const parcelModal = `
                <div class="modal fade" id="parcelDetailsModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
                    <div class="modal-dialog modal-xl" role="document" style="max-width: 90%;">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-gradient-primary text-white border-0">
                                <h4 class="modal-title d-flex align-items-center mb-0">
                                    <div class="bg-white rounded-circle p-2 me-3" style="width: 45px; height: 45px;">
                                        <i class="fas fa-package text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">تفاصيل الشحنة</div>
                                        <small class="opacity-75">معلومات شاملة عن الطلب</small>
                                    </div>
                                </h4>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> إغلاق
                                    </button>
                                </div>
                            </div>
                            <div class="modal-body p-0" style="max-height: 80vh; overflow-y: auto;">
                                <div id="parcelDetailsContent">
                <div class="text-center py-5">
                                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                                            <span class="sr-only">Loading...</span>
                    </div>
                                        <h5 class="text-muted">جارٍ تحميل التفاصيل...</h5>
                                        <p class="text-muted mb-0">يرجى الانتظار</p>
                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // إزالة modal سابق إن وجد
            $('#parcelDetailsModal').remove();
            
            // إضافة modal جديد
            $('body').append(parcelModal);
            
            // عرض modal
            $('#parcelDetailsModal').modal('show');
            
            // تحميل المحتوى
            $.ajax({
                url: 'parcel_details.php?id=' + parcelId,
                method: 'GET',
                success: function(response) {
                    $('#parcelDetailsContent').html(response);
                },
                error: function() {
                    $('#parcelDetailsContent').html(`
                        <div class="alert alert-danger text-center m-4">
                            <div class="display-1 text-danger mb-3">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h5>فشل في تحميل تفاصيل الشحنة</h5>
                            <p class="text-muted">يرجى التحقق من الاتصال والمحاولة مرة أخرى</p>
                            <button class="btn btn-primary" onclick="location.reload()">
                                <i class="fas fa-redo me-2"></i>إعادة المحاولة
                            </button>
                        </div>
                    `);
                }
            });
            
            // حفظ ID للطباعة
            window.currentParcelIdForPrint = parcelId;
        });





        // وظيفة طباعة البوليصة الشاملة
        $('#printFullWaybillBtn').on('click', function() {
            if (currentParcelId) {
                const printWindow = window.open('parcel_details.php?id=' + currentParcelId + '&view_type=full', '_blank');
                printWindow.onload = function() {
                    printWindow.print();
                };
            }
        });
        
        // وظيفة طباعة الملصق الجديد
        $('#printLabelBtn').on('click', function() {
            if (currentParcelId) {
                const printWindow = window.open('print_label.php?id=' + currentParcelId, '_blank');
                printWindow.onload = function() {
                    printWindow.print();
                };
            }
        });

        // وظيفة طباعة ملصق QR
        $('#printQrLabelBtn').on('click', function() {
            if (currentParcelId) {
                const printWindow = window.open('parcel_details.php?id=' + currentParcelId + '&view_type=label', '_blank');
                printWindow.onload = function() {
                    printWindow.print();
                };
            }
        });
        
        // وظيفة طباعة الملصق الجديد
        $('#printLabelBtn').on('click', function() {
            if (currentParcelId) {
                const printWindow = window.open('print_label.php?id=' + currentParcelId, '_blank');
                printWindow.onload = function() {
                    printWindow.print();
                };
            }
        });


        // فتح نافذة تغيير الحالة
        $(document).on('click', '.change_status_btn', function() {
            const parcelId = $(this).data('id');
            $('#parcel_id_status').val(parcelId);
            $('#parcel_id_status_alt').val(parcelId);
            
            // إعادة تعيين جميع الأقسام
            $('#status_reason_section').addClass('d-none');
            $('#partial_payment_section').addClass('d-none');
            
            // تنظيف قسم الأسباب
            $('#status_reason_select_list').empty().append('<option value="">-- اختر السبب --</option>');
            $('#status_reason_note_list').val('');
            $('#hidden_status_reason_id').val('');
            $('#hidden_status_reason_note').val('');
            
            // تنظيف قسم الدفع الجزئي
            $('#partial_amount_input').val('');
            $('#returned_items_input').val('0');
            $('#payment_note_input').val('');
            $('#modal_total_amount_change').text('0.00');
            $('#modal_paid_amount_change').text('0.00');
            $('#modal_remaining_amount_change').text('0.00');
            
            // إعادة تعيين اختيار الحالة للقيمة الافتراضية
            $('#new_status').val('');
            
            $('#changeStatusModal').modal('show');
        });

        // معالجة تغيير الحالة لإظهار/إخفاء قسم الأسباب والدفع الجزئي
        $('#new_status').on('change', function() {
            const statusValue = $(this).val();
            const statusReasonSection = $('#status_reason_section');
            const partialPaymentSection = $('#partial_payment_section');
            
            console.log('تم تغيير الحالة إلى:', statusValue);
            
            // إخفاء جميع الأقسام أولاً
            statusReasonSection.addClass('d-none');
            partialPaymentSection.addClass('d-none');
            
            // إظهار قسم الأسباب للتأجيل (7) والرفض (8)
            if (statusValue === '7' || statusValue === '8') {
                console.log('إظهار قسم الأسباب');
                // تحديد نوع الحالة والنصوص المناسبة
                let modalTitle, reasonLabel;
                if (statusValue === '7') {
                    modalTitle = 'تحديد سبب التأجيل';
                    reasonLabel = 'اختر سبب تأجيل الشحنة:';
                } else if (statusValue === '8') {
                    modalTitle = 'تحديد سبب الرفض';
                    reasonLabel = 'اختر سبب رفض الشحنة:';
                }
                
                // تحديث النصوص
                $('#reason_label_list').text(reasonLabel);
                $('#reason_help_text').text('هذه الحالة تتطلب تحديد سبب.');
                
                // تحميل الأسباب من الخادم
                loadStatusReasonsForList(statusValue);
                
                // إظهار القسم
                statusReasonSection.removeClass('d-none');
            } else if (statusValue === '6') {
                console.log('إظهار قسم الدفع الجزئي');
                // إظهار قسم الدفع الجزئي
                partialPaymentSection.removeClass('d-none');
                
                // الحصول على بيانات الشحنة من الزر
                const parcelId = $('#parcel_id_status').val();
                console.log('معرف الشحنة:', parcelId);
                if (parcelId) {
                    loadParcelPaymentData(parcelId);
                }
            } else {
                console.log('إخفاء جميع الأقسام');
                // إخفاء القسم للحالات الأخرى
                statusReasonSection.addClass('d-none');
                $('#hidden_status_reason_id').val('');
                $('#hidden_status_reason_note').val('');
            }
        });

        // دالة تحميل أسباب الحالة للقائمة
        function loadStatusReasonsForList(statusId) {
            const reasonSelect = $('#status_reason_select_list');
            
            // تنظيف كامل للقائمة أولاً
            reasonSelect.empty();
            reasonSelect.append('<option value="">جارٍ التحميل...</option>');
            
            $.ajax({
                url: 'parcel_list.php',
                type: 'POST',
                data: { 
                    action: 'get_status_reasons', 
                    status_id: statusId 
                },
                dataType: 'json',
                success: function(response) {
                    // تنظيف كامل مرة أخرى
                    reasonSelect.empty();
                    reasonSelect.append('<option value="">-- اختر السبب --</option>');
                    
                    if (response.status === 'success' && response.data && response.data.length > 0) {
                        // إضافة خيارات فريدة
                        const addedReasons = new Set();
                        response.data.forEach(reason => {
                            if (reason.id && reason.reason_text && !addedReasons.has(reason.id)) {
                                reasonSelect.append(`<option value="${reason.id}">${reason.reason_text}</option>`);
                                addedReasons.add(reason.id);
                            }
                        });
                    } else {
                        reasonSelect.append('<option value="">لا توجد أسباب متاحة</option>');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('خطأ في تحميل أسباب الحالة:', textStatus, errorThrown);
                    reasonSelect.empty();
                    reasonSelect.append('<option value="">خطأ في التحميل</option>');
                }
            });
        }

        // دالة تحميل بيانات الدفع للشحنة
        function loadParcelPaymentData(parcelId) {
            $.ajax({
                url: 'ajax_status_update.php',
                type: 'POST',
                data: { 
                    action: 'get_parcel_payment_info', 
                    parcel_id: parcelId 
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const info = response.data;
                        const data = {
                          cod_amount: parseFloat(info.cod_amount || info.cod_amount === 0 ? info.cod_amount : (info.parcel && info.parcel.cod_amount) || 0),
                          paid_amount: parseFloat(info.paid_amount || (info.parcel && info.parcel.paid_amount) || 0),
                          shipping_fees: parseFloat(info.shipping_fees || (info.parcel && info.parcel.shipping_fees) || 0),
                          shipping_payer: (info.shipping_payer || (info.parcel && info.parcel.shipping_payer) || 'sender'),
                          deduction_remaining: parseFloat(info.deduction_remaining || 0)
                        };
                        $('#modal_total_amount_change').text(data.cod_amount.toFixed(2));
                        $('#modal_paid_amount_change').text(data.paid_amount.toFixed(2));
                        const totalToCollect = (data.shipping_payer === 'recipient') ? (data.cod_amount + data.shipping_fees) : data.cod_amount;
                        const remainingBalance = Math.max(0, totalToCollect - data.paid_amount);
                        $('#modal_remaining_amount_change').text(remainingBalance.toFixed(2));
                        $('#modal_shipping_fees').text(data.shipping_fees.toFixed(2));
                        $('#modal_shipping_payer').text(data.shipping_payer === 'sender' ? 'المرسل' : 'المستلم');
                        
                        // حفظ البيانات للحسابات اللاحقة
                        window.currentParcelData = data;
                        
                        // تصفير القيم
                        $('#partial_amount_input').val('');
                        $('#returned_items_input').val('0');
                        $('#payment_note_input').val('');
                        
                        // إخفاء معاينة الحساب في البداية
                        $('#customer_due_preview').addClass('d-none');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('خطأ في تحميل بيانات الدفع:', textStatus, errorThrown);
                }
            });
        }

        // حساب المبلغ المستحق عند تغيير مبلغ الدفع الجزئي
        $('#partial_amount_input').on('input', function() {
            const partialAmount = parseFloat($(this).val()) || 0;
            if (partialAmount > 0 && window.currentParcelData) {
                calculateCustomerDue(partialAmount);
            } else {
                $('#customer_due_preview').addClass('d-none');
            }
        });

        // وظيفة حساب المعاينة بناءً على المبلغ المُحصل
        function calculateCustomerDue(collectedAmount) {
            const data = window.currentParcelData;
            const codAmount = parseFloat(data.cod_amount || 0);
            const currentPaid = parseFloat(data.paid_amount || 0);
            const shippingFees = parseFloat(data.shipping_fees || 0);
            const shippingPayer = data.shipping_payer || 'sender';
            const deductionRemaining = parseFloat(data.deduction_remaining || 0); // خصم الشحن/عمولة المندوب المتبقي تطبيقه
            
            let customerDue;
            const newPaidAmount = currentPaid + collectedAmount;
            let breakdown = '';
            
            if (shippingPayer === 'sender') {
                // الشحن على المرسل: نخصم فقط الجزء المتبقي من الخصم الأساسي (الشحن أو عمولة المندوب) من هذه الدفعة
                const deductionAppliedNow = Math.min(collectedAmount, deductionRemaining);
                customerDue = Math.max(0, collectedAmount - deductionAppliedNow);
                
                breakdown = `
                    <div class="alert alert-warning mb-2">الشحن على المرسل - سيتم خصم المتبقي من الخصم (${deductionRemaining.toFixed(2)} ج) من هذه الدفعة</div>
                    <div>• قيمة الشحنة: ${codAmount.toFixed(2)} جنيه</div>
                    <div>• المدفوع حالياً: ${currentPaid.toFixed(2)} جنيه</div>
                    <div>• المبلغ المُحصل: ${collectedAmount.toFixed(2)} جنيه</div>
                    <div>• الخصم المتبقي قبل الدفعة: ${deductionRemaining.toFixed(2)} جنيه</div>
                    <div>• الخصم المطبق الآن: ${deductionAppliedNow.toFixed(2)} جنيه</div>
                    <div>• سيتم إضافة للمُسجل: ${customerDue.toFixed(2)} جنيه</div>
                    <div>• إجمالي المُسجل: ${(currentPaid + customerDue).toFixed(2)} جنيه</div>
                    <div class="mt-2"><strong>المبلغ المستحق للعميل = ${collectedAmount.toFixed(2)} - ${deductionAppliedNow.toFixed(2)} = ${customerDue.toFixed(2)} جنيه</strong></div>
                `;
            } else {
                // الشحن على المستلم: لا يوجد خصم أساسي على الراسل
                customerDue = collectedAmount;
                
                breakdown = `
                    <div class="alert alert-info mb-2">الشحن على المستلم - رسوم الشحن لا تؤثر على المستحق</div>
                    <div>• قيمة الشحنة: ${codAmount.toFixed(2)} جنيه</div>
                    <div>• المدفوع حالياً: ${currentPaid.toFixed(2)} جنيه</div>
                    <div>• المبلغ المُحصل: ${collectedAmount.toFixed(2)} جنيه</div>
                    <div>• سيتم إضافة للمُسجل: ${collectedAmount.toFixed(2)} جنيه</div>
                    <div>• إجمالي المُسجل: ${newPaidAmount.toFixed(2)} جنيه</div>
                    <div class="mt-2"><strong>المبلغ المستحق للعميل = ${customerDue.toFixed(2)} جنيه</strong></div>
                `;
            }
            
            $('#calculation_breakdown').html(breakdown);
            $('#final_customer_due').text(customerDue.toFixed(2));
            $('#customer_due_preview').removeClass('d-none');
        }

        // حفظ السبب المختار في الحقول المخفية عند تغيير الاختيار
        $('#status_reason_select_list').on('change', function() {
            $('#hidden_status_reason_id').val($(this).val());
        });

        $('#status_reason_note_list').on('input', function() {
            $('#hidden_status_reason_note').val($(this).val());
        });

        // معالجة تغيير الحالة عبر AJAX
        $('#changeStatusForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            
            // التحقق من وجود السبب للحالات التي تتطلب ذلك
            const statusValue = $('#new_status').val();
            if ((statusValue === '7' || statusValue === '8') && !$('#hidden_status_reason_id').val()) {
                showTemporaryModal('تنبيه', 'يجب اختيار سبب لهذه الحالة.', 'warning');
                return;
            }
            
            // التحقق من بيانات الدفع الجزئي
            if (statusValue === '6') {
                const partialAmount = parseFloat($('#partial_amount_input').val());
                if (!partialAmount || partialAmount <= 0) {
                    showTemporaryModal('تنبيه', 'يجب إدخال مبلغ صحيح للدفع الجزئي.', 'warning');
                    return;
                }
            }
            
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> حفظ...');

            // إعداد البيانات للإرسال بشكل صريح لضمان إرسال جميع القيم
            let formData = {
                action: 'change_status',
                id: $('#parcel_id_status').val(),
                new_status: statusValue,
                remarks: $('#status_reason_note_list').val() || '',
                status_reason_id: $('#hidden_status_reason_id').val() || '',
                status_reason_note: $('#status_reason_note_list').val() || ''
            };
            
            // إضافة بيانات الدفع الجزئي إذا كانت الحالة هي دفع جزئي
            if (statusValue === '6') {
                formData.partial_amount = $('#partial_amount_input').val();
                formData.returned_items = $('#returned_items_input').val() || '0';
                formData.payment_note = $('#payment_note_input').val() || '';
            }
            
            console.log('البيانات المرسلة:', formData);

            $.ajax({
                url: 'ajax_status_update.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#changeStatusModal').modal('hide');
                        showTemporaryModal('نجاح', response.message, 'success');
                        location.reload();
                    } else {
                        showTemporaryModal('خطأ', response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('خطأ AJAX:', {xhr, status, error, responseText: xhr.responseText});
                    
                    // محاولة احتياطية عبر parcel_list.php
                    console.log('محاولة احتياطية...');
                    $.ajax({
                        url: 'parcel_list.php',
                        method: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#changeStatusModal').modal('hide');
                                showTemporaryModal('نجاح', response.message, 'success');
                                location.reload();
                            } else {
                                showTemporaryModal('خطأ', response.message, 'danger');
                            }
                        },
                        error: function() {
                            showTemporaryModal('خطأ', 'فشل في تحديث الحالة. يرجى المحاولة مرة أخرى أو تحديث الصفحة.', 'danger');
                        }
                    });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> حفظ');
                }
            });
        });

        // فتح نافذة خيارات الدفع (كامل أو جزئي)
        $(document).on('click', '.pay_parcel_options_btn', function() {
            const parcelId = $(this).data('id');
            const codAmount = parseFloat($(this).data('cod'));
            const paidAmount = parseFloat($(this).data('paid'));
            const shippingFees = parseFloat($(this).data('shipping-fees'));
            const shippingPayer = $(this).data('shipping-payer');

            let totalToCollectForModal = codAmount;
            if (shippingPayer === 'recipient') {
                totalToCollectForModal += shippingFees;
            }
            let remainingAmount = totalToCollectForModal - paidAmount;

            $('#partial_parcel_id').val(parcelId);
            $('#modal_total_cod_amount').text(totalToCollectForModal.toFixed(2));
            $('#modal_already_paid_amount').text(paidAmount.toFixed(2));
            $('#modal_remaining_cod_amount').text(remainingAmount.toFixed(2));
            $('#partial_amount').val(remainingAmount.toFixed(2)); // Set default to remaining

            $('#payParcelOptionsModal').modal('show');
        });

        // عند الضغط على زر "دفع كامل"
        $('#fullPaymentBtn').on('click', function() {
            const parcelId = $('#partial_parcel_id').val();
            $('#payParcelOptionsModal').modal('hide'); // إغلاق نافذة الخيارات
            
            const submitBtn = $(this); 

            $.ajax({
                url: 'parcel_list.php',
                method: 'POST',
                data: { action: 'mark_as_paid', id: parcelId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showTemporaryModal('نجاح', response.message, 'success');
                        location.reload();
                    } else {
                        showTemporaryModal('خطأ', response.message, 'danger');
                    }
                },
                error: function() {
                    showTemporaryModal('خطأ', 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.', 'danger');
                }
            });
        });

        // عند الضغط على زر "دفع جزئي"
        $('#partialPaymentBtn').on('click', function() {
            $('#payParcelOptionsModal').modal('hide'); // إغلاق نافذة الخيارات
            $('#partialPaymentInputModal').modal('show'); // فتح نافذة إدخال المبلغ
        });

        // معالجة الدفع الجزئي عبر AJAX
        $('#partialPaymentForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> تسجيل...');

            $.ajax({
                url: 'parcel_list.php',
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#partialPaymentInputModal').modal('hide');
                        showTemporaryModal('نجاح', response.message, 'success');
                        location.reload();
                    } else {
                        showTemporaryModal('خطأ', response.message, 'danger');
                    }
                },
                error: function() {
                    showTemporaryModal('خطأ', 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.', 'danger');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> تسجيل الدفع الجزئي');
                }
            });
        });


        // فتح نافذة تأكيد الحذف
        $(document).on('click', '.delete_parcel_btn', function() {
            const parcelId = $(this).data('id');
            $('#delete_parcel_id_confirm').val(parcelId);
            $('#deleteConfirmModal').modal('show');
        });

        // معالجة الحذف الفردي بعد التأكيد
        $('#confirmDeleteBtn').on('click', function() {
            const parcelId = $('#delete_parcel_id_confirm').val();
            const submitBtn = $(this);
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> حذف...');

            $.ajax({
                url: 'parcel_list.php',
                method: 'POST',
                data: { action: 'delete_parcel', id: parcelId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#deleteConfirmModal').modal('hide');
                        showTemporaryModal('نجاح', response.message, 'success');
                        location.reload();
                    } else {
                        showTemporaryModal('خطأ', response.message, 'danger');
                    }
                },
                error: function() {
                    showTemporaryModal('خطأ', 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.', 'danger');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('حذف');
                }
            });
        });

        // دالة لعرض رسائل مؤقتة في modal (محسنة لتجنب focus loop)
        function showTemporaryModal(title, message, type) {
            // إزالة أي modal مؤقت موجود مسبقاً
            $('#temporaryMessageModal').remove();
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            
            const modalId = 'temporaryMessageModal_' + Date.now(); // معرف فريد
            const modalHtml = `
                <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" data-bs-backdrop="true" data-bs-keyboard="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-${type} text-white">
                                <h5 class="modal-title" id="${modalId}Label">
                                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'times-circle'} me-2"></i>
                                    ${title}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <p class="mb-0">${message}</p>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'}" data-bs-dismiss="modal">
                                    <i class="fas fa-check me-1"></i> موافق
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // إضافة إلى الـ body
            $('body').append(modalHtml);
            
            // إنشاء وعرض الـ modal
            const tempModal = new bootstrap.Modal(document.getElementById(modalId), {
                backdrop: true,
                keyboard: true,
                focus: false // منع التركيز التلقائي لتجنب focus loop
            });
            
            tempModal.show();
            
            // إزالة الـ modal بعد إغلاقه
            $('#' + modalId).on('hidden.bs.modal', function () {
                $(this).remove();
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
            });
            
            // إغلاق تلقائي بعد 3 ثوان للرسائل الناجحة
            if (type === 'success') {
                setTimeout(() => {
                    tempModal.hide();
                }, 3000);
            }
        }

        // تحديد الكل / إلغاء تحديد الكل
        $('#selectAll').on('change', function() {
            $('.parcel-checkbox').prop('checked', $(this).prop('checked'));
        });

        // زر تحديث الحالة المحددة
        $('#updateSelectedStatusBtn').on('click', function() {
            const selectedParcels = $('.parcel-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedParcels.length === 0) {
                showTemporaryModal('تنبيه', 'يرجى تحديد الشحنات المراد تحديث حالتها', 'warning');
                return;
            }
            
            // فتح مودال تحديث الحالة
            $('#parcel_id_status').val(selectedParcels.join(','));
            $('#changeStatusModal').modal('show');
        });

        // أزرار التصدير الجديدة
        $('#exportExcelBtn').on('click', function() {
            exportData('excel');
        });
        
        $('#exportCsvBtn').on('click', function() {
            exportData('csv');
        });
        
        $('#exportPdfBtn').on('click', function() {
            exportData('pdf');
        });
        
        // أزرار الطباعة الجديدة
        $('#printWaybillBtn').on('click', function() {
            printData('waybill');
        });
        
        $('#printLabelBtn').on('click', function() {
            printData('label');
        });
        
        $('#printSummaryBtn').on('click', function() {
            printData('summary');
        });
        
        // دالة التصدير المحسنة
        function exportData(type) {
            const selectedParcels = $('.parcel-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            let exportUrl = 'export_parcels.php?type=' + type;
            
            if (selectedParcels.length > 0) {
                exportUrl += '&selected=' + selectedParcels.join(',');
            }
            
            // إضافة معايير الفلترة الحالية
            const currentUrl = new URL(window.location);
            const filterParams = currentUrl.searchParams;
            if (filterParams.toString()) {
                exportUrl += '&' + filterParams.toString();
            }
            
            // تحميل الملف
            window.open(exportUrl, '_blank');
            showTemporaryModal('نجاح', 'تم بدء عملية التصدير', 'success');
        }
        
        // دالة الطباعة المحسنة
        function printData(type) {
            const selectedParcels = $('.parcel-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (type === 'label') {
                // طباعة ملصق واحد
                if (selectedParcels.length === 1) {
                    const printWindow = window.open('print_label.php?id=' + selectedParcels[0], '_blank');
                    printWindow.onload = function() {
                        printWindow.print();
                    };
                } else {
                    showTemporaryModal('تنبيه', 'يرجى تحديد شحنة واحدة لطباعة الملصق', 'warning');
                }
            } else {
                let printUrl = 'print_parcels.php?type=' + type;
                
                if (selectedParcels.length > 0) {
                    printUrl += '&selected=' + selectedParcels.join(',');
                }
                
                // إضافة معايير الفلترة الحالية
                const currentUrl = new URL(window.location);
                const filterParams = currentUrl.searchParams;
                if (filterParams.toString()) {
                    printUrl += '&' + filterParams.toString();
                }
                
                // فتح نافذة الطباعة
                const printWindow = window.open(printUrl, '_blank');
                printWindow.onload = function() {
                    printWindow.print();
                };
            }
            
            showTemporaryModal('نجاح', 'تم فتح نافذة الطباعة', 'success');
        }

        // تأكيد التصدير
        $('#confirmExportBtn').on('click', function() {
            const exportType = $('#exportType').val();
            const columns = $('input[name="columns[]"]:checked').map(function() {
                return $(this).val();
            }).get();
            const scope = $('#exportScope').val();
            
            if (columns.length === 0) {
                showTemporaryModal('تنبيه', 'يرجى تحديد الأعمدة المراد تصديرها', 'warning');
                return;
            }
            
            // إنشاء رابط التصدير
            let exportUrl = 'export_parcels.php?type=' + exportType + '&columns=' + columns.join(',') + '&scope=' + scope;
            
            // إضافة معايير الفلترة إذا كانت موجودة
            const currentUrl = new URL(window.location);
            const filterParams = currentUrl.searchParams;
            if (filterParams.toString()) {
                exportUrl += '&' + filterParams.toString();
            }
            
            // تحميل الملف
            window.open(exportUrl, '_blank');
            $('#exportModal').modal('hide');
            showTemporaryModal('نجاح', 'تم بدء عملية التصدير', 'success');
        });

        // تأكيد الطباعة
        $('#confirmPrintBtn').on('click', function() {
            const printType = $('#printType').val();
            const scope = $('#printScope').val();
            const options = $('input[name="print_options[]"]:checked').map(function() {
                return $(this).val();
            }).get();
            
            // إنشاء رابط الطباعة
            let printUrl = 'print_parcels.php?type=' + printType + '&scope=' + scope + '&options=' + options.join(',');
            
            // إضافة معايير الفلترة إذا كانت موجودة
            const currentUrl = new URL(window.location);
            const filterParams = currentUrl.searchParams;
            if (filterParams.toString()) {
                printUrl += '&' + filterParams.toString();
            }
            
            // فتح نافذة الطباعة
            const printWindow = window.open(printUrl, '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
            
            $('#printModal').modal('hide');
        });

        // دالة عرض مودال الطباعة
        function showPrintModal() {
            // إنشاء مودال الطباعة ديناميكياً
            var modalHtml = `
                <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="printModalLabel">اختر الأعمدة للطباعة</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    اختر الأعمدة التي تريد طباعتها
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6 class="mb-3">معلومات الشحنة:</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_tracking" value="tracking" checked>
                                            <label class="form-check-label" for="print_col_tracking">رقم الشحنة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_sender_name" value="sender_name" checked>
                                            <label class="form-check-label" for="print_col_sender_name">اسم الراسل</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_sender_phone" value="sender_phone" checked>
                                            <label class="form-check-label" for="print_col_sender_phone">رقم الراسل</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_recipient_name" value="recipient_name" checked>
                                            <label class="form-check-label" for="print_col_recipient_name">اسم المستلم</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_recipient_phone" value="recipient_phone" checked>
                                            <label class="form-check-label" for="print_col_recipient_phone">رقم المستلم</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-3">معلومات التوصيل:</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_governorate" value="governorate" checked>
                                            <label class="form-check-label" for="print_col_governorate">المحافظة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_area" value="area" checked>
                                            <label class="form-check-label" for="print_col_area">المنطقة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_shipment_type" value="shipment_type" checked>
                                            <label class="form-check-label" for="print_col_shipment_type">نوع الشحنة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_courier" value="courier" checked>
                                            <label class="form-check-label" for="print_col_courier">المندوب</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_status" value="status" checked>
                                            <label class="form-check-label" for="print_col_status">حالة الشحنة</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-3">المعلومات المالية:</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_cod" value="cod" checked>
                                            <label class="form-check-label" for="print_col_cod">القيمة (COD)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_commission" value="commission" checked>
                                            <label class="form-check-label" for="print_col_commission">العمولة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_total" value="total" checked>
                                            <label class="form-check-label" for="print_col_total">إجمالي المستحق</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="print_col_date" value="date" checked>
                                            <label class="form-check-label" for="print_col_date">التاريخ</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPrintColumns()">تحديد الكل</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllPrintColumns()">إلغاء تحديد الكل</button>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="button" class="btn btn-success" onclick="printSelectedColumns()">
                                    <i class="fas fa-print"></i> طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // إزالة المودال القديم إذا كان موجوداً
            $('#printModal').remove();
            
            // إضافة المودال الجديد
            $('body').append(modalHtml);
            
            // عرض المودال
            $('#printModal').modal('show');
        }

        // دوال مساعدة للطباعة
        function selectAllPrintColumns() {
            $('#printModal input[type="checkbox"]').prop('checked', true);
        }

        function deselectAllPrintColumns() {
            $('#printModal input[type="checkbox"]').prop('checked', false);
        }

        function printSelectedColumns() {
            var selectedColumns = [];
            $('#printModal input[type="checkbox"]:checked').each(function() {
                var columnValue = $(this).val();
                if (selectedColumns.indexOf(columnValue) === -1) {
                    selectedColumns.push(columnValue);
                }
            });
            
            if (selectedColumns.length === 0) {
                showTemporaryModal('تنبيه', 'الرجاء اختيار عمود واحد على الأقل للطباعة', 'warning');
                return;
            }
            
            // إنشاء جدول جديد بالأعمدة المحددة فقط
            var originalTable = document.getElementById('parcel_list_table');
            var originalHeaderArray = originalTable.querySelectorAll('thead th');
            var originalRows = originalTable.querySelectorAll('tbody tr');
            
            var newTable = document.createElement('table');
            newTable.className = 'table table-bordered';
            
            var newHeaderRow = document.createElement('tr');
            var newDataRows = [];
            
            // تعريف تخطيط الأعمدة للطباعة
            var printColumnMapping = {
                'tracking': 1,        // رقم الشحنة (بعد checkbox)
                'sender_name': 2,     // بيانات الراسل
                'sender_phone': 2,    // بيانات الراسل
                'recipient_name': 3,  // اسم المستلم
                'recipient_phone': 3, // اسم المستلم
                'governorate': 4,     // المحافظة
                'area': 5,           // المنطقة
                'shipment_type': 6,  // نوع الشحنة
                'courier': 7,        // المندوب
                'status': 8,         // حالة الشحنة
                'cod': 9,            // القيمة (COD)
                'commission': 10,    // العمولة
                'total': 11,         // إجمالي المستحق
                'date': 12          // التاريخ
            };
            
            // إضافة خلايا العناوين بناءً على الأعمدة المحددة
            selectedColumns.forEach(function(colType) {
                var newHeaderCell = document.createElement('th');
                var headerContent = '';
                
                // معالجة خاصة لعناوين الراسل والمستلم
                if (colType === 'sender_name') {
                    headerContent = 'اسم الراسل';
                } else if (colType === 'sender_phone') {
                    headerContent = 'رقم الراسل';
                } else if (colType === 'recipient_name') {
                    headerContent = 'اسم المستلم';
                } else if (colType === 'recipient_phone') {
                    headerContent = 'رقم المستلم';
                } else {
                    var headerIndex = printColumnMapping[colType];
                    if (headerIndex >= 0 && headerIndex < originalHeaderArray.length) {
                        headerContent = originalHeaderArray[headerIndex].innerHTML
                            .replace(/<br>/g, ' ')
                            .replace(/<small[^>]*>/g, '')
                            .replace(/<\/small>/g, ' ')
                            .replace(/<strong>/g, '')
                            .replace(/<\/strong>/g, '')
                            .replace(/<div>/g, '')
                            .replace(/<\/div>/g, ' ')
                            .replace(/<span[^>]*>/g, '')
                            .replace(/<\/span>/g, '')
                            .replace(/<i[^>]*>/g, '')
                            .replace(/<\/i>/g, '')
                            .replace(/\s+/g, ' ')
                            .trim();
                    }
                }
                
                newHeaderCell.innerHTML = headerContent;
                newHeaderRow.appendChild(newHeaderCell);
            });
            
            // إضافة صفوف البيانات
            originalRows.forEach(function(row) {
                var newRow = document.createElement('tr');
                var cells = row.querySelectorAll('td');
                
                selectedColumns.forEach(function(colType) {
                    var newCell = document.createElement('td');
                    var cellContent = '';
                    
                    // معالجة خاصة لأعمدة الراسل والمستلم
                    if (colType === 'sender_name' || colType === 'sender_phone') {
                        var senderCell = cells[2]; // خلية الراسل
                        if (senderCell) {
                            var senderDivs = senderCell.querySelectorAll('div');
                            if (senderDivs.length >= 2) {
                                if (colType === 'sender_name') {
                                    cellContent = senderDivs[0].innerText.trim();
                                } else if (colType === 'sender_phone') {
                                    cellContent = senderDivs[1].innerText.trim();
                                }
                            }
                        }
                    } else if (colType === 'recipient_name' || colType === 'recipient_phone') {
                        var recipientCell = cells[3]; // خلية المستلم
                        if (recipientCell) {
                            var recipientDivs = recipientCell.querySelectorAll('div');
                            if (recipientDivs.length >= 2) {
                                if (colType === 'recipient_name') {
                                    cellContent = recipientDivs[0].innerText.trim();
                                } else if (colType === 'recipient_phone') {
                                    cellContent = recipientDivs[1].innerText.trim();
                                }
                            }
                        }
                    } else {
                        var cellIndex = printColumnMapping[colType];
                        if (cellIndex >= 0 && cellIndex < cells.length) {
                            cellContent = cells[cellIndex].innerHTML
                                .replace(/<br>/g, ' ')
                                .replace(/<small[^>]*>/g, '')
                                .replace(/<\/small>/g, ' ')
                                .replace(/<strong>/g, '')
                                .replace(/<\/strong>/g, '')
                                .replace(/<div>/g, '')
                                .replace(/<\/div>/g, ' ')
                                .replace(/<span[^>]*>/g, '')
                                .replace(/<\/span>/g, '')
                                .replace(/<i[^>]*>/g, '')
                                .replace(/<\/i>/g, '')
                                .replace(/\s+/g, ' ')
                                .trim();
                        }
                    }
                    
                    newCell.innerHTML = cellContent;
                    newRow.appendChild(newCell);
                });
                
                newDataRows.push(newRow);
            });
            
            var newThead = document.createElement('thead');
            newThead.appendChild(newHeaderRow);
            newTable.appendChild(newThead);
            
            var newTbody = document.createElement('tbody');
            newDataRows.forEach(function(row) {
                newTbody.appendChild(row);
            });
            newTable.appendChild(newTbody);
            
            // إنشاء نافذة الطباعة
            var printWindow = window.open('', '', 'height=700,width=900');
            printWindow.document.write('<html><head><title>طباعة قائمة الشحنات</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                @page { size: landscape; }
                body { 
                    font-family: 'Tajawal', Arial, sans-serif; 
                    direction: rtl; 
                    padding: 20px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 20px;
                    page-break-inside: auto;
                }
                th, td { 
                    border: 1px solid #dee2e6; 
                    padding: 8px; 
                    text-align: right;
                    font-size: 12px;
                }
                thead th { 
                    background-color: #f8f9fa; 
                    color: #000;
                    font-weight: bold;
                }
                tr { 
                    page-break-inside: avoid; 
                    page-break-after: auto;
                }
                .report-header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .report-header h3 {
                    margin-bottom: 10px;
                    color: #333;
                }
                .report-header h4 {
                    color: #666;
                    margin-bottom: 5px;
                }
                .report-header p {
                    color: #888;
                    font-size: 12px;
                }
                .status-badge {
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                }
                @media print {
                    body { padding: 0; }
                    .no-print { display: none !important; }
                }
            `);
            printWindow.document.write('</style></head><body>');
            
            // إضافة ترويسة التقرير
            printWindow.document.write('<div class="report-header">');
            printWindow.document.write('<h3>تقرير قائمة الشحنات</h3>');
            printWindow.document.write('<p>تاريخ الطباعة: ' + new Date().toLocaleDateString('ar-EG') + '</p>');
            printWindow.document.write('</div>');
            
            // إضافة الجدول
            printWindow.document.write(newTable.outerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            // انتظار تحميل الموارد ثم الطباعة
            printWindow.onload = function() {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            };
            
            // إغلاق المودال
            $('#printModal').modal('hide');
        }


        
        // زر طباعة الملصق من التفاصيل
        $(document).on('click', '.print-label-btn', function() {
            const parcelId = $(this).data('id');
            const printWindow = window.open('print_label.php?id=' + parcelId, '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        });

        // دالة عرض مودال التصدير
        function showExcelExportModal() {
            // إنشاء مودال التصدير ديناميكياً
            var modalHtml = `
                <div class="modal fade" id="excelExportModal" tabindex="-1" aria-labelledby="excelExportModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="excelExportModalLabel">تصدير إلى Excel</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    اختر الأعمدة التي تريد تصديرها
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6 class="mb-3">معلومات الشحنة:</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_tracking" value="tracking" checked>
                                            <label class="form-check-label" for="excel_col_tracking">رقم الشحنة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_sender_name" value="sender_name" checked>
                                            <label class="form-check-label" for="excel_col_sender_name">اسم الراسل</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_sender_phone" value="sender_phone" checked>
                                            <label class="form-check-label" for="excel_col_sender_phone">رقم الراسل</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_recipient_name" value="recipient_name" checked>
                                            <label class="form-check-label" for="excel_col_recipient_name">اسم المستلم</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_recipient_phone" value="recipient_phone" checked>
                                            <label class="form-check-label" for="excel_col_recipient_phone">رقم المستلم</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-3">معلومات التوصيل:</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_governorate" value="governorate" checked>
                                            <label class="form-check-label" for="excel_col_governorate">المحافظة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_area" value="area" checked>
                                            <label class="form-check-label" for="excel_col_area">المنطقة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_shipment_type" value="shipment_type" checked>
                                            <label class="form-check-label" for="excel_col_shipment_type">نوع الشحنة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_courier" value="courier" checked>
                                            <label class="form-check-label" for="excel_col_courier">المندوب</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_status" value="status" checked>
                                            <label class="form-check-label" for="excel_col_status">حالة الشحنة</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-3">المعلومات المالية:</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_cod" value="cod" checked>
                                            <label class="form-check-label" for="excel_col_cod">القيمة (COD)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_commission" value="commission" checked>
                                            <label class="form-check-label" for="excel_col_commission">العمولة</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_total" value="total" checked>
                                            <label class="form-check-label" for="excel_col_total">إجمالي المستحق</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="excel_col_date" value="date" checked>
                                            <label class="form-check-label" for="excel_col_date">التاريخ</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllExcelColumns()">تحديد الكل</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllExcelColumns()">إلغاء تحديد الكل</button>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="button" class="btn btn-success" onclick="exportSelectedColumns()">
                                    <i class="fas fa-file-excel"></i> تصدير Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // إزالة المودال القديم إذا كان موجوداً
            $('#excelExportModal').remove();
            
            // إضافة المودال الجديد
            $('body').append(modalHtml);
            
            // عرض المودال
            $('#excelExportModal').modal('show');
        }

        // دوال مساعدة للتصدير
        function selectAllExcelColumns() {
            $('#excelExportModal input[type="checkbox"]').prop('checked', true);
        }

        function deselectAllExcelColumns() {
            $('#excelExportModal input[type="checkbox"]').prop('checked', false);
        }

        function exportSelectedColumns() {
            var selectedColumns = [];
            $('#excelExportModal input[type="checkbox"]:checked').each(function() {
                var columnValue = $(this).val();
                if (selectedColumns.indexOf(columnValue) === -1) {
                    selectedColumns.push(columnValue);
                }
            });
            
            if (selectedColumns.length === 0) {
                showTemporaryModal('تنبيه', 'الرجاء اختيار عمود واحد على الأقل للتصدير', 'warning');
                return;
            }
            
            // إنشاء جدول جديد بالأعمدة المحددة فقط
            var originalTable = document.getElementById('parcel_list_table');
            var originalHeaderArray = originalTable.querySelectorAll('thead th');
            var originalRows = originalTable.querySelectorAll('tbody tr');
            
            var newTable = document.createElement('table');
            newTable.className = 'table table-bordered';
            
            var newHeaderRow = document.createElement('tr');
            var newDataRows = [];
            
            // تعريف تخطيط الأعمدة للتصدير
            var columnMapping = {
                'tracking': 1,        // رقم الشحنة (بعد checkbox)
                'sender_name': 2,     // بيانات الراسل
                'sender_phone': 2,    // بيانات الراسل
                'recipient_name': 3,  // اسم المستلم
                'recipient_phone': 3, // اسم المستلم
                'governorate': 4,     // المحافظة
                'area': 5,           // المنطقة
                'shipment_type': 6,  // نوع الشحنة
                'courier': 7,        // المندوب
                'status': 8,         // حالة الشحنة
                'cod': 9,            // القيمة (COD)
                'commission': 10,    // العمولة
                'total': 11,         // إجمالي المستحق
                'date': 12          // التاريخ
            };
            
            // إضافة خلايا العناوين بناءً على الأعمدة المحددة
            selectedColumns.forEach(function(colType) {
                var newHeaderCell = document.createElement('th');
                var headerContent = '';
                
                // معالجة خاصة لعناوين الراسل والمستلم
                if (colType === 'sender_name') {
                    headerContent = 'اسم الراسل';
                } else if (colType === 'sender_phone') {
                    headerContent = 'رقم الراسل';
                } else if (colType === 'recipient_name') {
                    headerContent = 'اسم المستلم';
                } else if (colType === 'recipient_phone') {
                    headerContent = 'رقم المستلم';
                } else {
                    var headerIndex = columnMapping[colType];
                    if (headerIndex >= 0 && headerIndex < originalHeaderArray.length) {
                        headerContent = originalHeaderArray[headerIndex].innerHTML
                            .replace(/<br>/g, ' ')
                            .replace(/<small[^>]*>/g, '')
                            .replace(/<\/small>/g, ' ')
                            .replace(/<strong>/g, '')
                            .replace(/<\/strong>/g, '')
                            .replace(/<div>/g, '')
                            .replace(/<\/div>/g, ' ')
                            .replace(/<span[^>]*>/g, '')
                            .replace(/<\/span>/g, '')
                            .replace(/<i[^>]*>/g, '')
                            .replace(/<\/i>/g, '')
                            .replace(/\s+/g, ' ')
                            .trim();
                    }
                }
                
                newHeaderCell.innerHTML = headerContent;
                newHeaderRow.appendChild(newHeaderCell);
            });
            
            // إضافة صفوف البيانات
            originalRows.forEach(function(row) {
                var newRow = document.createElement('tr');
                var cells = row.querySelectorAll('td');
                
                selectedColumns.forEach(function(colType) {
                    var newCell = document.createElement('td');
                    var cellContent = '';
                    
                    // معالجة خاصة لأعمدة الراسل والمستلم
                    if (colType === 'sender_name' || colType === 'sender_phone') {
                        var senderCell = cells[2]; // خلية الراسل
                        if (senderCell) {
                            var senderDivs = senderCell.querySelectorAll('div');
                            if (senderDivs.length >= 2) {
                                if (colType === 'sender_name') {
                                    cellContent = senderDivs[0].innerText.trim();
                                } else if (colType === 'sender_phone') {
                                    cellContent = senderDivs[1].innerText.trim();
                                }
                            }
                        }
                    } else if (colType === 'recipient_name' || colType === 'recipient_phone') {
                        var recipientCell = cells[3]; // خلية المستلم
                        if (recipientCell) {
                            var recipientDivs = recipientCell.querySelectorAll('div');
                            if (recipientDivs.length >= 2) {
                                if (colType === 'recipient_name') {
                                    cellContent = recipientDivs[0].innerText.trim();
                                } else if (colType === 'recipient_phone') {
                                    cellContent = recipientDivs[1].innerText.trim();
                                }
                            }
                        }
                    } else {
                        var cellIndex = columnMapping[colType];
                        if (cellIndex >= 0 && cellIndex < cells.length) {
                            cellContent = cells[cellIndex].innerHTML
                                .replace(/<br>/g, ' ')
                                .replace(/<small[^>]*>/g, '')
                                .replace(/<\/small>/g, ' ')
                                .replace(/<strong>/g, '')
                                .replace(/<\/strong>/g, '')
                                .replace(/<div>/g, '')
                                .replace(/<\/div>/g, ' ')
                                .replace(/<span[^>]*>/g, '')
                                .replace(/<\/span>/g, '')
                                .replace(/<i[^>]*>/g, '')
                                .replace(/<\/i>/g, '')
                                .replace(/\s+/g, ' ')
                                .trim();
                        }
                    }
                    
                    newCell.innerHTML = cellContent;
                    newRow.appendChild(newCell);
                });
                
                newDataRows.push(newRow);
            });
            
            var newThead = document.createElement('thead');
            newThead.appendChild(newHeaderRow);
            newTable.appendChild(newThead);
            
            var newTbody = document.createElement('tbody');
            newDataRows.forEach(function(row) {
                newTbody.appendChild(row);
            });
            newTable.appendChild(newTbody);
            
            // تصدير الجدول الجديد
            exportTableToExcel(newTable, 'قائمة_الشحنات_' + new Date().toISOString().split('T')[0]);
            
            // إغلاق المودال
            $('#excelExportModal').modal('hide');
        }



        // Function to clear filters
        function clearFilters() {
            window.location.href = window.location.pathname + '?page=parcel_list';
        }
        


        // Select All functionality
        $(document).on('change', '#selectAll', function() {
            $('.shipment-checkbox').prop('checked', $(this).is(':checked'));
            updateSelectedCount();
        });

        // Individual checkbox change
        $(document).on('change', '.shipment-checkbox', function() {
            updateSelectedCount();
            // Update select all checkbox
            var totalCheckboxes = $('.shipment-checkbox').length;
            var checkedCheckboxes = $('.shipment-checkbox:checked').length;
            $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
        });

        // Update selected count
        function updateSelectedCount() {
            var count = $('.shipment-checkbox:checked').length;
            console.log('Selected shipments:', count);
        }


        

    });
</script>

</body>
</html>
