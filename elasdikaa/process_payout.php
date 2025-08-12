<?php
// process_payout.php

// إيقاف إظهار الأخطاء لمنع طباعة أي تحذيرات تفسد استجابة JSON
error_reporting(0);
ini_set('display_errors', 0);

// تفعيل Output Buffering لالتقاط أي مخرجات غير مرغوبة
ob_start();

// لضمان استجابة JSON
header('Content-Type: application/json');

// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

$response = ['status' => 'error', 'message' => 'حدث خطأ غير متوقع.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agent_id'], $_POST['amount'], $_POST['description'])) {
    $agent_id = intval($_POST['agent_id']);
    $amount = floatval($_POST['amount']);
    $description = $conn->real_escape_string($_POST['description']);

    // التحقق من أن المبلغ أكبر من صفر
    if ($amount <= 0) {
        $response['message'] = 'مبلغ الدفعة يجب أن يكون أكبر من صفر.';
        ob_end_clean();
        echo json_encode($response);
        exit();
    }

    // جلب الرصيد الحالي للوكيل للتحقق منه
    // هذا الاستعلام يجب أن يعكس طريقة حساب الرصيد في agent_finance.php
    $balance_query = $conn->query("
        SELECT
            SUM(CASE WHEN transaction_type = 'commission' THEN amount ELSE 0 END) -
            SUM(CASE WHEN transaction_type = 'payout' THEN amount ELSE 0 END) AS current_balance
        FROM
            agent_transactions
        WHERE agent_id = $agent_id
    ");
    $balance_data = $balance_query->fetch_assoc();
    $current_balance = $balance_data['current_balance'] ?? 0;

    // التحقق من أن المبلغ لا يتجاوز الرصيد المتاح (إذا كان الرصيد موجبًا)
    // إذا كان الرصيد سالبًا، فهذا يعني أن الوكيل مدين للشركة، ولا يمكن دفع مبلغ له
    if ($amount > $current_balance && $current_balance >= 0) {
        $response['message'] = 'مبلغ الدفعة يتجاوز الرصيد المتاح للوكيل. الرصيد المتاح: ' . number_format($current_balance, 2) . ' جنيه.';
        ob_end_clean();
        echo json_encode($response);
        exit();
    } elseif ($current_balance < 0) {
         $response['message'] = 'لا يمكن سداد دفعة للوكيل، رصيده الحالي سالب (مدين للشركة).';
        ob_end_clean();
        echo json_encode($response);
        exit();
    }


    // ابدأ المعاملة
    $conn->begin_transaction();

    try {
        // تسجيل حركة الدفعة في جدول agent_transactions
        // المبلغ يجب أن يكون سالبًا ليعكس أنه دفعة خارجة من الشركة للوكيل
        $stmt = $conn->prepare("INSERT INTO agent_transactions (agent_id, amount, transaction_type, description, direction, created_at) VALUES (?, ?, 'payout', ?, 'out', NOW())");
        $neg_amount = $amount; // المبلغ المدفوع يخرج من الشركة، لذا هو موجب هنا في السجل
        $stmt->bind_param("ids", $agent_id, $neg_amount, $description);

        if ($stmt->execute()) {
            $conn->commit();
            $response['status'] = 'success';
            $response['message'] = 'تم تسجيل الدفعة بنجاح.';
        } else {
            throw new Exception("فشل في تسجيل الدفعة: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'خطأ في معالجة الدفعة: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'البيانات غير مكتملة أو طريقة الطلب غير صحيحة.';
}

ob_end_clean();
echo json_encode($response);
?>
