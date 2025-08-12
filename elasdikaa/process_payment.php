<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $agent_id = isset($_POST['agent_id']) ? intval($_POST['agent_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : '';
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

    if ($agent_id > 0 && $amount > 0 && !empty($payment_date)) {
        $stmt = $conn->prepare("INSERT INTO agent_payments (agent_id, amount, payment_date, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idss", $agent_id, $amount, $payment_date, $notes);
        if ($stmt->execute()) {
            echo "<script>alert('تم تسجيل الدفعة بنجاح!'); window.location.href = 'index.php?page=agent_finance&id=$agent_id';</script>";
        } else {
            echo "<script>alert('حدث خطأ أثناء تسجيل الدفعة.'); window.location.href = 'index.php?page=agent_finance&id=$agent_id';</script>";
        }
    } else {
        echo "<script>alert('بيانات الدفعة غير صحيحة.'); window.location.href = 'index.php?page=agent_finance&id=$agent_id';</script>";
    }
}
?>