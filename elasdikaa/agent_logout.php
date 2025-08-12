<?php
session_start();

// تدمير جلسة الوكيل
if (isset($_SESSION['agent_logged_in'])) {
    // تسجيل وقت تسجيل الخروج
    if (isset($_SESSION['agent_user_id'])) {
        include 'db_connect.php';
        $user_id = $_SESSION['agent_user_id'];
        $update_stmt = $conn->prepare("UPDATE agent_users SET last_login = NOW() WHERE id = ?");
        $update_stmt->bind_param('i', $user_id);
        $update_stmt->execute();
        $conn->close();
    }
    
    // حذف متغيرات الجلسة
    unset($_SESSION['agent_logged_in']);
    unset($_SESSION['agent_user_id']);
    unset($_SESSION['agent_id']);
    unset($_SESSION['agent_username']);
    unset($_SESSION['agent_company']);
    unset($_SESSION['agent_login_time']);
}

// تدمير الجلسة بالكامل
session_destroy();

// إعادة التوجيه إلى صفحة تسجيل الدخول
header('Location: agent_login.php?logout=success');
exit;
?>
