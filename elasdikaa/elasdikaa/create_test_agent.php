<?php
include 'db_connect.php';

// إنشاء جدول agent_users إذا لم يكن موجوداً
$create_table = "CREATE TABLE IF NOT EXISTS agent_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
)";
$conn->query($create_table);

// التحقق من وجود وكلاء
$check_agents = $conn->query("SELECT * FROM agents LIMIT 1");

if ($check_agents->num_rows == 0) {
    // إنشاء وكيل تجريبي
    $insert_agent = $conn->prepare("INSERT INTO agents (company_name, contact_person, phone, email, address, geo_area, coop_type, commission_type, commission_value, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $company_name = "شركة الاختبار";
    $contact_person = "أحمد محمد";
    $phone = "01234567890";
    $email = "test@test.com";
    $address = "العنوان التجريبي";
    $geo_area = "القاهرة";
    $coop_type = "exchange";
    $commission_type = "fixed";
    $commission_value = 5.00;
    $status = 1;
    
    $insert_agent->bind_param('ssssssssdi', $company_name, $contact_person, $phone, $email, $address, $geo_area, $coop_type, $commission_type, $commission_value, $status);
    
    if ($insert_agent->execute()) {
        $agent_id = $conn->insert_id;
        echo "تم إنشاء وكيل تجريبي بنجاح - ID: $agent_id<br>";
    } else {
        echo "خطأ في إنشاء الوكيل: " . $conn->error . "<br>";
        exit;
    }
} else {
    // استخدام أول وكيل موجود
    $agent = $check_agents->fetch_assoc();
    $agent_id = $agent['id'];
    echo "استخدام الوكيل الموجود: " . $agent['company_name'] . " - ID: $agent_id<br>";
}

// التحقق من وجود حساب للوكيل
$check_user = $conn->prepare("SELECT * FROM agent_users WHERE agent_id = ?");
$check_user->bind_param('i', $agent_id);
$check_user->execute();
$user_result = $check_user->get_result();

if ($user_result->num_rows == 0) {
    // إنشاء حساب تجريبي
    $username = "test_agent";
    $password = "123456";
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $insert_user = $conn->prepare("INSERT INTO agent_users (agent_id, username, password, status) VALUES (?, ?, ?, 'active')");
    $insert_user->bind_param('iss', $agent_id, $username, $hashed_password);
    
    if ($insert_user->execute()) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>✅ تم إنشاء حساب تجريبي بنجاح!</h3>";
        echo "<p><strong>اسم المستخدم:</strong> $username</p>";
        echo "<p><strong>كلمة المرور:</strong> $password</p>";
        echo "<p><strong>معرف الوكيل:</strong> $agent_id</p>";
        echo "</div>";
        
        echo "<a href='agent_login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>انتقل لصفحة تسجيل الدخول</a>";
    } else {
        echo "خطأ في إنشاء الحساب: " . $conn->error;
    }
} else {
    $user = $user_result->fetch_assoc();
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>⚠️ يوجد حساب مسبقاً للوكيل!</h3>";
    echo "<p><strong>اسم المستخدم:</strong> " . $user['username'] . "</p>";
    echo "<p><strong>حالة الحساب:</strong> " . $user['status'] . "</p>";
    echo "<p><strong>تاريخ الإنشاء:</strong> " . $user['created_at'] . "</p>";
    echo "</div>";
    
    echo "<a href='agent_login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>انتقل لصفحة تسجيل الدخول</a>";
}

// عرض جميع الحسابات الموجودة
echo "<hr><h4>جميع حسابات الوكلاء الموجودة:</h4>";
$all_users = $conn->query("SELECT au.*, a.company_name FROM agent_users au JOIN agents a ON au.agent_id = a.id");

if ($all_users->num_rows > 0) {
    echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='padding: 10px;'>ID</th>";
    echo "<th style='padding: 10px;'>اسم المستخدم</th>";
    echo "<th style='padding: 10px;'>اسم الشركة</th>";
    echo "<th style='padding: 10px;'>الحالة</th>";
    echo "<th style='padding: 10px;'>تاريخ الإنشاء</th>";
    echo "</tr>";
    
    while ($user = $all_users->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 10px; text-align: center;'>" . $user['id'] . "</td>";
        echo "<td style='padding: 10px;'>" . $user['username'] . "</td>";
        echo "<td style='padding: 10px;'>" . $user['company_name'] . "</td>";
        echo "<td style='padding: 10px; text-align: center;'>" . $user['status'] . "</td>";
        echo "<td style='padding: 10px; text-align: center;'>" . $user['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>لا توجد حسابات وكلاء في النظام.</p>";
}

$conn->close();
?>

<style>
body {
    font-family: 'Arial', sans-serif;
    direction: rtl;
    margin: 20px;
    background-color: #f8f9fa;
}
</style>




