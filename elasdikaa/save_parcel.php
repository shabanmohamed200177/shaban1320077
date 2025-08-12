<?php
// file: save_parcel.php
// تم تحديثه ليشمل المنطق الكامل لحساب عمولات وتكاليف شحنات الوكلاء،
// مع التمييز بين "استلام شحنة من وكيل" و "إرسال شحنة إلى وكيل".

include('db_connect.php');
require_once __DIR__ . DIRECTORY_SEPARATOR . 'error_handler.php';

// لا نريد طباعة التحذيرات داخل JSON
if (function_exists('ini_set')) {
    @ini_set('display_errors', 0);
    @ini_set('log_errors', 1);
}
$conn->set_charset("utf8mb4");

session_start();
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 0, 'message' => "يجب تسجيل الدخول أولًا."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    echo json_encode(['status' => 0, 'message' => "بيانات غير صالحة."]);
    exit;
}

// ----------------------------------------------------------------------
// 1. استخراج وتنظيف البيانات من النموذج
// ----------------------------------------------------------------------
$input = filter_input_array(INPUT_POST, [
    'id' => FILTER_VALIDATE_INT,
    'sender_name' => FILTER_UNSAFE_RAW,
    'sender_phone' => FILTER_UNSAFE_RAW,
    'sender_address' => FILTER_UNSAFE_RAW,
    'sender_governorate_id' => FILTER_VALIDATE_INT,
    'sender_area_id' => FILTER_VALIDATE_INT,

    'recipient_name' => FILTER_UNSAFE_RAW,
    'recipient_phone' => FILTER_UNSAFE_RAW,
    'recipient_address' => FILTER_UNSAFE_RAW,
    'recipient_governorate_id' => FILTER_VALIDATE_INT,
    'recipient_area_id' => FILTER_VALIDATE_INT,

    'contents' => FILTER_UNSAFE_RAW,
    'weight' => FILTER_VALIDATE_FLOAT,
    'declared_value' => FILTER_VALIDATE_FLOAT,
    'cod_amount' => FILTER_VALIDATE_FLOAT,
    'cod_amount_from_agent' => FILTER_VALIDATE_FLOAT,
    'shipping_cost' => FILTER_VALIDATE_FLOAT,
    'agent_cost' => FILTER_VALIDATE_FLOAT, // تكلفة الوكيل التي يتم إرسالها من النموذج
    'courier_commission' => FILTER_VALIDATE_FLOAT,
    'shipping_cost_hidden' => FILTER_VALIDATE_FLOAT,
    'shipping_payer' => FILTER_UNSAFE_RAW,
    
    'status' => FILTER_VALIDATE_INT,
    'status_reason_id' => FILTER_VALIDATE_INT,
    'status_reason_note' => FILTER_UNSAFE_RAW,
    'financial_notes' => FILTER_UNSAFE_RAW,
    'note' => FILTER_UNSAFE_RAW,
    'pieces' => FILTER_VALIDATE_INT,

    'shipment_direction' => FILTER_UNSAFE_RAW,
    'client_id_fk' => FILTER_VALIDATE_INT,

    'agent_id_to' => FILTER_VALIDATE_INT,
    'agent_id_from' => FILTER_VALIDATE_INT,
    'courier_id' => FILTER_VALIDATE_INT,

    'payment_type' => FILTER_UNSAFE_RAW,
    'postpone_date' => FILTER_UNSAFE_RAW,
    'reference_number' => FILTER_UNSAFE_RAW,
]);

// تقليم النصوص
foreach ($input as $k => $v) {
    if (is_string($v)) {
        $input[$k] = trim($v);
    }
}

// ----------------------------------------------------------------------
// 2. التحقق من صحة البيانات الأساسية
// ----------------------------------------------------------------------
if (
    !$input['sender_name'] || !$input['sender_phone'] ||
    !$input['recipient_name'] || !$input['recipient_phone'] ||
    !isset($input['cod_amount']) || !isset($input['shipping_cost']) ||
    !isset($input['status'])
) {
    echo json_encode(['status' => 0, 'message' => "البيانات الأساسية للشحنة غير مكتملة."]);
    exit;
}

// ----------------------------------------------------------------------
// 3. تحديد الوكيل والمندوب وحساب القيم المالية
// ----------------------------------------------------------------------
$agent_id = null;
$courier_id = null;
$delivery_agent_fee = 0.00;
$agent_share = 0.00;
$company_profit = 0.00;
// تحديد القيم المالية حسب نوع الشحنة
if ($input['shipment_direction'] === 'to_agent') {
    // حالة: إرسال شحنة إلى وكيل
    $agent_id = $input['agent_id_to'];
    $agent_share = floatval($input['agent_cost']);
    $delivery_agent_fee = 0.00; // لا يوجد عمولة مندوب
    $courier_id = null;
    $cod_amount = floatval($input['cod_amount']);
    $shipping_fees = floatval($input['shipping_cost']);

    $company_profit = $shipping_fees - $agent_share;
    
    if ($input['shipping_payer'] === 'recipient') {
        $total_to_collect = $cod_amount + $shipping_fees;
        $net_amount = $cod_amount;
    } else { // 'sender'
        $total_to_collect = $cod_amount;
        $net_amount = $cod_amount - $shipping_fees;
    }
    $total_amount = $cod_amount + $shipping_fees; // إجمالي المبلغ (COD + الشحن)
    
} elseif ($input['shipment_direction'] === 'from_agent') {
    // حالة: استلام شحنة من وكيل
    $agent_id = $input['agent_id_from'];
    $courier_id = $input['courier_id'];
    $cod_amount = floatval($input['cod_amount_from_agent']);
    $delivery_agent_fee = floatval($input['courier_commission']);
    $agent_share = 0.00; // لا يوجد عمولة للوكيل هنا
    $shipping_fees = $delivery_agent_fee; // تكلفة الشحن = عمولة المندوب

    // صافي ربحك هو عمولة ثابتة (مثال: 3 جنيهات)
    $company_profit = 3.00; 

    // إجمالي المبلغ الذي يتم تحصيله هو قيمة الشحنة
    $total_to_collect = $cod_amount;

    // المبلغ الصافي للعملية (المدفوع للوكيل)
    $net_amount = $cod_amount - $delivery_agent_fee;
    $total_amount = $cod_amount;
    
} else {
    // حالة: شحنة عادية (بدون وكيل)
    $agent_id = null;
    $courier_id = $input['courier_id'];
    $delivery_agent_fee = 0.00;
    $agent_share = 0.00;
    $cod_amount = floatval($input['cod_amount']);
    $shipping_fees = floatval($input['shipping_cost']);
    
    $company_profit = $shipping_fees - $delivery_agent_fee;
    
    if ($input['shipping_payer'] === 'recipient') {
        $total_to_collect = $cod_amount + $shipping_fees;
        $net_amount = $cod_amount;
    } else { // 'sender'
        $total_to_collect = $cod_amount;
        $net_amount = $cod_amount - $shipping_fees;
    }
    $total_amount = $cod_amount + $shipping_fees;
}

// تحديد حالة الدفع الأولية
$payment_status = 'unpaid';
$paid_amount = 0.00;
if ($input['payment_type'] === 'full') {
    $payment_status = 'paid';
    $paid_amount = $total_to_collect;
} elseif ($input['payment_type'] === 'partial') {
    $payment_status = 'partial_paid';
    $paid_amount = 0.00;
}


$conn->begin_transaction();

try {
    $parcel_id = $input['id'];
    $is_update = !empty($parcel_id);
    $message = "";
    $tracking_number = "";
    $current_status = $input['status'];

    // ----------------------------------------------------------------------
    // 4. إضافة عميل جديد تلقائيًا أو الحصول على ID العميل الموجود
    //     مطلوب: عدم حفظ عميل الوكيل عندما تكون العملية "استلام من وكيل"
    // ----------------------------------------------------------------------
    $client_id = $input['client_id_fk'];
    if ($input['shipment_direction'] === 'from_agent') {
        // لا ننشئ عميلاً جديداً ولا نربط بعميل موجود في حالة الاستلام من وكيل
        $client_id = null;
    } else {
        // إرسال إلى وكيل: يمكن إنشاء/ربط عميل (عميل شركتك)
        if (empty($client_id) && !empty($input['sender_phone'])) {
            $check_client_sql = "SELECT id FROM customers WHERE phone = ?";
            $check_client_stmt = $conn->prepare($check_client_sql);
            $check_client_stmt->bind_param("s", $input['sender_phone']);
            $check_client_stmt->execute();
            $check_client_result = $check_client_stmt->get_result();

            if ($check_client_result->num_rows === 0) {
                $insert_client_sql = "INSERT INTO customers (name, phone, address, governorate_id, area_id) VALUES (?, ?, ?, ?, ?)";
                $insert_client_stmt = $conn->prepare($insert_client_sql);
                $insert_client_stmt->bind_param("sssis",
                    $input['sender_name'],
                    $input['sender_phone'],
                    $input['sender_address'],
                    $input['sender_governorate_id'],
                    $input['sender_area_id']
                );
                $insert_client_stmt->execute();
                $client_id = $conn->insert_id;
                $insert_client_stmt->close();
            } else {
                $existing_client = $check_client_result->fetch_assoc();
                $client_id = $existing_client['id'];
            }
            $check_client_stmt->close();
        }
    }


    // ----------------------------------------------------------------------
    // 5. حفظ/تحديث الشحنة الأساسية في جدول parcels
    // ----------------------------------------------------------------------
    if ($is_update) {
        $get_tracking_sql = "SELECT tracking_number FROM parcels WHERE id = ?";
        $get_tracking_stmt = $conn->prepare($get_tracking_sql);
        $get_tracking_stmt->bind_param("i", $parcel_id);
        $get_tracking_stmt->execute();
        $tracking_result = $get_tracking_stmt->get_result();
        $tracking_row = $tracking_result->fetch_assoc();
        $tracking_number = $tracking_row['tracking_number'];
        $get_tracking_stmt->close();

        $query = "UPDATE parcels SET
            contents = ?,
            sender_name = ?, sender_address = ?, sender_phone = ?, sender_governorate_id = ?, sender_area_id = ?,
            recipient_name = ?, recipient_address = ?, recipient_phone = ?, recipient_governorate_id = ?, recipient_area_id = ?,
            weight = ?, declared_value = ?, cod_amount = ?, shipping_fees = ?, delivery_agent_fee = ?, agent_share = ?,
            shipping_payer = ?, total_amount = ?, company_profit = ?, total_to_collect = ?, net_amount = ?,
            status = ?, payment_status = ?, paid_amount = ?,
            client_id_fk = ?, agent_id = ?, courier_id = ?, shipment_direction = ?, financial_notes = ?, note = ?,
            pieces = ?, partial_paid = ?, partial_note = ?, returned_pieces = ?,
            status_reason_id = ?, status_reason_note = ?, status_updated_at = NOW(), status_updated_by = ?,
            postpone_date = ?
            WHERE id = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("خطأ في إعداد استعلام التحديث: " . $conn->error);
        }
        
        $bind_params = [
            $input['contents'],
            $input['sender_name'], $input['sender_address'], $input['sender_phone'], $input['sender_governorate_id'], $input['sender_area_id'],
            $input['recipient_name'], $input['recipient_address'], $input['recipient_phone'], $input['recipient_governorate_id'], $input['recipient_area_id'],
            $input['weight'], $input['declared_value'], $cod_amount, $shipping_fees, $delivery_agent_fee, $agent_share,
            $input['shipping_payer'], $total_amount, $company_profit, $total_to_collect, $net_amount,
            $current_status, $payment_status, $paid_amount,
            $client_id, $agent_id, $courier_id, $input['shipment_direction'], $input['financial_notes'], $input['note'],
            $input['pieces'],
            (float)($input['partial_paid'] ?? 0), (string)($input['partial_note'] ?? ''), (int)($input['returned_pieces'] ?? 0),
            ($input['status_reason_id'] ?? null), ($input['status_reason_note'] ?? ''), ($_SESSION['name'] ?? 'system'),
            ($input['postpone_date'] ?: null),
            $parcel_id
        ];

        $types = "";
        foreach ($bind_params as $param) {
            if (is_int($param)) {
                $types .= "i";
            } elseif (is_float($param)) {
                $types .= "d";
            } else {
                $types .= "s";
            }
        }
        
        $stmt->bind_param($types, ...$bind_params);
        $stmt->execute();
        $message = "تم تحديث الشحنة بنجاح.";

    } else {
        $tracking_number = 'TRACK-' . strtoupper(uniqid());

        // فحص وجود أعمدة اختيارية لتفادي عدم تطابق الأعمدة والقيم
        $has_reference = false; $has_postpone = false;
        if ($chk = $conn->query("SHOW COLUMNS FROM parcels LIKE 'reference_number'")) { $has_reference = $chk->num_rows > 0; $chk->close(); }
        if ($chk2 = $conn->query("SHOW COLUMNS FROM parcels LIKE 'postpone_date'")) { $has_postpone = $chk2->num_rows > 0; $chk2->close(); }

        // جلب كل أعمدة جدول parcels لمعرفة الأعمدة الإلزامية القديمة
        $existingCols = [];
        if ($all = $conn->query('SHOW COLUMNS FROM parcels')) {
            while($r = $all->fetch_assoc()) { $existingCols[$r['Field']] = true; }
            $all->close();
        }

        $cols = [
            'tracking_number','contents','sender_name','sender_address','sender_phone','sender_governorate_id','sender_area_id',
            'recipient_name','recipient_address','recipient_phone','recipient_governorate_id','recipient_area_id',
            'weight','declared_value','cod_amount','shipping_fees','delivery_agent_fee','agent_share','shipping_payer',
            'total_amount','company_profit','total_to_collect','net_amount','status','payment_status','paid_amount',
            'client_id_fk','agent_id','courier_id','shipment_direction','financial_notes','note','pieces',
            'partial_paid','partial_note','returned_pieces','status_reason_id','status_reason_note','status_updated_by'
        ];
        $params = [
            $tracking_number, $input['contents'],
            $input['sender_name'], $input['sender_address'], $input['sender_phone'], $input['sender_governorate_id'], $input['sender_area_id'],
            $input['recipient_name'], $input['recipient_address'], $input['recipient_phone'], $input['recipient_governorate_id'], $input['recipient_area_id'],
            $input['weight'], $input['declared_value'], $cod_amount, $shipping_fees, $delivery_agent_fee, $agent_share,
            $input['shipping_payer'], $total_amount, $company_profit, $total_to_collect, $net_amount,
            $current_status, $payment_status, $paid_amount,
            $client_id, $agent_id, $courier_id, $input['shipment_direction'], $input['financial_notes'], $input['note'],
            $input['pieces'], (float)($input['partial_paid'] ?? 0), (string)($input['partial_note'] ?? ''), (int)($input['returned_pieces'] ?? 0),
            ($input['status_reason_id'] ?? null), ($input['status_reason_note'] ?? ''), ($_SESSION['name'] ?? 'system')
        ];

        // أعمدة قديمة إلزامية في بعض المخططات
        if (!empty($existingCols['sender_contact'])) {
            array_splice($cols, 3, 0, 'sender_contact');
            array_splice($params, 3, 0, (string)$input['sender_phone']);
        }
        if (!empty($existingCols['recipient_contact'])) {
            // بعد recipient_address مباشرة
            $pos = array_search('recipient_address', $cols);
            if ($pos !== false) { $pos++;
                array_splice($cols, $pos, 0, 'recipient_contact');
                array_splice($params, $pos, 0, (string)$input['recipient_phone']);
            }
        }
        if (!empty($existingCols['type'])) { $cols[] = 'type'; $params[] = 1; }
        if (!empty($existingCols['from_branch_id'])) { $cols[] = 'from_branch_id'; $params[] = ''; }
        if (!empty($existingCols['to_branch_id'])) { $cols[] = 'to_branch_id'; $params[] = ''; }
        if (!empty($existingCols['height'])) { $cols[] = 'height'; $params[] = '0'; }
        if (!empty($existingCols['width'])) { $cols[] = 'width'; $params[] = '0'; }
        if (!empty($existingCols['length'])) { $cols[] = 'length'; $params[] = '0'; }
        if (!empty($existingCols['price'])) { $cols[] = 'price'; $params[] = 0; }

        if ($has_reference) {
            array_splice($cols, 1, 0, 'reference_number');
            $reference_number = !empty($input['reference_number']) ? $input['reference_number'] : ('REF-' . strtoupper(uniqid()));
            array_splice($params, 1, 0, $reference_number);
        }
        if ($has_postpone) {
            $cols[] = 'postpone_date';
            $params[] = ($input['postpone_date'] ?: null);
        }

        $placeholders = rtrim(str_repeat('?,', count($cols)), ',');
        $query = 'INSERT INTO parcels (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
        
        $stmt = $conn->prepare($query);
        if (!$stmt) { throw new Exception('خطأ في إعداد استعلام الإدخال: ' . $conn->error); }

        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) { $types .= 'i'; }
            elseif (is_float($param)) { $types .= 'd'; }
            else { $types .= 's'; }
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $parcel_id = $conn->insert_id;
        $message = 'تم حفظ الشحنة بنجاح.';
    }
    $stmt->close();

    // ----------------------------------------------------------------------
    // 5.5. حفظ سجل تغيير الحالة مع السبب في جدول parcel_status_history
    // ----------------------------------------------------------------------
    if ($is_update && isset($input['status_reason_id']) && !empty($input['status_reason_id'])) {
        // جلب الحالة السابقة
        $old_status_query = "SELECT status FROM parcels WHERE id = ?";
        $old_status_stmt = $conn->prepare($old_status_query);
        $old_status_stmt->bind_param("i", $parcel_id);
        $old_status_stmt->execute();
        $old_status_result = $old_status_stmt->get_result();
        $old_status = $old_status_result->num_rows > 0 ? $old_status_result->fetch_assoc()['status'] : null;
        $old_status_stmt->close();

        // حفظ سجل التغيير
        $history_sql = "INSERT INTO parcel_status_history (parcel_id, old_status_id, new_status_id, reason_id, reason_note, changed_by, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $history_stmt = $conn->prepare($history_sql);
        if ($history_stmt) {
            $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $changed_by = $_SESSION['name'] ?? $_SESSION['username'] ?? 'system';
            $history_stmt->bind_param("iiiiiss", $parcel_id, $old_status, $current_status, $input['status_reason_id'], $input['status_reason_note'], $changed_by, $user_ip);
            $history_stmt->execute();
            $history_stmt->close();
        }
    }

    // ----------------------------------------------------------------------
    // 6. تسجيل حالة الشحنة في جدول parcel_tracks
    // ----------------------------------------------------------------------
    $last_status_query = "SELECT status FROM parcel_tracks WHERE parcel_id = ? ORDER BY date_created DESC LIMIT 1";
    $last_status_stmt = $conn->prepare($last_status_query);
    $last_status_stmt->bind_param("i", $parcel_id);
    $last_status_stmt->execute();
    $last_status_result = $last_status_stmt->get_result();
    $last_recorded_status = null;
    if ($last_status_result->num_rows > 0) {
        $last_recorded_status = $last_status_result->fetch_assoc()['status'];
    }
    $last_status_stmt->close();

    if ($last_recorded_status != $current_status) {
        $insert_track_sql = "INSERT INTO parcel_tracks (parcel_id, status, date_created) VALUES (?, ?, NOW())";
        $insert_track_stmt = $conn->prepare($insert_track_sql);
        if (!$insert_track_stmt) {
            throw new Exception("خطأ في إعداد استعلام تتبع الشحنة: " . $conn->error);
        }
        $insert_track_stmt->bind_param("ii", $parcel_id, $current_status);
        $insert_track_stmt->execute();
        $insert_track_stmt->close();
    }


    // ----------------------------------------------------------------------
    // 7. تسجيل الحركات المالية في جدول cashbox_transactions
    // ----------------------------------------------------------------------
    // هذا الجزء يحتاج إلى منطق أكثر دقة لتجنب تسجيل نفس الحركة المالية عدة مرات عند التحديث
    // أو لضمان أن الحركات المالية تعكس فقط التغييرات الفعلية في الدفع.
    // حاليًا، هذا المنطق يسجل فقط عند الإدخال الأولي أو عند تغيير حالة الدفع في parcel_list.php
    // إذا كنت تريد تسجيل كل حركة مالية هنا عند كل تحديث، يجب أن يكون هناك فحص للحركات الموجودة.

    if (!$is_update && $payment_status === 'paid' && $total_to_collect > 0) {
        $conn->query("INSERT INTO cashbox_transactions (branch_id, type, relation_id, amount, direction, notes, created_at)
                        VALUES ({$_SESSION['login_branch_id']}, 'customer', $client_id, $total_to_collect, 'in', 'تحصيل من المستلم للشحنة: $tracking_number', NOW())");
    }
    // يمكن إضافة منطق لعمولة المندوب والوكيل هنا أيضًا إذا كانت تسجل عند الحفظ الأولي
    // ولكن يجب تجنب التكرار عند التحديث

    $conn->commit();
    echo json_encode(['status' => 1, 'message' => $message, 'tracking_number' => $tracking_number]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 0, 'message' => 'فشل حفظ الشحنة: ' . $e->getMessage()]);
}

$conn->close();
?>
