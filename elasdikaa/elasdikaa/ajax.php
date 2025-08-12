<?php
// Start output buffering to prevent "headers already sent" errors.
// This must be the very first thing in the file, no spaces or newlines before it.
ob_start();

// Set default timezone to avoid PHP warnings
date_default_timezone_set("Africa/Cairo");

// Start the session only if it hasn't been started already.
// This ensures session_start() is called only once.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting: لا تعرض الأخطاء لتجنب كسر JSON في الردود
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set the content type header for JSON responses by default.
// This should be done before any output. Specific cases (like HTML) will override it.
header('Content-Type: application/json');

// Get the action from GET request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Include necessary files.
// Ensure these files (db_connect.php, admin_class.php) do NOT output anything
// (like spaces, newlines, or HTML) before their opening <?php tag.
include 'admin_class.php';
$crud = new Action();
include 'db_connect.php';

// Use a try-catch block to gracefully handle any unexpected errors and return JSON.
try {
    switch ($action) {
        case 'login':
            echo $crud->login();
            break;

        case 'login2':
            echo $crud->login2();
            break;
        case 'logout':
            echo $crud->logout();
            break;
        case 'logout2':
            session_destroy();
            header("Location: login.php"); // تم تبسيط عملية تسجيل الخروج
            exit;
            break;
        case 'signup':
            echo $crud->signup();
            break;
        case 'save_user':
            echo $crud->save_user();
            break;
        case 'update_user':
            echo $crud->update_user();
            break;
        case 'delete_user':
            echo $crud->delete_user();
            break;
        case 'save_branch':
            echo $crud->save_branch();
            break;
        case 'delete_branch':
            echo $crud->delete_branch();
            break;

        case 'save_parcel':
            echo json_encode(['status' => 0, 'message' => 'استدعاء save_parcel من ajax.php غير مدعوم. يرجى استدعاء save_parcel.php مباشرة.']);
            break;

        case 'delete_parcel':
            $result = $crud->delete_parcel();
            if ($result === 1) {
                echo json_encode(['status' => 'success', 'message' => 'تم حذف الشحنة بنجاح.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $result]);
            }
            break;
        case 'update_parcel':
            $result = $crud->update_parcel();
            if ($result === 1) {
                echo json_encode(['status' => 'success', 'message' => 'تم تحديث الشحنة بنجاح.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $result]);
            }
            break;
        case 'get_parcel_heistory':
            echo $crud->get_parcel_heistory();
            break;
        case 'update_parcel_status':
            $result = $crud->update_parcel_status();
            if ($result === 1) {
                echo json_encode(['status' => 'success', 'message' => 'تم تحديث حالة الشحنة بنجاح.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $result]);
            }
            break;
        case 'update_multiple_parcel_status':
            $result = $crud->update_multiple_parcel_status();
            if ($result === 1) {
                echo json_encode(['status' => 'success', 'message' => 'تم تحديث حالة الشحنات المحددة بنجاح.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $result]);
            }
            break;
        case 'get_report':
            echo $crud->get_report();
            break;
        case 'get_client_report':
            echo json_encode(['status' => 0, 'message' => 'الدالة get_client_report غير معرفة أو غير مدعومة.']);
            break;
        case 'get_agent_report':
            echo json_encode(['status' => 0, 'message' => 'الدالة get_agent_report غير معرفة أو غير مدعومة.']);
            break;
        case 'save_courier':
            echo $crud->save_courier();
            break;
        case 'delete_courier':
            echo $crud->delete_courier();
            break;
        case 'add_governorate':
            echo $crud->add_governorate();
            break;
        case 'update_governorate':
            echo $crud->update_governorate();
            break;
        case 'delete_governorate':
            echo $crud->delete_governorate();
            break;
        case 'add_area':
            echo $crud->add_area();
            break;
        case 'update_area':
            echo $crud->update_area();
            break;
        case 'delete_area':
            echo $crud->delete_area();
            break;
        case 'save_customer':
            echo $crud->save_customer();
            break;
        case 'update_customer':
            echo $crud->update_customer();
            break;
        case 'delete_customer':
            $result = $crud->delete_customer();
            if ($result === 1) {
                echo json_encode(['status' => 'success', 'message' => 'تم حذف العميل بنجاح.']);
            } elseif ($result === 0) {
                echo json_encode(['status' => 'error', 'message' => 'لم يتم العثور على العميل.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $result]);
            }
            break;
        case 'update_agent':
            echo $crud->update_agent();
            break;

        case 'get_all_governorates':
            $governorates_qry = $conn->query("SELECT id, name FROM governorates ORDER BY name ASC");
            $result = [];
            while ($row = $governorates_qry->fetch_assoc()) {
                $result[] = $row;
            }
            echo json_encode(['status' => 1, 'data' => $result]);
            break;

        case 'get_all_customers_filtered':
            $search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';
            $governorate_id = isset($_POST['governorate_id']) ? intval($_POST['governorate_id']) : 0;

            $sql = "SELECT c.id, c.name, c.phone, c.email, c.address, c.status, c.date_created, g.name as governorate_name, a.name as area_name
                             FROM customers c
                             LEFT JOIN governorates g ON c.governorate_id = g.id
                             LEFT JOIN areas a ON c.area_id = a.id
                             WHERE 1=1";
            $bind_types = "";
            $bind_params = [];

            if (!empty($search_query)) {
                $sql .= " AND (c.name LIKE ? OR c.phone LIKE ?)";
                $bind_types .= "ss";
                $bind_params[] = "%" . $search_query . "%";
                $bind_params[] = "%" . $search_query . "%";
            }
            if ($governorate_id > 0) {
                $sql .= " AND c.governorate_id = ?";
                $bind_types .= "i";
                $bind_params[] = $governorate_id;
            }
            $sql .= " ORDER BY c.name ASC";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['status' => 'error', 'message' => 'خطأ في إعداد استعلام العملاء: ' . $conn->error]);
                break;
            }

            if (!empty($bind_params)) {
                $stmt->bind_param($bind_types, ...$bind_params);
            }
            
            $stmt->execute();
            $customers_qry = $stmt->get_result();
            $customers_arr = [];
            while ($row = $customers_qry->fetch_assoc()) {
                $customers_arr[] = $row;
            }
            $stmt->close();
            echo json_encode(['status' => 'success', 'data' => $customers_arr]);
            break;

        case 'get_customer_data_for_form':
            $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
            $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $c = $result->fetch_assoc();
            $stmt->close();

            if ($c) {
                echo json_encode(['status' => 1, 'data' => $c]);
            } else {
                echo json_encode(['status' => 0, 'message' => 'لم يتم العثور على العميل.']);
            }
            break;

        case 'get_areas_by_gov':
            $gov_id = isset($_POST['gov_id']) ? intval($_POST['gov_id']) : 0;
            if ($gov_id <= 0) {
                echo json_encode(['status' => 0, 'message' => 'معرف المحافظة غير صالح']);
                break;
            }
            $stmt = $conn->prepare("SELECT id, name, price FROM areas WHERE governorate_id = ? ORDER BY name ASC");
            $stmt->bind_param("i", $gov_id);
            $stmt->execute();
            $areas_qry = $stmt->get_result();
            $areas_arr = [];
            while ($a = $areas_qry->fetch_assoc()) {
                $areas_arr[] = [
                    'id' => (int)$a['id'],
                    'name' => $a['name'],
                    'price' => isset($a['price']) ? (float)$a['price'] : 0
                ];
            }
            $stmt->close();
            echo json_encode(['status' => 1, 'data' => $areas_arr]);
            break;

        case 'get_customer_shipments':
            $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
            $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
            $start_date = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? $_POST['start_date'] . ' 00:00:00' : null;
            $end_date = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? $_POST['end_date'] . ' 23:59:59' : null;
            $per_page = 10; // عدد الشحنات لكل صفحة

            if (!$customer_id) {
                echo json_encode(['status' => 'error', 'message' => 'معرف العميل غير صالح.']);
                break;
            }
            
            // جلب بيانات العميل
            $stmt_customer = $conn->prepare("SELECT id, name, phone FROM customers WHERE id = ?");
            $stmt_customer->bind_param("i", $customer_id);
            $stmt_customer->execute();
            $customer_result = $stmt_customer->get_result();
            $customer_data = $customer_result->fetch_assoc();
            $stmt_customer->close();

            if (!$customer_data) {
                echo json_encode(['status' => 'error', 'message' => 'لم يتم العثور على العميل.']);
                break;
            }

            // استخدام رقم الهاتف من قاعدة البيانات مباشرة لتجنب التلاعب
            $customer_phone = $customer_data['phone'];
            
            // تعريف مصفوفة حالات الشحنات
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

            // بناء استعلام جلب إجمالي عدد الشحنات مع فلاتر التاريخ (للتصفح)
            $count_sql = "SELECT COUNT(*) as total FROM parcels WHERE sender_phone = ?";
            $bind_types_count = "s";
            $bind_params_count = [$customer_phone];
            if ($start_date) {
                $count_sql .= " AND date_created >= ?";
                $bind_types_count .= "s";
                $bind_params_count[] = $start_date;
            }
            if ($end_date) {
                $count_sql .= " AND date_created <= ?";
                $bind_types_count .= "s";
                $bind_params_count[] = $end_date;
            }
            $stmt_count = $conn->prepare($count_sql);
            $stmt_count->bind_param($bind_types_count, ...$bind_params_count);
            $stmt_count->execute();
            $count_result = $stmt_count->get_result()->fetch_assoc();
            $total_shipments = $count_result['total'];
            $stmt_count->close();

            $total_pages = ceil($total_shipments / $per_page);
            $start_idx = ($page - 1) * $per_page;

            // بناء استعلام جلب الشحنات للصفحة الحالية
            $shipments_sql = "SELECT
                                p.id,
                                p.tracking_number,
                                p.recipient_name,
                                p.recipient_phone,
                                p.status,
                                p.cod_amount,
                                p.shipping_fees,
                                p.shipping_payer,
                                p.paid_amount,
                                p.payment_status,
                                p.date_created,
                                c.name AS courier_name
                             FROM parcels p
                             LEFT JOIN couriers c ON p.courier_id = c.id
                             WHERE p.sender_phone = ?";
            $bind_types_shipments = "s";
            $bind_params_shipments = [$customer_phone];

            if ($start_date) {
                $shipments_sql .= " AND p.date_created >= ?";
                $bind_types_shipments .= "s";
                $bind_params_shipments[] = $start_date;
            }
            if ($end_date) {
                $shipments_sql .= " AND p.date_created <= ?";
                $bind_types_shipments .= "s";
                $bind_params_shipments[] = $end_date;
            }
            $shipments_sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";
            $bind_types_shipments .= "ii";
            $bind_params_shipments[] = $per_page;
            $bind_params_shipments[] = $start_idx;

            $stmt_shipments = $conn->prepare($shipments_sql);
            if ($stmt_shipments === false) {
                echo json_encode(['status' => 'error', 'message' => 'خطأ في إعداد استعلام الشحنات: ' . $conn->error]);
                break;
            }
            $stmt_shipments->bind_param($bind_types_shipments, ...$bind_params_shipments);
            
            $stmt_shipments->execute();
            $shipments_qry = $stmt_shipments->get_result();
            $shipments_on_page = [];
            while ($row = $shipments_qry->fetch_assoc()) {
                $shipments_on_page[] = $row;
            }
            $stmt_shipments->close();

            // حساب الإجمالي المستحق للعميل من الشحنات المسلمة وغير المدفوعة
            $total_deliverd_unpaid_cod = 0;
            $total_paid_by_customer = 0;
            // يجب استعلام إجمالي المبالغ من قاعدة البيانات مباشرة بدلاً من تكرارها
            $summary_sql = "SELECT SUM(cod_amount) as total_cod_unpaid FROM parcels WHERE sender_phone = ? AND status = 4 AND payment_status != 'paid'";
            $stmt_summary = $conn->prepare($summary_sql);
            $stmt_summary->bind_param("s", $customer_phone);
            $stmt_summary->execute();
            $summary_result = $stmt_summary->get_result()->fetch_assoc();
            $total_deliverd_unpaid_cod = floatval($summary_result['total_cod_unpaid'] ?? 0);
            $stmt_summary->close();

            $total_paid_by_customer_sql = "SELECT SUM(paid_amount) as total_paid FROM parcels WHERE sender_phone = ?";
            $stmt_total_paid = $conn->prepare($total_paid_by_customer_sql);
            $stmt_total_paid->bind_param("s", $customer_phone);
            $stmt_total_paid->execute();
            $total_paid_result = $stmt_total_paid->get_result()->fetch_assoc();
            $total_paid_by_customer = floatval($total_paid_result['total_paid'] ?? 0);
            $stmt_total_paid->close();


            echo json_encode([
                'status' => 'success',
                'customer_details' => $customer_data,
                'shipments' => $shipments_on_page,
                'total_shipments' => $total_shipments,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'summary' => [
                    'total_paid_by_customer' => $total_paid_by_customer,
                    'total_deliverd_unpaid_cod' => $total_deliverd_unpaid_cod // المبلغ الذي سيدفعه للعميل
                ],
                'parcel_statuses_map' => $status_arr
            ]);
            break;

        case 'process_customer_payout':
            $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
            $parcel_ids = isset($_POST['parcel_ids']) ? $_POST['parcel_ids'] : [];

            if (!$customer_id || empty($parcel_ids)) {
                echo json_encode(['status' => 'error', 'message' => 'بيانات الدفع غير مكتملة.']);
                break;
            }

            $conn->begin_transaction();
            $success = true;
            $message = "تم دفع مستحقات الشحنات بنجاح.";
            $total_payout_amount = 0;

            try {
                // جلب بيانات العميل لتسجيل الملاحظات
                $stmt_customer_name = $conn->prepare("SELECT name FROM customers WHERE id = ?");
                $stmt_customer_name->bind_param("i", $customer_id);
                $stmt_customer_name->execute();
                $customer_name_result = $stmt_customer_name->get_result();
                $customer_name_row = $customer_name_result->fetch_assoc();
                $customer_name = $customer_name_row['name'] ?? 'عميل غير معروف';
                $stmt_customer_name->close();

                // بناء استعلام واحد لجلب تفاصيل الشحنات المحددة
                $placeholders = implode(',', array_fill(0, count($parcel_ids), '?'));
                $get_parcels_sql = "SELECT id, cod_amount, shipping_fees, shipping_payer, status, payment_status, tracking_number FROM parcels WHERE id IN ($placeholders)";
                $stmt_parcels = $conn->prepare($get_parcels_sql);
                $types = str_repeat('i', count($parcel_ids));
                $stmt_parcels->bind_param($types, ...$parcel_ids);
                $stmt_parcels->execute();
                $parcels_result = $stmt_parcels->get_result();
                $stmt_parcels->close();

                $processed_parcel_ids = [];

                while ($parcel_data = $parcels_result->fetch_assoc()) {
                    $parcel_id = $parcel_data['id'];
                    // التحقق من أن الشحنة تم تسليمها بنجاح (status = 4) ولم يتم الدفع عنها للعميل بعد
                    if ($parcel_data['status'] == 4 && $parcel_data['payment_status'] !== 'paid') {
                        $payout_amount = floatval($parcel_data['cod_amount'] ?? 0);
                        $total_payout_amount += $payout_amount;
                        $processed_parcel_ids[] = $parcel_id;
                    } else {
                        $success = false;
                        $message = "الشحنة رقم " . $parcel_data['tracking_number'] . " ليست جاهزة للدفع (الحالة ليست تم التسليم بنجاح أو تم دفعها بالفعل).";
                        break; // توقف عند أول خطأ
                    }
                }
                
                if ($success && !empty($processed_parcel_ids)) {
                    $placeholders_update = implode(',', array_fill(0, count($processed_parcel_ids), '?'));
                    $update_parcel_sql = "UPDATE parcels SET payment_status = 'paid', paid_amount = cod_amount, status = 5, paid_at = NOW() WHERE id IN ($placeholders_update)";
                    $stmt_update_parcel = $conn->prepare($update_parcel_sql);
                    $types_update = str_repeat('i', count($processed_parcel_ids));
                    $stmt_update_parcel->bind_param($types_update, ...$processed_parcel_ids);
                    $stmt_update_parcel->execute();
                    $stmt_update_parcel->close();
                    
                    // تسجيل حركة مالية صادرة (دفع للعميل)
                    $notes = "دفع مستحقات العميل: " . $customer_name . " لعدد " . count($processed_parcel_ids) . " شحنة.";
                    $insert_cashbox_sql = "INSERT INTO cashbox_transactions (branch_id, type, relation_id, amount, direction, notes, created_at) VALUES (?, 'customer', ?, ?, 'out', ?, NOW())";
                    $stmt_cashbox = $conn->prepare($insert_cashbox_sql);
                    $branch_id = $_SESSION['login_branch_id'] ?? 0;
                    $stmt_cashbox->bind_param("iids", $branch_id, $customer_id, $total_payout_amount, $notes);
                    $stmt_cashbox->execute();
                    $stmt_cashbox->close();

                    // تسجيل تغيير الحالة في سجل التتبع لجميع الشحنات
                    $insert_track_sql = "INSERT INTO parcel_tracks (parcel_id, status, date_created) VALUES (?, 5, NOW())";
                    $stmt_track = $conn->prepare($insert_track_sql);
                    foreach($processed_parcel_ids as $p_id) {
                        $stmt_track->bind_param("i", $p_id);
                        $stmt_track->execute();
                    }
                    $stmt_track->close();
                } else if ($success) {
                    $message = "لا توجد شحنات مؤهلة للدفع.";
                }

                if ($success) {
                    $conn->commit();
                    echo json_encode(['status' => 'success', 'message' => $message . " إجمالي المبلغ المدفوع: " . number_format($total_payout_amount, 2) . " جنيه."]);
                } else {
                    $conn->rollback();
                    echo json_encode(['status' => 'error', 'message' => $message]);
                }

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'فشل معالجة الدفع: ' . $e->getMessage()]);
            }
            break;

        case 'get_customer_details':
            // This action returns HTML, so we explicitly set the header here.
            header('Content-Type: text/html; charset=utf-8');
            $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
            $stmt = $conn->prepare("SELECT c.*, g.name as governorate_name, a.name as area_name FROM customers c LEFT JOIN governorates g ON c.governorate_id = g.id LEFT JOIN areas a ON c.area_id = a.id WHERE c.id = ?");
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $c = $result->fetch_assoc();
            $stmt->close();

            if ($c) {
                echo '<table class="table table-bordered">';
                echo '<tr><th>اسم العميل</th><td>' . htmlspecialchars($c['name']) . '</td></tr>';
                echo '<tr><th>رقم الجوال</th><td>' . htmlspecialchars($c['phone']) . '</td></tr>';
                echo '<tr><th>العنوان</th><td>' . htmlspecialchars($c['address']) . '</td></tr>';
                echo '<tr><th>المحافظة</th><td>' . htmlspecialchars($c['governorate_name']) . '</td></tr>';
                echo '<tr><th>المنطقة</th><td>' . htmlspecialchars($c['area_name']) . '</td></tr>';
                echo '<tr><th>الحالة</th><td>' . ($c['status'] ? "نشط" : "موقوف") . '</td></tr>';
                echo '<tr><th>تاريخ الإضافة</th><td>' . htmlspecialchars($c['date_created']) . '</td></tr>';
                echo '</table>';
            } else {
                echo '<div class="alert alert-danger">لم يتم العثور على العميل.</div>';
            }
            break;
        case 'update_parcel_status':
            $result = $crud->update_parcel_status();
            if ($result === 1) {
                echo json_encode(['status' => 'success', 'message' => 'تم تحديث حالة الشحنة بنجاح.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $result]);
            }
            break;
        case 'update_multiple_parcel_status':
            $result = $crud->update_multiple_parcel_status();
            if ($result === 1) {
                echo json_encode(['status' => 'success', 'message' => 'تم تحديث حالة الشحنات المحددة بنجاح.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $result]);
            }
            break;
        case 'search_customers':
            // تم استبدال استخدام real_escape_string باستعلامات مُعدة لمنع حقن SQL.
            $query = isset($_POST['query']) ? trim($_POST['query']) : '';
            $sql = "SELECT id, name, phone, address, governorate_id, area_id FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name ASC LIMIT 10";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $search_param = "%" . $query . "%";
                $stmt->bind_param("ss", $search_param, $search_param);
                $stmt->execute();
                $q = $stmt->get_result();
                $result = [];
                while ($row = $q->fetch_assoc()) {
                    $result[] = $row;
                }
                $stmt->close();
                echo json_encode($result);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'خطأ في إعداد الاستعلام']);
            }
            break;
        case 'get_shipping_cost':
            // This action returns plain text, so we explicitly set the header here.
            header('Content-Type: text/plain');
            $gov_id = isset($_POST['gov_id']) ? intval($_POST['gov_id']) : 0;
            $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
            $cost = 0;
            if ($area_id > 0) {
                $stmt = $conn->prepare("SELECT price FROM areas WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $area_id);
                $stmt->execute();
                $qry = $stmt->get_result();
                if ($row = $qry->fetch_assoc()) {
                    $cost = floatval($row['price']);
                }
                $stmt->close();
            } elseif ($gov_id > 0) {
                $stmt = $conn->prepare("SELECT price FROM governorates WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $gov_id);
                $stmt->execute();
                $qry = $stmt->get_result();
                if ($row = $qry->fetch_assoc()) {
                    $cost = floatval($row['price']);
                }
                $stmt->close();
            }
            echo $cost;
            break;
        
        // --- كود جديد: حالات جديدة لنظام الإشعارات والرسوم البيانية ---

        case 'get_notifications':
            // تأكد أن user_id متاح في الجلسة أو يتم تمريره بشكل آمن.
            // هنا نفترض أن user_id هو 1 (مدير النظام) كما في admin_class.php
            $user_id = $_SESSION['login_id'] ?? 1;
            $result = $crud->get_notifications($user_id);
            echo json_encode(['status' => 'success', 'data' => $result]);
            break;

        case 'get_agent_chart_data':
            $data = $crud->get_agent_monthly_performance();
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        // --- نهاية الكود الجديد ---
        
        // وظائف إدارة الوكلاء المتقدمة
        case 'get_all_agents':
            $search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';
            $status_filter = isset($_POST['status_filter']) ? $_POST['status_filter'] : '';
            $governorate_id = isset($_POST['governorate_id']) ? intval($_POST['governorate_id']) : 0;
            
            $where_conditions = [];
            $params = [];
            $types = "";
            
            if (!empty($search_query)) {
                $where_conditions[] = "(a.company_name LIKE ? OR a.name LIKE ? OR a.phone LIKE ?)";
                $search_param = "%$search_query%";
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
                $types .= "sss";
            }
            
            if ($status_filter !== '') {
                $where_conditions[] = "a.status = ?";
                $params[] = intval($status_filter);
                $types .= "i";
            }
            
            if ($governorate_id > 0) {
                $where_conditions[] = "a.governorate_id = ?";
                $params[] = $governorate_id;
                $types .= "i";
            }
            
            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
            
            $query = "
                SELECT a.*, g.name as governorate_name, ar.name as area_name
                FROM agents a
                LEFT JOIN governorates g ON a.governorate_id = g.id
                LEFT JOIN areas ar ON a.area_id = ar.id
                $where_clause
                ORDER BY a.company_name ASC
            ";
            
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                 echo json_encode(['status' => 0, 'message' => 'خطأ في تحضير الاستعلام: ' . $conn->error]);
                 break;
            }
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $agents = [];
            while ($row = $result->fetch_assoc()) {
                $agents[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 1, 'data' => $agents]);
            break;
            
        case 'get_agent_shipments_count':
            $agent_id = isset($_POST['agent_id']) ? intval($_POST['agent_id']) : 0;
            $type = isset($_POST['type']) ? $_POST['type'] : 'sent';
            
            if ($agent_id > 0) {
                if ($type === 'sent') {
                    // الشحنات المرسلة إلى الوكيل (type = 1)
                    $query = "SELECT COUNT(*) as count FROM parcels WHERE agent_id = ? AND type = 1";
                } else {
                    // الشحنات المستلمة من الوكيل (type = 2)
                    $query = "SELECT COUNT(*) as count FROM parcels WHERE agent_id = ? AND type = 2";
                }
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $agent_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                echo json_encode(['status' => 'success', 'count' => $row['count']]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'معرف الوكيل غير صالح']);
            }
            break;
            
        case 'get_agent_shipments':
            $agent_id = isset($_POST['agent_id']) ? intval($_POST['agent_id']) : 0;
            $type = isset($_POST['type']) ? $_POST['type'] : 'sent';
            
            if ($agent_id > 0) {
                if ($type === 'sent') {
                    // الشحنات المرسلة إلى الوكيل
                    $query = "
                        SELECT p.*, ps.name_ar as status_name, ps.color as status_color
                        FROM parcels p
                        LEFT JOIN parcel_status ps ON p.status = ps.id
                        WHERE p.agent_id = ? AND p.type = 1
                        ORDER BY p.date_created DESC
                    ";
                } else {
                    // الشحنات المستلمة من الوكيل
                    $query = "
                        SELECT p.*, ps.name_ar as status_name, ps.color as status_color
                        FROM parcels p
                        LEFT JOIN parcel_status ps ON p.status = ps.id
                        WHERE p.agent_id = ? AND p.type = 2
                        ORDER BY p.date_created DESC
                    ";
                }
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $agent_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $shipments = [];
                while ($row = $result->fetch_assoc()) {
                    $shipments[] = $row;
                }
                
                echo json_encode(['status' => 'success', 'data' => $shipments]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'معرف الوكيل غير صالح']);
            }
            break;
            
        case 'get_agent_financial_summary':
            $agent_id = isset($_POST['agent_id']) ? intval($_POST['agent_id']) : 0;
            
            if ($agent_id > 0) {
                // حساب الملخص المالي للوكيل
                $sent_query = "SELECT COUNT(*) as count, SUM(agent_share) as total_commission FROM parcels WHERE agent_id = ? AND type = 1";
                $received_query = "SELECT COUNT(*) as count, SUM(agent_share) as total_commission FROM parcels WHERE agent_id = ? AND type = 2";
                $pending_query = "SELECT SUM(agent_share) as pending_amount FROM parcels WHERE agent_id = ? AND payment_status != 'paid'";
                
                // الشحنات المرسلة
                $stmt1 = $conn->prepare($sent_query);
                $stmt1->bind_param("i", $agent_id);
                $stmt1->execute();
                $sent_result = $stmt1->get_result()->fetch_assoc();
                $stmt1->close();
                
                // الشحنات المستلمة
                $stmt2 = $conn->prepare($received_query);
                $stmt2->bind_param("i", $agent_id);
                $stmt2->execute();
                $received_result = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
                
                // المبالغ المعلقة
                $stmt3 = $conn->prepare($pending_query);
                $stmt3->bind_param("i", $agent_id);
                $stmt3->execute();
                $pending_result = $stmt3->get_result()->fetch_assoc();
                $stmt3->close();
                
                $summary = [
                    'sent_shipments_count' => intval($sent_result['count'] ?? 0),
                    'received_shipments_count' => intval($received_result['count'] ?? 0),
                    'total_commission' => floatval($sent_result['total_commission'] ?? 0) + floatval($received_result['total_commission'] ?? 0),
                    'pending_commission' => floatval($pending_result['pending_amount'] ?? 0)
                ];
                
                echo json_encode(['status' => 'success', 'data' => $summary]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'معرف الوكيل غير صالح']);
            }
            break;
            
        // إدارة العملاء
        case 'get_all_customers_filtered':
            $search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';
            $governorate_id = isset($_POST['governorate_id']) ? intval($_POST['governorate_id']) : 0;
            
            $where_conditions = [];
            $params = [];
            $types = "";
            
            if (!empty($search_query)) {
                $where_conditions[] = "(c.name LIKE ? OR c.phone LIKE ?)";
                $search_param = "%$search_query%";
                $params[] = $search_param;
                $params[] = $search_param;
                $types .= "ss";
            }
            
            if ($governorate_id > 0) {
                $where_conditions[] = "c.governorate_id = ?";
                $params[] = $governorate_id;
                $types .= "i";
            }
            
            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
            
            $query = "
                SELECT c.*, g.name as governorate_name, a.name as area_name
                FROM customers c
                LEFT JOIN governorates g ON c.governorate_id = g.id
                LEFT JOIN areas a ON c.area_id = a.id
                $where_clause
                ORDER BY c.id DESC
            ";
            
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                echo json_encode(['status' => 0, 'message' => 'خطأ في تحضير الاستعلام: ' . $conn->error]);
                break;
            }
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $customers = [];
            while ($row = $result->fetch_assoc()) {
                $customers[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'data' => $customers]);
            break;

        case 'get_customer_settlement':
            header('Content-Type: application/json; charset=utf-8');
            $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
            if (!$customer_id) {
                echo json_encode(['status' => 'error', 'message' => 'معرف العميل غير صالح.']);
                break;
            }
            include_once 'customer_settlement_utils.php';
            $data = fetch_customer_settlement_data($conn, $customer_id);
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'settle_customer_payout':
            header('Content-Type: application/json; charset=utf-8');
            $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
            $parcel_ids = isset($_POST['parcel_ids']) ? $_POST['parcel_ids'] : [];
            $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
            $partial_map = isset($_POST['partial_amounts']) && is_array($_POST['partial_amounts']) ? $_POST['partial_amounts'] : [];

            if (!$customer_id || (!is_array($parcel_ids))) {
                echo json_encode(['status' => 'error', 'message' => 'بيانات غير مكتملة.']);
                break;
            }

            include_once 'customer_settlement_utils.php';
            include_once 'lib/PaymentService.php';

            $conn->begin_transaction();
            try {
                ensure_settlement_tables($conn);

                // اجلب الشحنات المختارة للتحقق والحساب
                $all_ids = array_map('intval', $parcel_ids);
                if (empty($all_ids) && empty($partial_map)) {
                    echo json_encode(['status' => 'error', 'message' => 'لا توجد شحنات للدفع.']);
                    break;
                }

                $selected_ids = $all_ids;
                if (!empty($partial_map)) {
                    foreach ($partial_map as $pid => $amt) {
                        $pid = intval($pid);
                        if (!in_array($pid, $selected_ids, true)) {
                            $selected_ids[] = $pid;
                        }
                    }
                }

                if (empty($selected_ids)) {
                    echo json_encode(['status' => 'error', 'message' => 'لا توجد شحنات صالحة.']);
                    break;
                }

                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                $sql = "SELECT id, client_id_fk, cod_amount, shipping_fees, shipping_payer, status, payment_status FROM parcels WHERE id IN ($placeholders) FOR UPDATE";
                $stmt = $conn->prepare($sql);
                $types = str_repeat('i', count($selected_ids));
                $stmt->bind_param($types, ...$selected_ids);
                $stmt->execute();
                $rs = $stmt->get_result();

                $valid_ids = [];
                $total_payout = 0.0;

                while ($row = $rs->fetch_assoc()) {
                    if ((int)$row['client_id_fk'] !== $customer_id) {
                        continue; // تجاهل شحنة ليست لهذا العميل
                    }
                    $parcelId = (int)$row['id'];
                    $net = compute_parcel_net_due($row);
                    $alreadyPaid = get_parcel_paid_to_customer($conn, $parcelId);
                    $remaining = max(0.0, $net - $alreadyPaid);

                    $payAmount = 0.0;
                    if (isset($partial_map[$parcelId])) {
                        $amt = (float)$partial_map[$parcelId];
                        if ($amt > 0) {
                            $payAmount = min($remaining, $amt);
                        }
                    } elseif (in_array($parcelId, $all_ids, true)) {
                        // دفع كامل للشحنات المحددة
                        $payAmount = $remaining;
                    }

                    if ($payAmount > 0) {
                        $valid_ids[$parcelId] = $payAmount;
                        $total_payout += $payAmount;
                    }
                }
                $stmt->close();

                if (empty($valid_ids)) {
                    echo json_encode(['status' => 'error', 'message' => 'لم يتم العثور على شحنات صالحة للدفع.']);
                    $conn->rollback();
                    break;
                }

                // سجل رأس التسوية
                $created_by = $_SESSION['login_name'] ?? 'system';
                $hdr = $conn->prepare('INSERT INTO customer_payouts (customer_id, total_amount, notes, created_by, created_at) VALUES (?, ?, ?, ?, NOW())');
                $hdr->bind_param('idss', $customer_id, $total_payout, $notes, $created_by);
                $hdr->execute();
                $payout_id = $conn->insert_id;
                $hdr->close();

                // تفاصيل التسوية وتحديث الشحنات
                $det = $conn->prepare('INSERT INTO customer_payout_details (payout_id, customer_id, parcel_id, amount, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                foreach ($valid_ids as $pid => $amt) {
                    $det->bind_param('iiids', $payout_id, $customer_id, $pid, $amt, $notes);
                    $det->execute();

                    // إذا اكتمل صافي المستحق، حدث حالة الشحنة إلى تم الدفع بنجاح
                    $info = PaymentService::getPaymentInfo($conn, (int)$pid);
                    $netTotal = (float)$info['total_to_collect'];
                    $paidSoFar = (float)$info['paid_amount'];
                    $delta = max(0.0, $netTotal - $paidSoFar);
                    $willBePaid = min($delta, $amt);
                    $newPaid = $paidSoFar + $willBePaid;
                    $newStatus = $newPaid >= $netTotal ? 5 : 6; // 5 paid, 6 partial
                    $newPaymentStatus = $newPaid >= $netTotal ? 'paid' : 'partial_paid';
                    $upd = $conn->prepare("UPDATE parcels SET paid_amount = ?, payment_status = ?, status = ?, paid_at = CASE WHEN ? = 'paid' THEN NOW() ELSE paid_at END WHERE id = ?");
                    $upd->bind_param('dsisi', $newPaid, $newPaymentStatus, $newStatus, $newPaymentStatus, $pid);
                    $upd->execute();
                    $upd->close();

                    // سجل تتبع
                    $trk = $conn->prepare('INSERT INTO parcel_tracks (parcel_id, status, date_created, remarks) VALUES (?, ?, NOW(), ?)');
                    $remark = 'تسوية عميل بقيمة ' . number_format($amt, 2) . ' ج.م';
                    $trk->bind_param('iis', $pid, $newStatus, $remark);
                    $trk->execute();
                    $trk->close();
                }
                $det->close();

                // سجل حركة مالية في صندوق الخزينة (خارجية)
                $branch_id = $_SESSION['login_branch_id'] ?? 0;
                $cash = $conn->prepare("INSERT INTO cashbox_transactions (branch_id, type, relation_id, amount, direction, notes, created_at) VALUES (?, 'customer', ?, ?, 'out', ?, NOW())");
                $cash->bind_param('iids', $branch_id, $customer_id, $total_payout, $notes);
                $cash->execute();
                $cash->close();

                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'تمت التسوية بنجاح', 'payout_id' => $payout_id, 'total' => $total_payout]);
            } catch (Throwable $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'فشل التسوية: ' . $e->getMessage()]);
            }
            break;
            
        default:
            // في حالة عدم تطابق أي إجراء، يتم إرجاع خطأ
            echo json_encode(['status' => 'error', 'message' => 'إجراء غير صالح.']);
            break;
    }
} catch (Exception $e) {
    // Catch any global errors and return a JSON response.
    // This is a safety net for unhandled exceptions.
    ob_end_clean(); // Clean any previous output
    header('Content-Type: application/json'); // Ensure header is correct
    echo json_encode(['status' => 'error', 'message' => 'حدث خطأ غير متوقع: ' . $e->getMessage()]);
}

// End output buffering and send output to the browser.
// This is the last thing in the file.
ob_end_flush();
?>