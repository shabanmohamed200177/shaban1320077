<?php
// new_parcel.php

// 1. حل مشكلة تكرار session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التأكد من وجود ملف الاتصال بقاعدة البيانات
if (!isset($conn)) {
    include 'db_connect.php';
}

// -----------------------------------------------------------
// منطق معالجة طلبات AJAX (search_customers, get_areas_by_gov)
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'search_customers') {
        try {
            $query = '%' . trim($_POST['query']) . '%';
            // استخدام جدول 'customers' بدلاً من 'clients'
            $sql = "SELECT id, name, phone, address, governorate_id, area_id FROM customers WHERE name LIKE ? OR phone LIKE ? LIMIT 10";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
            }
            $stmt->bind_param("ss", $query, $query);
            $stmt->execute();
            $result = $stmt->get_result();
            $customers = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $customers]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_areas_by_gov') {
        try {
            $gov_id = intval($_POST['gov_id']);
            $sql = "SELECT id, name FROM areas WHERE governorate_id = ? ORDER BY name ASC";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
            }
            $stmt->bind_param("i", $gov_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $areas = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $areas]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
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
            echo json_encode(['status' => 'success', 'data' => $reasons]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_parcel_statuses') {
        try {
            $sql = "SELECT id, name_ar FROM parcel_status ORDER BY id ASC";
            $res = $conn->query($sql);
            $list = [];
            if ($res) {
                while($row = $res->fetch_assoc()) { $list[] = $row; }
            }
            echo json_encode(['status' => 'success', 'data' => $list]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// -----------------------------------------------------------
// منطق جلب البيانات لملء النموذج (في وضع التعديل)
// -----------------------------------------------------------
$parcel_data = [];
$is_edit_mode = false;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $parcel_id = intval($_GET['id']);
    // جلب كافة الأعمدة المطلوبة من جدول parcels
    $query = "SELECT * FROM parcels WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $parcel_data = $result->fetch_assoc();
            $is_edit_mode = true;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------
// جلب البيانات الأساسية للقوائم المنسدلة
// -----------------------------------------------------------
$agents_result = $conn->query("SELECT id, company_name FROM agents WHERE status = 1 ORDER BY company_name ASC");
$agents = [];
if ($agents_result) {
    while ($row = $agents_result->fetch_assoc()) {
        $agents[] = $row;
    }
}

$governorates_result = $conn->query("SELECT id, name FROM governorates ORDER BY name ASC");
$governorates = [];
if ($governorates_result) {
    while ($g = $governorates_result->fetch_assoc()) {
        $governorates[] = $g;
    }
}

$couriers_result = $conn->query("SELECT id, name FROM couriers WHERE status = 1 ORDER BY name ASC");
$couriers = [];
if ($couriers_result) {
    while ($c = $couriers_result->fetch_assoc()) {
        $couriers[] = $c;
    }
}

$status_result = $conn->query("SELECT id, name_ar FROM parcel_status ORDER BY id ASC");
$parcel_statuses = [];
if ($status_result) {
    while ($s = $status_result->fetch_assoc()) {
        $parcel_statuses[] = $s;
    }
}

?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit_mode ? 'تعديل شحنة' : 'إضافة شحنة جديدة'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb !important; font-family: 'Tajawal', Arial, sans-serif; direction: rtl; }
        .page-title { font-weight: bold; font-size: 2rem; color: #1976d2; margin-bottom: 15px; text-align: right; letter-spacing: 1px; }
        .modern-btn { border-radius: 2rem !important; font-weight: 600; font-size: 1.07rem; box-shadow: 0 2px 10px #38f9d733; padding: 0.7rem 2rem; transition: all 0.2s; }
        .modern-btn:hover { opacity: 0.95; }
        .box-section { background: #fff; border-radius: 18px; box-shadow: 0 4px 18px #ddeafc33; padding: 22px; margin-bottom: 15px; position: relative; text-align: right; }
        .box-title { font-size: 1.25em; color: #1565c0; font-weight: bold; margin-bottom: 12px; display: flex; align-items: center; gap: 9px; justify-content: flex-end; }
        .form-label { font-weight: 500; color: #1976d2; margin-bottom: 5px; }
        .form-control, .form-select { border-radius: 1.2rem !important; text-align: right; margin-bottom: 10px; }
        .box-icon { font-size: 1.33em; color: #00bcd4; margin-left: 7px; margin-right: 0; }
        .autocomplete-suggestions { position: absolute; background: #fff; border: 1px solid #ddd; border-radius: 8px; max-height: 220px; overflow-y: auto; z-index: 99; width: 100%; box-shadow: 0 5px 15px #eee; }
        .autocomplete-suggestion { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee; text-align: right; }
        .autocomplete-suggestion:last-child { border-bottom: none; }
        .autocomplete-suggestion:hover { background: #e3f2fd; }
        .readonly-field { background-color: #e9ecef !important; }
        .form-group { margin-bottom: 1rem; }
        /* Added spacing between form groups */
        .form-group + .form-group { margin-top: 15px; } 
        @media (max-width: 600px) { .page-title { font-size: 1.3rem; } }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="page-title"><i class="fa-solid fa-truck-fast"></i> <?php echo $is_edit_mode ? 'تعديل شحنة' : 'إضافة شحنة جديدة'; ?></div>

            <form id="manage-parcel-form" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="save_parcel">
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($parcel_data['id']); ?>">
                <?php endif; ?>
                
                <div class="box-section text-center">
                    <div class="row g-3 justify-content-center">
                        <div class="col-sm-6">
                            <input type="radio" class="btn-check" name="shipment_direction" id="to_agent" value="to_agent" autocomplete="off" <?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? 'to_agent') == 'to_agent') ? 'checked' : (!$is_edit_mode ? 'checked' : ''); ?>>
                            <label class="btn btn-outline-primary modern-btn w-100" for="to_agent">
                                <i class="fa-solid fa-arrow-right"></i> إرسال شحنة إلى وكيل
                            </label>
                        </div>
                        <div class="col-sm-6">
                            <input type="radio" class="btn-check" name="shipment_direction" id="from_agent" value="from_agent" autocomplete="off" <?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? '') == 'from_agent') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary modern-btn w-100" for="from_agent">
                                <i class="fa-solid fa-arrow-left"></i> استلام شحنة من وكيل
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="box-section" id="sender_section">
                            <div class="box-title" id="sender_title"><span class="box-icon"><i class="fa-solid fa-paper-plane"></i></span> بيانات الراسل (عميلك)</div>
                            
                            <div id="client_search_container" class="form-group" style="position:relative;">
                                <label class="form-label">ابحث عن العميل (الاسم/الهاتف)</label>
                                <input type="text" name="client_search" id="client_search" class="form-control" autocomplete="off" placeholder="اكتب اسم العميل أو رقمه" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['sender_name']) : ''; ?>">
                                <div id="client_suggestions" class="autocomplete-suggestions" style="display:none;"></div>
                                <button type="button" id="reset_selected_customer" class="btn btn-warning btn-sm mt-2 <?php echo $is_edit_mode ? '' : 'd-none'; ?>">تغيير العميل</button>
                            </div>

                            <hr>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="fa-regular fa-address-card"></i> اسم الراسل</label>
                                <input type="text" name="sender_name" id="sender_name" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['sender_name']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-mobile-screen"></i> رقم جوال الراسل</label>
                                <input type="text" name="sender_phone" id="sender_phone" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['sender_phone']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-location-dot"></i> عنوان الراسل</label>
                                <input type="text" name="sender_address" id="sender_address" class="form-control" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['sender_address']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-map"></i> المحافظة</label>
                                <select name="sender_governorate_id" id="sender-governorate" class="form-control" required>
                                    <option value="">اختر المحافظة</option>
                                    <?php foreach ($governorates as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g['id']); ?>" <?php echo ($is_edit_mode && $parcel_data['sender_governorate_id'] == $g['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-location-crosshairs"></i> المنطقة</label>
                                <select name="sender_area_id" id="sender-zone" class="form-control" required>
                                    <option value="">اختر المنطقة</option>
                                    <?php if ($is_edit_mode && $parcel_data['sender_governorate_id']): 
                                        $areas_result = $conn->query("SELECT id, name FROM areas WHERE governorate_id = " . intval($parcel_data['sender_governorate_id']) . " ORDER BY name ASC");
                                        while($a = $areas_result->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($a['id']); ?>" <?php echo ($parcel_data['sender_area_id'] == $a['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['name']); ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <input type="hidden" name="client_id_fk" id="client_id_fk" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['client_id_fk'] ?? '') : ''; ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="box-section" id="receiver_section">
                            <div class="box-title" id="receiver_title"><span class="box-icon"><i class="fa-solid fa-user-plus"></i></span> بيانات المستلم (عميل الوكيل)</div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-regular fa-address-card"></i> اسم المستلم</label>
                                <input type="text" name="recipient_name" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['recipient_name']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-mobile-screen"></i> رقم جوال المستلم</label>
                                <input type="text" name="recipient_phone" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['recipient_phone']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-location-dot"></i> تفاصيل العنوان</label>
                                <input type="text" name="recipient_address" class="form-control" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['recipient_address']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-map"></i> المحافظة</label>
                                <select name="recipient_governorate_id" id="receiver-governorate" class="form-control" required>
                                    <option value="">اختر المحافظة</option>
                                    <?php foreach ($governorates as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g['id']); ?>" <?php echo ($is_edit_mode && $parcel_data['recipient_governorate_id'] == $g['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-location-crosshairs"></i> المنطقة</label>
                                <select name="recipient_area_id" id="receiver-zone" class="form-control" required>
                                    <option value="">اختر المنطقة</option>
                                    <?php if ($is_edit_mode && $parcel_data['recipient_governorate_id']): 
                                        $areas_result = $conn->query("SELECT id, name FROM areas WHERE governorate_id = " . intval($parcel_data['recipient_governorate_id']) . " ORDER BY name ASC");
                                        while($a = $areas_result->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($a['id']); ?>" <?php echo ($parcel_data['recipient_area_id'] == $a['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['name']); ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box-section">
                    <div class="box-title"><span class="box-icon"><i class="fa-solid fa-route"></i></span> التوجيه والإسناد</div>
                    <div id="routing_to_agent_section" class="<?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? 'to_agent') == 'from_agent') ? 'd-none' : ''; ?>">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user-tie"></i> الوكيل المُرَسل إليه</label>
                            <select name="agent_id_to" id="agent_id_to" class="form-control" <?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? 'to_agent') == 'to_agent') ? 'required' : ''; ?>>
                                <option value="">اختر الوكيل</option>
                                <?php foreach ($agents as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a['id']); ?>" <?php echo ($is_edit_mode && $parcel_data['agent_id'] == $a['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="routing_from_agent_section" class="<?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? 'to_agent') == 'to_agent') ? 'd-none' : ''; ?>">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user-tie"></i> الوكيل الذي استلمت منه</label>
                            <select name="agent_id_from" id="agent_id_from" class="form-control" <?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? '') == 'from_agent') ? 'required' : ''; ?>>
                                <option value="">اختر الوكيل</option>
                                <?php foreach ($agents as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a['id']); ?>" <?php echo ($is_edit_mode && $parcel_data['agent_id'] == $a['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-person-biking"></i> إسناد للمندوب الداخلي</label>
                            <select name="courier_id" id="courier_id" class="form-control" <?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? '') == 'from_agent') ? 'required' : ''; ?>>
                                <option value="">اختر المندوب</option>
                                <?php foreach ($couriers as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['id']); ?>" <?php echo ($is_edit_mode && $parcel_data['courier_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="box-section">
                    <div class="box-title"><span class="box-icon"><i class="fa-solid fa-box"></i></span> تفاصيل الشحنة والبيانات المالية</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">وصف المحتويات</label>
                                <input type="text" name="contents" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['contents']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">عدد القطع</label>
                                <input type="number" name="pieces" class="form-control" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['pieces']) : '1'; ?>" min="1" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">الوزن الفعلي (KG)</label>
                                <input type="number" step="0.01" name="weight" class="form-control" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['weight']) : '0'; ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">القيمة المُعلن عنها</label>
                                <input type="number" step="0.01" name="declared_value" class="form-control" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['declared_value']) : '0'; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">حالة الشحنة</label>
                                <select name="status" id="parcel_status" class="form-select" required>
                                    <option value="">اختر الحالة</option>
                                    <?php foreach ($parcel_statuses as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s['id']); ?>" <?php echo ($is_edit_mode && $parcel_data['status'] == $s['id']) ? 'selected' : (($s['id'] == 1 && !$is_edit_mode) ? 'selected' : ''); ?>><?php echo htmlspecialchars($s['name_ar']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <button type="button" class="btn btn-success btn-sm" id="btn_change_status_now">
                                        <i class="fas fa-sync-alt"></i> تغيير الحالة الآن
                                    </button>
                                    <span class="badge bg-secondary" id="np_current_status_badge">
                                        <?php
                                            if ($is_edit_mode) {
                                                $sv = intval($parcel_data['status'] ?? 1);
                                                $sn = 'غير معروف';
                                                foreach ($parcel_statuses as $ps) { if (intval($ps['id']) === $sv) { $sn = $ps['name_ar']; break; } }
                                                echo htmlspecialchars($sn);
                                            } else {
                                                echo 'قيد التنفيذ';
                                            }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="note" class="control-label">ملاحظات حول الشحنة</label>
                                <textarea class="form-control simple-textarea" id="note" name="note" rows="3" placeholder="اكتب ملاحظاتك حول الشحنة هنا..."><?php echo $is_edit_mode ? htmlspecialchars($parcel_data['notes'] ?? '') : ''; ?></textarea>
                                                            </div>
                                <div class="box-section mt-3">
                                    <div class="box-title"><span class="box-icon"><i class="fa-solid fa-sack-dollar"></i></span> الملخص المالي</div>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">الإجمالي المطلوب من المستلم</label>
                                            <input type="text" id="np_total_to_collect" class="form-control readonly-field" readonly value="0.00">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">المدفوع حتى الآن</label>
                                            <input type="text" id="np_paid_amount" class="form-control readonly-field" readonly value="0.00">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">المتبقي</label>
                                            <input type="text" id="np_remaining" class="form-control readonly-field" readonly value="0.00">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">حالة الدفع</label>
                                            <input type="text" id="np_payment_status" class="form-control readonly-field" readonly value="غير مدفوع">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                            <!-- الحقول المالية لإرسال شحنة إلى وكيل -->
                            <div id="to_agent_financial_fields" class="<?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? 'to_agent') == 'from_agent') ? 'd-none' : ''; ?>">
                                <div class="form-group">
                                    <label class="form-label">تكلفة الشحن (Shipping Cost)</label>
                                    <input type="number" step="0.01" name="shipping_cost" id="shipping_cost" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['shipping_fees']) : '0'; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">تكلفة الوكيل (Agent Cost)</label>
                                    <input type="number" step="0.01" name="agent_cost" id="agent_cost" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['agent_share']) : '0'; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">مبلغ التحصيل عند الاستلام (COD Amount)</label>
                                    <input type="number" step="0.01" name="cod_amount" id="cod_amount" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['cod_amount']) : '0'; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">من يتحمل تكلفة الشحن؟</label>
                                    <select name="shipping_payer" id="shipping_payer" class="form-select">
                                        <option value="sender" <?php echo ($is_edit_mode && ($parcel_data['shipping_payer'] ?? 'sender') == 'sender') ? 'selected' : ''; ?>>الراسل</option>
                                        <option value="recipient" <?php echo ($is_edit_mode && ($parcel_data['shipping_payer'] ?? '') == 'recipient') ? 'selected' : ''; ?>>المستلم</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">الإجمالي المطلوب تحصيله من المستلم</label>
                                    <input type="text" id="total_to_collect" class="form-control readonly-field bg-success text-white" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" data-bs-toggle="tooltip" data-bs-placement="top" title="المبلغ الذي سيحصل عليه العميل (الراسل) بعد خصم تكلفة الشحن">المبلغ الصافي للعملية <i class="fa fa-info-circle"></i></label>
                                    <input type="text" id="net_amount" class="form-control readonly-field bg-info text-white" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="net_profit" class="control-label">صافي ربحي من الشحنة</label>
                                    <input type="text" class="form-control readonly-field" id="net_profit" name="net_profit" readonly>
                                </div>
                            </div>

                            <!-- الحقول المالية لاستلام شحنة من وكيل -->
                            <div id="from_agent_financial_fields" class="<?php echo ($is_edit_mode && ($parcel_data['shipment_direction'] ?? 'to_agent') == 'from_agent') ? '' : 'd-none'; ?>">
                                <div class="form-group">
                                    <label class="form-label">مبلغ التحصيل عند الاستلام (COD Amount)</label>
                                    <input type="number" step="0.01" name="cod_amount_from_agent" id="cod_amount_from_agent" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['cod_amount']) : '0'; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">عمولة المندوب</label>
                                    <input type="number" step="0.01" name="courier_commission" id="courier_commission" class="form-control" required value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['delivery_agent_fee']) : '30'; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">ربح الشركة من عمولة المندوب (مبلغ ثابت)</label>
                                    <input type="number" step="0.01" name="company_cut_amount" id="company_cut_amount" class="form-control" value="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">عمولة المندوب بعد خصم ربح الشركة</label>
                                    <input type="text" id="courier_commission_after_company" class="form-control readonly-field bg-warning text-dark" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">المبلغ الصافي للعملية (المدفوع للوكيل)</label>
                                    <input type="text" id="net_amount_from_agent" class="form-control readonly-field bg-info text-white" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="net_profit_from_agent" class="control-label">صافي ربحي من الشحنة</label>
                                    <input type="text" class="form-control readonly-field" id="net_profit_from_agent" name="net_profit_from_agent" readonly>
                                </div>
                                <!-- حقل مخفي لـ cod_amount في حالة from_agent -->
                                <input type="hidden" name="cod_amount" id="cod_amount_hidden" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['cod_amount']) : '0'; ?>">
                                <!-- حقل مخفي لـ shipping_cost في حالة from_agent -->
                                <input type="hidden" name="shipping_cost" id="shipping_cost_hidden" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['shipping_fees']) : '0'; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">ملاحظات مالية</label>
                                <textarea name="financial_notes" class="form-control"><?php echo $is_edit_mode ? htmlspecialchars($parcel_data['financial_notes'] ?? '') : ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">طريقة الدفع</label>
                                <select name="payment_type" id="payment_type" class="form-select">
                                    <option value="unpaid" <?php echo ($is_edit_mode && ($parcel_data['payment_status'] ?? 'unpaid') == 'unpaid') ? 'selected' : ''; ?>>غير مدفوع</option>
                                    <option value="full" <?php echo ($is_edit_mode && ($parcel_data['payment_status'] ?? '') == 'paid') ? 'selected' : ''; ?>>دفع كامل</option>
                                    <option value="partial" <?php echo ($is_edit_mode && ($parcel_data['payment_status'] ?? '') == 'partial_paid') ? 'selected' : ''; ?>>دفع جزئي</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12 text-center">
                        <button type="submit" class="btn btn-primary modern-btn"><i class="fas fa-save"></i> <?php echo $is_edit_mode ? 'تحديث الشحنة' : 'حفظ الشحنة وطباعة البوليصة'; ?></button>
                    </div>
                </div>
                
                <!-- حقول خفية للدفع الجزئي والمرتجعات -->
                <input type="hidden" name="partial_paid" id="partial_paid_hidden" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['partial_paid'] ?? '') : ''; ?>">
                <input type="hidden" name="partial_note" id="partial_note_hidden" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['partial_note'] ?? '') : ''; ?>">
                <input type="hidden" name="returned_pieces" id="returned_pieces_hidden" value="<?php echo $is_edit_mode ? htmlspecialchars($parcel_data['returned_pieces'] ?? 0) : 0; ?>">
            </form>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Modal: تعديل بيانات الدفع الجزئي والمرتجعات -->
<div class="modal fade" id="partialPaymentEditModal" tabindex="-1" aria-labelledby="partialPaymentEditModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="partialPaymentEditModalLabel">تسجيل دفع جزئي/مرتجعات</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 text-muted">الإجمالي المطلوب تحصيله: <strong id="partial_modal_total">0.00</strong></div>
        <div class="mb-3">
            <label class="form-label">المبلغ المدفوع الآن</label>
            <input type="number" step="0.01" class="form-control" id="partial_amount_input" placeholder="أدخل المبلغ المدفوع">
        </div>
        <div class="mb-3">
            <label class="form-label">عدد القطع المرتجعة</label>
            <input type="number" min="0" step="1" class="form-control" id="returned_pieces_input" placeholder="0">
        </div>
        <div class="mb-3">
            <label class="form-label">ملاحظة</label>
            <textarea class="form-control" id="partial_note_input" rows="2" placeholder="اكتب ملاحظة"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="partial_modal_cancel_btn" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-primary" id="partial_modal_save_btn" data-bs-dismiss="modal" onclick="window.__partialSave&&window.__partialSave()">حفظ</button>
      </div>
    </div>
  </div>
  </div>

<!-- Modal: تحديد أسباب التأجيل أو الرفض -->
<div class="modal fade" id="statusReasonModal" tabindex="-1" aria-labelledby="statusReasonModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="statusReasonModalLabel">تحديد سبب الحالة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label" id="reason-label">اختر السبب:</label>
            <select class="form-control" id="status_reason_select">
                <option value="">-- اختر السبب --</option>
            </select>
        </div>
        <div class="mb-3 d-none" id="postpone-date-container">
            <label class="form-label">تاريخ التأجيل</label>
            <input type="date" class="form-control" id="postpone_date_input" />
            <small class="text-muted">اختر اليوم الذي سيتم التأجيل إليه.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">ملاحظة إضافية (اختيارية)</label>
            <textarea class="form-control" id="status_reason_note" rows="3" placeholder="اكتب ملاحظة إضافية حول السبب..."></textarea>
        </div>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span id="status-help-text">سيتم حفظ السبب مع الحالة وإضافته لسجل تتبع الشحنة.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-primary" id="save_status_reason_btn">حفظ السبب</button>
      </div>
    </div>
  </div>
</div>

<!-- حقول مخفية لحفظ بيانات الأسباب -->
<input type="hidden" name="status_reason_id" id="status_reason_id" value="">
<input type="hidden" name="status_reason_note" id="status_reason_note_hidden" value="">
<input type="hidden" name="postpone_date" id="postpone_date_hidden" value="">

<script>
$(document).ready(function(){
    function parseLocaleNumber(input) {
        const s = String(input ?? '').trim();
        if (!s) return 0;
        const map = { '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9',
                      '۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9' };
        const normalized = s.replace(/[٠-٩۰-۹]/g, d => map[d]).replace(/[^\d.\-]/g, '');
        const n = Number(normalized);
        return Number.isFinite(n) ? n : 0;
    }
    const shipmentDirectionSelector = $('input[name="shipment_direction"]');
    const senderTitle = $('#sender_title');
    const receiverTitle = $('#receiver_title');
    const routingToAgent = $('#routing_to_agent_section');
    const routingFromAgent = $('#routing_from_agent_section');
    const agentCostLabel = $('#agent_cost_label');
    const agentIdTo = $('#agent_id_to');
    const agentIdFrom = $('#agent_id_from');
    const courierId = $('#courier_id');
    const clientSearch = $('#client_search');
    const clientSuggestions = $('#client_suggestions');
    const resetCustomerBtn = $('#reset_selected_customer');
    
    // Initial load for edit mode
    const isEditMode = <?php echo json_encode($is_edit_mode); ?>;
    if (isEditMode) {
        toggleSenderFields(true);
        // Manually trigger change for sender-governorate and receiver-governorate to load areas
        $('#sender-governorate').trigger('change');
        $('#receiver-governorate').trigger('change');
        // Set timeout to ensure areas are loaded before setting selected area
        setTimeout(() => {
            $('#sender-zone').val(<?php echo json_encode($parcel_data['sender_area_id'] ?? ''); ?>);
            $('#receiver-zone').val(<?php echo json_encode($parcel_data['recipient_area_id'] ?? ''); ?>);
            
            // Set values for from_agent fields if applicable
            const shipmentDirection = <?php echo json_encode($parcel_data['shipment_direction'] ?? 'to_agent'); ?>;
            if (shipmentDirection === 'from_agent') {
                $('#cod_amount_from_agent').val(<?php echo json_encode($parcel_data['cod_amount'] ?? '0'); ?>);
                $('#courier_commission').val(<?php echo json_encode($parcel_data['delivery_agent_fee'] ?? '30'); ?>);
            }
            
            calculateFinancials(); // Recalculate after all fields are set
        }, 800); // Increased timeout for safety
    }

    function toggleSenderFields(readonly = false) {
        if(readonly) {
            $('#sender_name, #sender_phone, #sender_address').prop('readonly', true).addClass('readonly-field');
            $('#sender-governorate, #sender-zone').prop('disabled', true);
            clientSearch.prop('readonly', true).addClass('readonly-field');
            resetCustomerBtn.removeClass('d-none');
        } else {
            $('#sender_name, #sender_phone, #sender_address').prop('readonly', false).removeClass('readonly-field');
            $('#sender-governorate, #sender-zone').prop('disabled', false);
            clientSearch.prop('readonly', false).removeClass('readonly-field');
            $('#client_id_fk').val('');
            resetCustomerBtn.addClass('d-none'); // Should be hidden when not selected
        }
    }

    function toggleFormSections() {
        const direction = shipmentDirectionSelector.filter(':checked').val();
        
        // Reset required/disabled states for routing selects
        agentIdTo.prop('required', false).prop('disabled', true);
        agentIdFrom.prop('required', false).prop('disabled', true);
        courierId.prop('required', false).prop('disabled', true);
        
        // Toggle financial fields visibility
        const toAgentFields = $('#to_agent_financial_fields');
        const fromAgentFields = $('#from_agent_financial_fields');
        
        if (direction === 'to_agent') {
            senderTitle.html('<span class="box-icon"><i class="fa-solid fa-paper-plane"></i></span> بيانات الراسل (عميلك)');
            receiverTitle.html('<span class="box-icon"><i class="fa-solid fa-user-plus"></i></span> بيانات المستلم (عميل الوكيل)');
            
            routingToAgent.removeClass('d-none');
            routingFromAgent.addClass('d-none');
            
            // Show to_agent financial fields
            toAgentFields.removeClass('d-none');
            fromAgentFields.addClass('d-none');
            
            agentIdTo.prop('required', true).prop('disabled', false);
                $('#parcel_status').val(1); // قيد التنفيذ
        } else { // 'from_agent'
            senderTitle.html('<span class="box-icon"><i class="fa-solid fa-paper-plane"></i></span> بيانات الراسل (عميل الوكيل)');
            receiverTitle.html('<span class="box-icon"><i class="fa-solid fa-user-plus"></i></span> بيانات المستلم (عميلك في منطقتك)');
            
            routingToAgent.addClass('d-none');
            routingFromAgent.removeClass('d-none');

            // Show from_agent financial fields
            fromAgentFields.removeClass('d-none');
            toAgentFields.addClass('d-none');
            
            agentIdFrom.prop('required', true).prop('disabled', false);
            courierId.prop('required', true).prop('disabled', false);
                $('#parcel_status').val(2); // تم تسليمها للمندوب
        }
    }

    function calculateFinancials() {
        const direction = shipmentDirectionSelector.filter(':checked').val();
        
        if (direction === 'to_agent') {
            // حساب القيم لإرسال شحنة إلى وكيل
            const shippingCost = parseLocaleNumber($('#shipping_cost').val());
            const agentCost = parseLocaleNumber($('#agent_cost').val());
            const codAmount = parseLocaleNumber($('#cod_amount').val());
            const shippingPayer = $('#shipping_payer').val();
            let totalToCollect, netAmount, netProfit;

            // Calculate Total to Collect
            if (shippingPayer === 'recipient') {
                totalToCollect = codAmount + shippingCost;
            } else {
                totalToCollect = codAmount;
            }

            // Calculate Net Amount
            if (shippingPayer === 'sender') {
                netAmount = codAmount - shippingCost;
            } else {
                netAmount = codAmount;
            }
            
            // Calculate Net Profit
            netProfit = shippingCost - agentCost;

            $('#total_to_collect').val(totalToCollect.toFixed(2));
            $('#net_amount').val(netAmount.toFixed(2));
            $('#net_profit').val(netProfit.toFixed(2));
                        } else if (direction === 'from_agent') {
                    // حساب القيم لاستلام شحنة من وكيل
                    const codAmount = parseLocaleNumber($('#cod_amount_from_agent').val());
                    const courierCommission = parseLocaleNumber($('#courier_commission').val());
                    const companyCutAmount = parseLocaleNumber($('#company_cut_amount').val());
                    const courierAfterCompany = Math.max(0, courierCommission - companyCutAmount);
                    
                    // المبلغ الصافي للعملية (المدفوع للوكيل): يُخصم كامل عمولة المندوب فقط
                    const netAmount = codAmount - courierCommission;
                    
                    // صافي ربح الشركة: الجزء المخصوم من عمولة المندوب
                    const netProfit = companyCutAmount;

                    $('#net_amount_from_agent').val(netAmount.toFixed(2));
                    $('#net_profit_from_agent').val(netProfit.toFixed(2));
                    $('#courier_commission_after_company').val(courierAfterCompany.toFixed(2));
                }
    }

    // تحميل حالات الشحنة ديناميكيًا لضمان ظهور جميع الحالات
    function loadParcelStatuses() {
        $.ajax({
            url: 'new_parcel.php',
            type: 'POST',
            data: { action: 'get_parcel_statuses' },
            dataType: 'json',
            success: function(resp){
                if (resp && resp.status === 'success') {
                    const sel = $('#parcel_status');
                    const current = sel.val();
                    sel.empty();
                    resp.data.forEach(function(st){
                        const opt = $('<option>').val(st.id).text(st.name_ar);
                        sel.append(opt);
                    });
                    if (current) sel.val(current);
                    if (!sel.val()) sel.val('1'); // افتراضي قيد التنفيذ
                }
            }
        });
    }

    loadParcelStatuses();

    // فتح مودال الدفع الجزئي عند اختيار حالة "تم الدفع جزئي" (6)
    let lastStatusVal = $('#parcel_status').val();
    // استخدم تفويض الحدث لضمان الاشتغال حتى لو أعيد بناء العنصر
    $(document).on('change', '#parcel_status', function() {
        const val = String($(this).val());
        const text = String($('#parcel_status option:selected').text() || '').trim();
        // تحديث شارة الحالة الحالية
        $('#np_current_status_badge').text(text || '');
        // يدعم حالتين: لو كان رقم الحالة "6" أو كان نص الحالة يحتوي كلمة جزئي (للتوافق مع اختلاف IDs)
        const isPartial = (val === '6') || text.indexOf('جزئي') !== -1 || text.indexOf('partial') !== -1;
        if (isPartial) {
            // جهز قيم افتراضية للمودال
            const currentTotalToCollect = (function(){
                const dir = shipmentDirectionSelector.filter(':checked').val();
                if (dir === 'to_agent') {
                    const cod = parseLocaleNumber($('#cod_amount').val());
                    const ship = parseLocaleNumber($('#shipping_cost').val());
                    const payer = $('#shipping_payer').val();
                    return payer === 'recipient' ? (cod + ship) : cod;
                } else {
                    // from_agent: التحصيل من المستلم هو COD فقط
                    return parseLocaleNumber($('#cod_amount_from_agent').val());
                }
            })();
            $('#partial_amount_input').val( Math.max(0, (parseFloat($('#partial_paid_hidden').val())||0)) || '' );
            $('#returned_pieces_input').val( parseInt($('#returned_pieces_hidden').val()||0,10) );
            $('#partial_modal_total').text(currentTotalToCollect.toFixed(2));
            const el = document.getElementById('partialPaymentEditModal');
            if (el) {
                // استخدم jQuery لفتح المودال لضمان التوافق
                $(el).modal('show');
            } else {
                alert('أدخل المبلغ المدفوع وعدد القطع المرتجعة ثم احفظ.');
            }
        }
        
        // فتح مودال الأسباب للتأجيل (7) أو الرفض (8)
        const needsReason = (val === '7') || (val === '8');
        if (needsReason && val !== lastStatusVal) {
            // تحديد نوع الحالة والأسباب المناسبة
            if (val === '7') {
                loadStatusReasons(7, 'تحديد سبب التأجيل', 'اختر سبب تأجيل الشحنة:');
            } else if (val === '8') {
                loadStatusReasons(8, 'تحديد سبب الرفض', 'اختر سبب رفض الشحنة:');
            }
        }
        
        lastStatusVal = val;
    });

    // واجهات عامة تُستدعى من الأزرار (لضمان العمل حتى إن فشلت ربطات jQuery)
    window.__partialSave = function() {
        const amount = parseLocaleNumber($('#partial_amount_input').val());
        const pieces = parseInt($('#returned_pieces_input').val() || 0, 10);
        const note = $('#partial_note_input').val() || '';
        $('#partial_paid_hidden').val(amount.toFixed(2));
        $('#returned_pieces_hidden').val(pieces);
        $('#partial_note_hidden').val(note);
        // اضبط نوع الدفع إلى جزئي لتناسق الحالة
        $('#payment_type').val('partial');
        // إشعار بصري للمستخدم
        // أظهر رسالة تحت حقل الحالة
        const box = document.getElementById('partial_feedback');
        if (box) {
            box.classList.remove('d-none');
            box.classList.add('alert-success');
            box.textContent = 'تم تسجيل الدفع الجزئي بنجاح';
            setTimeout(()=>{ box.classList.add('d-none'); }, 2000);
        } else {
            alert('تم تسجيل الدفع الجزئي بنجاح');
        }
        
        // أغلق المودال
        const el = document.getElementById('partialPaymentEditModal');
        if (el) {
            $(el).modal('hide');
        }
    };

    window.__partialCancel = function() {
        const el = document.getElementById('partialPaymentEditModal');
        if (el) {
            $(el).modal('hide');
        }
    };

    // دعم إغلاق المودال بزر X في الهيدر
    $('#partialPaymentEditModal').on('hidden.bs.modal', function () {
        // لا شيء خاص عند الإغلاق، فقط ضمان عدم منع الإغلاق
    });

    // دوال نظام أسباب الحالات
    function loadStatusReasons(statusId, modalTitle, reasonLabel) {
        // تحديث عنوان المودال والنصوص
        $('#statusReasonModalLabel').text(modalTitle);
        $('#reason-label').text(reasonLabel);
        
        // تنظيف القائمة المنسدلة
        const reasonSelect = $('#status_reason_select');
        reasonSelect.empty().append('<option value="">-- اختر السبب --</option>');
        
        // تحميل الأسباب من الخادم
        $.ajax({
            url: 'new_parcel.php',
            type: 'POST',
            data: { 
                action: 'get_status_reasons', 
                status_id: statusId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(reason => {
                        const code = reason.reason_code || '';
                        reasonSelect.append(`<option value="${reason.id}" data-code="${code}">${reason.reason_text}</option>`);
                    });
                    
                    // فتح المودال
                    $('#statusReasonModal').modal('show');
                } else {
                    console.error('لم يتم العثور على أسباب للحالة:', statusId);
                    // يمكن المتابعة بدون سبب
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('خطأ في تحميل أسباب الحالة:', textStatus, errorThrown);
                // يمكن المتابعة بدون سبب
            }
        });
    }

    // إظهار/إخفاء حقل تاريخ التأجيل بناءً على كود السبب
    $(document).on('change', '#status_reason_select', function(){
        const code = $('#status_reason_select option:selected').data('code');
        if (String(code).toUpperCase() === 'DEFER_TO_DATE') {
            $('#postpone-date-container').removeClass('d-none');
        } else {
            $('#postpone-date-container').addClass('d-none');
            $('#postpone_date_input').val('');
        }
    });

    // حفظ السبب المختار
    $('#save_status_reason_btn').on('click', function() {
        const selectedReasonId = $('#status_reason_select').val();
        const reasonNote = $('#status_reason_note').val().trim();
        const selectedCode = $('#status_reason_select option:selected').data('code');
        
        if (!selectedReasonId) {
            alert('يرجى اختيار سبب للحالة');
            return;
        }
        // تحقق من إدخال تاريخ التأجيل إذا كان السبب يتطلب ذلك
        if (String(selectedCode).toUpperCase() === 'DEFER_TO_DATE') {
            const postponeDate = $('#postpone_date_input').val();
            if (!postponeDate) {
                alert('يرجى اختيار تاريخ التأجيل');
                return;
            }
            $('#postpone_date_hidden').val(postponeDate);
        } else {
            $('#postpone_date_hidden').val('');
        }
        
        // حفظ القيم في الحقول المخفية
        $('#status_reason_id').val(selectedReasonId);
        $('#status_reason_note_hidden').val(reasonNote);
        
        // عرض رسالة تأكيد
        const selectedText = $('#status_reason_select option:selected').text();
        const confirmMsg = 'تم تسجيل السبب: ' + selectedText + (reasonNote ? '\\nالملاحظة: ' + reasonNote : '');
        
        // إغلاق المودال
        $('#statusReasonModal').modal('hide');
        
        // عرض تأكيد
        setTimeout(() => {
            alert(confirmMsg);
        }, 300);
    });

    // إعادة تعيين قيم المودال عند إغلاقه
    $('#statusReasonModal').on('hidden.bs.modal', function () {
        $('#status_reason_select').val('');
        $('#status_reason_note').val('');
    });

    shipmentDirectionSelector.on('change', function() {
        toggleFormSections();
        // calculateFinancials() will be called by the other change handler
    });
    $('#shipping_cost, #agent_cost, #cod_amount, #shipping_payer, #cod_amount_from_agent, #courier_commission, #company_cut_amount').on('input change', calculateFinancials);

    toggleFormSections(); // Initial call to set correct sections and requirements
    
    // Set default values for from_agent fields when switching to from_agent mode
    shipmentDirectionSelector.on('change', function() {
        const direction = $(this).val();
        if (direction === 'from_agent' && !isEditMode) {
            // Set default values for from_agent fields
            $('#cod_amount_from_agent').val('0');
            $('#courier_commission').val('30');
            setTimeout(() => {
                calculateFinancials(); // Calculate with default values after a short delay
            }, 100);
        } else if (direction === 'to_agent' && !isEditMode) {
            // Set default values for to_agent fields
            $('#shipping_cost').val('0');
            $('#agent_cost').val('0');
            $('#cod_amount').val('0');
            setTimeout(() => {
                calculateFinancials(); // Calculate with default values after a short delay
            }, 100);
        }
    });
    
    // Handle form submission to map the correct field names
    $('#manage-parcel-form').on('submit', function(e) {
        const direction = shipmentDirectionSelector.filter(':checked').val();
        
        // Map the correct COD amount field based on direction
        if (direction === 'from_agent') {
            // Copy value from from_agent field to the hidden cod_amount field
            const codAmountFromAgent = $('#cod_amount_from_agent').val();
            $('#cod_amount_hidden').val(codAmountFromAgent);
            
            // Copy courier commission AFTER company cut to shipping_cost hidden field
            const courierCommission = parseLocaleNumber($('#courier_commission').val());
            const companyCutAmount = parseLocaleNumber($('#company_cut_amount').val());
            const courierAfterCompany = Math.max(0, courierCommission - companyCutAmount);
            $('#shipping_cost_hidden').val(courierAfterCompany.toFixed(2));
        } else if (direction === 'to_agent') {
            // Ensure the main cod_amount field is used for to_agent
            const codAmount = $('#cod_amount').val();
            $('#cod_amount_hidden').val(codAmount);
            
            // Ensure the main shipping_cost field is used for to_agent
            const shippingCost = $('#shipping_cost').val();
            $('#shipping_cost_hidden').val(shippingCost);
        }
    });
    
    // calculateFinancials() will be called after timeout in edit mode, or immediately in add mode
    if (!isEditMode) {
        calculateFinancials(); // Calculate initial values for new parcels
    }

    clientSearch.on('input', function() {
        const query = $(this).val();
        if (query.length > 2) {
            $.ajax({
                url: 'new_parcel.php',
                type: 'POST',
                data: { action: 'search_customers', query: query },
                success: function(response) {
                    clientSuggestions.empty().show();
                    if (response.status === 'success' && response.data.length > 0) {
                        response.data.forEach(customer => {
                            clientSuggestions.append(`<div class="autocomplete-suggestion" data-id="${customer.id}" data-name="${customer.name}" data-phone="${customer.phone}" data-address="${customer.address}" data-gov-id="${customer.governorate_id}" data-area-id="${customer.area_id}">
                                <b>${customer.name}</b> (${customer.phone})
                            </div>`);
                        });
                    } else {
                        clientSuggestions.append(`<div class="autocomplete-suggestion">لا يوجد عملاء بهذا الاسم أو الرقم</div>`);
                    }
                }
            });
        } else {
            clientSuggestions.hide();
        }
    });

    $(document).on('click', '.autocomplete-suggestion', function() {
        const customer = $(this).data();
        $('#client_id_fk').val(customer.id);
        $('#sender_name').val(customer.name);
        $('#sender_phone').val(customer.phone);
        $('#sender_address').val(customer.address);
        $('#sender-governorate').val(customer.govId).trigger('change');
        setTimeout(() => {
            $('#sender-zone').val(customer.areaId);
        }, 500);
        toggleSenderFields(true);
        clientSuggestions.hide();
    });

    resetCustomerBtn.on('click', function() {
        toggleSenderFields(false);
        $('#sender_name, #sender_phone, #sender_address').val('');
        $('#sender-governorate').val('').trigger('change'); // Reset and trigger change for areas
        $('#sender-zone').val('');
        clientSearch.val(''); // Clear search input
    });

    $('#sender-governorate, #receiver-governorate').on('change', function() {
        const govId = $(this).val();
        const targetZone = $(this).attr('id') === 'sender-governorate' ? $('#sender-zone') : $('#receiver-zone');
        targetZone.empty().append('<option value="">اختر المنطقة</option>').prop('disabled', true);
        if (govId) {
            $.ajax({
                url: 'new_parcel.php',
                type: 'POST',
                data: { action: 'get_areas_by_gov', gov_id: govId },
                dataType: 'json', // Expect JSON response
                success: function(response) {
                    if (response.status === 'success') {
                        response.data.forEach(area => {
                            targetZone.append(`<option value="${area.id}">${area.name}</option>`);
                        });
                        targetZone.prop('disabled', false);
                    } else {
                        console.error('Error fetching areas:', response.message);
                        targetZone.html('<option value="">خطأ في تحميل المناطق</option>');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error fetching areas:', textStatus, errorThrown);
                    targetZone.html('<option value="">خطأ في تحميل المناطق</option>');
                }
            });
        }
    });

    $('#manage-parcel-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...');

        // التحقق من صحة البيانات قبل الإرسال
        const formData = form.serialize();
        console.log('بيانات النموذج:', formData);
        
        $.ajax({
            url: 'save_parcel.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                console.log('استجابة الخادم:', response);
                
                if (response && response.status === 1) { 
                    // عرض رسالة النجاح
                    alert((response.message || 'تم حفظ الشحنة بنجاح') + (response.tracking_number ? '\\nرقم التتبع: ' + response.tracking_number : ''));
                    
                    // إعادة توجيه حسب نوع العملية
                    setTimeout(() => {
                        if (response.parcel_id && !<?php echo $is_edit_mode ? 'true' : 'false'; ?>) {
                            // للشحنات الجديدة - فتح البوليصة
                            window.open('print_waybill.php?id=' + response.parcel_id, '_blank');
                        }
                        
                        // العودة إلى قائمة الشحنات أو إعادة تحميل
                            window.location.href = 'index.php?page=parcel_list';
                    }, 2000);
                    
                } else if (response && response.status === 0) {
                    alert(response.message || 'حدث خطأ أثناء حفظ الشحنة');
                } else {
                    alert('استجابة غير صالحة من الخادم');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('خطأ AJAX:', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    textStatus: textStatus,
                    errorThrown: errorThrown
                });
                
                let errorMessage = 'حدث خطأ غير متوقع';
                
                if (textStatus === 'timeout') {
                    errorMessage = 'انتهت مهلة الاتصال. يرجى المحاولة مرة أخرى.';
                } else if (jqXHR.status === 404) {
                    errorMessage = 'ملف حفظ الشحنة غير موجود.';
                } else if (jqXHR.status === 500) {
                    errorMessage = 'خطأ في الخادم. يرجى التحقق من سجلات الأخطاء.';
                } else if (jqXHR.responseText) {
                    try {
                        const errorResponse = JSON.parse(jqXHR.responseText);
                        errorMessage = errorResponse.message || errorMessage;
                    } catch (e) {
                        // إذا لم يكن JSON صالح، استخدم النص الخام
                        errorMessage += ': ' + jqXHR.responseText.substring(0, 100);
                    }
                }
                
                alert(errorMessage);
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> <?php echo $is_edit_mode ? 'تحديث الشحنة' : 'حفظ الشحنة وطباعة البوليصة'; ?>');
            }
        });
    });

    // زر تغيير الحالة الفعلي من صفحة إضافة/تعديل الشحنة (عند وضع التعديل)
    $(document).on('click', '#btn_change_status_now', function(){
        const parcelId = <?php echo $is_edit_mode ? intval($parcel_data['id']) : 0; ?>;
        if (!parcelId) {
            alert('يجب حفظ الشحنة أولاً قبل تغيير الحالة.');
            return;
        }
        const newStatus = $('#parcel_status').val();
        if (!newStatus) {
            alert('اختر الحالة أولاً');
            return;
        }
        const reasonId = $('#status_reason_id').val() || '';
        const reasonNote = $('#status_reason_note_hidden').val() || '';

        const formData = {
            action: 'change_status',
            id: parcelId,
            new_status: newStatus,
            status_reason_id: reasonId,
            status_reason_note: reasonNote,
            remarks: reasonNote
        };
        $.ajax({
            url: 'ajax_status_update.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp){
                if (resp && resp.status === 'success') {
                    // تحديث الشارة
                    const text = $('#parcel_status option:selected').text();
                    $('#np_current_status_badge').text(text);
                    alert('تم تحديث حالة الشحنة بنجاح');
                } else {
                    alert(resp && resp.message ? resp.message : 'فشل تحديث الحالة');
                }
            },
            error: function(xhr){
                alert('فشل الاتصال بالخادم لتحديث الحالة');
                console.error(xhr.responseText);
            }
        });
    });

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    // تحميل الملخص المالي عند فتح الصفحة (وضع التعديل فقط)
    function npLoadFinancialSummary(){
        const parcelId = <?php echo $is_edit_mode ? intval($parcel_data['id']) : 0; ?>;
        if (!parcelId) return;
        $.ajax({
            url: 'ajax_status_update.php',
            method: 'POST',
            data: { action: 'get_parcel_payment_info', parcel_id: parcelId },
            dataType: 'json',
            success: function(resp){
                if (resp && resp.status === 'success'){
                    const d = resp.data;
                    const total = parseFloat(d.total_to_collect || (d.parcel && d.parcel.total_to_collect) || 0);
                    const paid  = parseFloat(d.paid_amount || (d.parcel && d.parcel.paid_amount) || 0);
                    const remaining = Math.max(0, total - paid);
                    const pstatus = d.payment_status || (d.parcel && d.parcel.payment_status) || 'unpaid';
                    $('#np_total_to_collect').val(total.toFixed(2));
                    $('#np_paid_amount').val(paid.toFixed(2));
                    $('#np_remaining').val(remaining.toFixed(2));
                    $('#np_payment_status').val(pstatus === 'paid' ? 'مدفوع كامل' : (pstatus === 'partial_paid' ? 'مدفوع جزئي' : 'غير مدفوع'));
                }
            }
        });
    }
    npLoadFinancialSummary();

    // بعد تغيير الحالة بنجاح، أعد تحميل الملخص المالي
    $(document).on('click', '#btn_change_status_now', function(){
        setTimeout(npLoadFinancialSummary, 300);
    });
});
</script>
</body>
</html>
