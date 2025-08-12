<?php
include 'db_connect.php';

echo "<h2>🔧 إعادة تعيين كلمة مرور الوكيل</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $username = $_POST['username'];
    $new_password = $_POST['new_password'];
    
    // تشفير كلمة المرور الجديدة
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // تحديث كلمة المرور
    $update_stmt = $conn->prepare("UPDATE agent_users SET password = ? WHERE username = ?");
    $update_stmt->bind_param('ss', $hashed_password, $username);
    
    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>✅ تم تحديث كلمة المرور بنجاح!</h3>";
            echo "<p><strong>اسم المستخدم:</strong> $username</p>";
            echo "<p><strong>كلمة المرور الجديدة:</strong> $new_password</p>";
            echo "<p><strong>الكود المشفر:</strong> " . substr($hashed_password, 0, 30) . "...</p>";
            echo "</div>";
            
            // اختبار كلمة المرور الجديدة
            if (password_verify($new_password, $hashed_password)) {
                echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
                echo "✅ <strong>تم التحقق من كلمة المرور بنجاح!</strong>";
                echo "</div>";
            }
            
            echo "<a href='agent_login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>جرب تسجيل الدخول الآن</a>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
            echo "❌ لم يتم العثور على المستخدم: $username";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "❌ خطأ في تحديث كلمة المرور: " . $conn->error;
        echo "</div>";
    }
}

// عرض جميع المستخدمين الموجودين
echo "<h3>👤 المستخدمين الموجودين:</h3>";
$users = $conn->query("SELECT au.id, au.username, au.status, au.created_at, a.company_name 
                      FROM agent_users au 
                      JOIN agents a ON au.agent_id = a.id");

if ($users && $users->num_rows > 0) {
    echo "<table border='1' style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='padding: 10px;'>ID</th>";
    echo "<th style='padding: 10px;'>اسم المستخدم</th>";
    echo "<th style='padding: 10px;'>اسم الشركة</th>";
    echo "<th style='padding: 10px;'>الحالة</th>";
    echo "<th style='padding: 10px;'>تاريخ الإنشاء</th>";
    echo "</tr>";
    
    while ($user = $users->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 10px; text-align: center;'>" . $user['id'] . "</td>";
        echo "<td style='padding: 10px;'><strong>" . $user['username'] . "</strong></td>";
        echo "<td style='padding: 10px;'>" . $user['company_name'] . "</td>";
        echo "<td style='padding: 10px; text-align: center;'>" . $user['status'] . "</td>";
        echo "<td style='padding: 10px; text-align: center;'>" . $user['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// نموذج إعادة تعيين كلمة المرور
echo "<h3>🔑 إعادة تعيين كلمة المرور:</h3>";
echo "<form method='POST' style='background: #f5f5f5; padding: 20px; border-radius: 5px; max-width: 500px;'>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>اسم المستخدم:</label>";
echo "<select name='username' required style='width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ddd;'>";
echo "<option value=''>اختر المستخدم</option>";

// إعادة جلب المستخدمين للقائمة المنسدلة
$users_select = $conn->query("SELECT username FROM agent_users");
while ($user = $users_select->fetch_assoc()) {
    echo "<option value='{$user['username']}'>{$user['username']}</option>";
}

echo "</select>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>كلمة المرور الجديدة:</label>";
echo "<input type='text' name='new_password' required style='width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ddd;' placeholder='أدخل كلمة المرور الجديدة'>";
echo "</div>";

echo "<button type='submit' name='reset_password' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>إعادة تعيين كلمة المرور</button>";
echo "</form>";

echo "<hr>";
echo "<h3>🧪 اختبار كلمة مرور موجودة:</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_password'])) {
    $test_username = $_POST['test_username'];
    $test_password = $_POST['test_password'];
    
    $user_query = $conn->prepare("SELECT username, password FROM agent_users WHERE username = ?");
    $user_query->bind_param('s', $test_username);
    $user_query->execute();
    $user_result = $user_query->get_result();
    
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>نتيجة الاختبار:</h4>";
        echo "<p><strong>المستخدم:</strong> " . $user_data['username'] . "</p>";
        echo "<p><strong>كلمة المرور المختبرة:</strong> $test_password</p>";
        echo "<p><strong>الكود المحفوظ:</strong> " . substr($user_data['password'], 0, 30) . "...</p>";
        
        if (password_verify($test_password, $user_data['password'])) {
            echo "<p style='color: green; font-weight: bold;'>✅ كلمة المرور صحيحة!</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>❌ كلمة المرور خاطئة!</p>";
        }
        echo "</div>";
    }
}

echo "<form method='POST' style='background: #f0f8ff; padding: 15px; border-radius: 5px; max-width: 400px;'>";
echo "<div style='margin-bottom: 10px;'>";
echo "<label>المستخدم:</label>";
echo "<select name='test_username' required style='width: 100%; padding: 5px; margin: 5px 0;'>";
echo "<option value=''>اختر المستخدم</option>";

$users_test = $conn->query("SELECT username FROM agent_users");
while ($user = $users_test->fetch_assoc()) {
    echo "<option value='{$user['username']}'>{$user['username']}</option>";
}

echo "</select>";
echo "</div>";
echo "<div style='margin-bottom: 10px;'>";
echo "<label>كلمة المرور:</label>";
echo "<input type='text' name='test_password' required style='width: 100%; padding: 5px; margin: 5px 0;'>";
echo "</div>";
echo "<button type='submit' name='test_password' style='background: #17a2b8; color: white; padding: 8px 16px; border: none; border-radius: 5px;'>اختبار كلمة المرور</button>";
echo "</form>";

echo "<hr>";
echo "<p><a href='agent_login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>العودة لصفحة تسجيل الدخول</a></p>";
echo "<p><a href='debug_login.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>صفحة التشخيص</a></p>";

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












