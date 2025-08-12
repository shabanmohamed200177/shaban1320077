<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['courier_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

$parcel_id = isset($_GET['parcel_id']) ? intval($_GET['parcel_id']) : null;
$courier_id = $_SESSION['courier_id'];

if (!$parcel_id) {
    echo json_encode(['success' => false, 'message' => 'معرف الشحنة مطلوب']);
    exit();
}

try {
    // جلب بيانات الشحنة مع بيانات العميل والفرع والمنطقة
    $stmt = $conn->prepare("
        SELECT p.*, c.customer_name, c.customer_phone, c.customer_address, c.customer_email,
               b.branch_name, g.governorate_name, a.area_name
        FROM parcels p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN governorates g ON p.province_id = g.id
        LEFT JOIN areas a ON p.area_id = a.id
        WHERE p.id = ? AND p.courier_id = ?
    ");
    $stmt->bind_param("ii", $parcel_id, $courier_id);
    $stmt->execute();
    $parcel = $stmt->get_result()->fetch_assoc();

    if (!$parcel) {
        echo json_encode(['success' => false, 'message' => 'الشحنة غير موجودة أو لا تخصك']);
        exit();
    }

    // سجل تغييرات الحالة
    $log_stmt = $conn->prepare("
        SELECT psl.*, c.name as courier_name 
        FROM parcel_status_logs psl
        LEFT JOIN couriers c ON psl.courier_id = c.id
        WHERE psl.parcel_id = ?
        ORDER BY psl.created_at DESC
    ");
    $log_stmt->bind_param("i", $parcel_id);
    $log_stmt->execute();
    $logs = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // الملاحظات
    $notes_stmt = $conn->prepare("
        SELECT pn.*, c.name as courier_name 
        FROM parcel_notes pn
        LEFT JOIN couriers c ON pn.courier_id = c.id
        WHERE pn.parcel_id = ?
        ORDER BY pn.created_at DESC
    ");
    $notes_stmt->bind_param("i", $parcel_id);
    $notes_stmt->execute();
    $notes = $notes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // إنشاء HTML
    ob_start();
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary"><i class="fas fa-box me-2"></i>معلومات الشحنة</h6>
            <table class="table table-sm">
                <tr><td><strong>رقم التتبع:</strong></td><td><?= htmlspecialchars($parcel['tracking_number']) ?></td></tr>
                <tr><td><strong>الحالة:</strong></td><td><span class="badge bg-primary"><?= getStatusText($parcel['status']) ?></span></td></tr>
                <tr><td><strong>نوع الشحنة:</strong></td><td><?= htmlspecialchars($parcel['parcel_type'] ?? 'غير محدد') ?></td></tr>
                <tr><td><strong>الوزن:</strong></td><td><?= htmlspecialchars($parcel['weight'] ?? 'غير محدد') ?> كجم</td></tr>
                <tr><td><strong>رسوم الشحن:</strong></td><td><?= number_format($parcel['shipping_fee'] ?? 0, 2) ?> ريال</td></tr>
                <tr><td><strong>تاريخ الإنشاء:</strong></td><td><?= date('Y-m-d H:i', strtotime($parcel['created_at'])) ?></td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-primary"><i class="fas fa-user me-2"></i>معلومات العميل</h6>
            <table class="table table-sm">
                <tr><td><strong>الاسم:</strong></td><td><?= htmlspecialchars($parcel['customer_name']) ?></td></tr>
                <tr><td><strong>الهاتف:</strong></td><td><?= htmlspecialchars($parcel['customer_phone']) ?></td></tr>
                <tr><td><strong>البريد الإلكتروني:</strong></td><td><?= htmlspecialchars($parcel['customer_email'] ?? 'غير محدد') ?></td></tr>
                <tr><td><strong>العنوان:</strong></td><td><?= htmlspecialchars($parcel['customer_address']) ?></td></tr>
                <tr><td><strong>الفرع:</strong></td><td><?= htmlspecialchars($parcel['branch_name'] ?? 'غير محدد') ?></td></tr>
                <tr><td><strong>المنطقة:</strong></td><td><?= htmlspecialchars($parcel['area_name'] ?? 'غير محدد') ?></td></tr>
            </table>
        </div>
    </div>

    <hr>

    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary"><i class="fas fa-history me-2"></i>سجل تغييرات الحالة</h6>
            <div class="timeline">
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="timeline-item mb-2">
                            <div class="d-flex align-items-center">
                                <div class="timeline-marker bg-primary rounded-circle me-2" style="width: 10px; height: 10px;"></div>
                                <div>
                                    <strong><?= getStatusText($log['new_status']) ?></strong>
                                    <br><small class="text-muted"><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?> - <?= htmlspecialchars($log['courier_name']) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">لا يوجد سجل تغييرات</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <h6 class="text-primary"><i class="fas fa-comments me-2"></i>الملاحظات</h6>
            <div class="notes-container">
                <?php if (count($notes) > 0): ?>
                    <?php foreach ($notes as $note): ?>
                        <div class="note-item mb-2 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between">
                                <strong><?= htmlspecialchars($note['courier_name']) ?></strong>
                                <small class="text-muted"><?= date('Y-m-d H:i', strtotime($note['created_at'])) ?></small>
                            </div>
                            <p class="mb-0 mt-1"><?= htmlspecialchars($note['note']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">لا توجد ملاحظات</p>
                <?php endif; ?>
            </div>
            <hr>
            <div class="add-note">
                <h6>إضافة ملاحظة جديدة</h6>
                <div class="input-group">
                    <textarea class="form-control" id="newNote" rows="2" placeholder="اكتب ملاحظتك هنا..."></textarea>
                    <button class="btn btn-primary" onclick="addNote(<?= $parcel_id ?>)">
                        <i class="fas fa-plus me-1"></i>إضافة
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}

// دالة لتحويل حالة الشحنة إلى نص عربي
function getStatusText($status) {
    $statusMap = [
        1 => 'جديد',
        2 => 'مُسند',
        3 => 'تم الاستلام',
        4 => 'قيد النقل',
        5 => 'خارج للتوصيل',
        6 => 'تم التوصيل',
        7 => 'فشل التوصيل',
        8 => 'مرتجع',
        9 => 'ملغي',
        10 => 'معلق'
    ];
    return $statusMap[$status] ?? $status;
}
