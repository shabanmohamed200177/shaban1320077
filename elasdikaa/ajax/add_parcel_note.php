<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['courier_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$parcel_id = isset($input['parcel_id']) ? intval($input['parcel_id']) : null;
$note = isset($input['note']) ? trim($input['note']) : '';

$courier_id = $_SESSION['courier_id'];

if (!$parcel_id || empty($note)) {
    echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
    exit();
}

try {
    $stmt = $conn->prepare("INSERT INTO parcel_notes (parcel_id, courier_id, note, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $parcel_id, $courier_id, $note);

    if ($stmt->execute()) {
        // جلب اسم المندوب
        $courier_stmt = $conn->prepare("SELECT name FROM couriers WHERE id = ?");
        $courier_stmt->bind_param("i", $courier_id);
        $courier_stmt->execute();
        $courier = $courier_stmt->get_result()->fetch_assoc();

        $note_html = '
            <div class="note-item mb-2 p-2 bg-light rounded">
                <div class="d-flex justify-content-between">
                    <strong>' . htmlspecialchars($courier['name']) . '</strong>
                    <small class="text-muted">' . date('Y-m-d H:i') . '</small>
                </div>
                <p class="mb-0 mt-1">' . htmlspecialchars($note) . '</p>
            </div>
        ';

        echo json_encode(['success' => true, 'note_html' => $note_html]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في إضافة الملاحظة']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
