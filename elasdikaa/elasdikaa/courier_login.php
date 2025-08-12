<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

$error = '';
$input_phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($input_phone === '' || $password === '') {
        $error = 'يرجى إدخال رقم الجوال وكلمة المرور.';
    } else {
        $phone = preg_replace('/\D+/', '', $input_phone);
        $stmt = $conn->prepare('SELECT id, name, phone, password, status FROM couriers WHERE phone = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ((int)$row['status'] === 0) {
                    $error = 'الحساب غير نشط. يرجى التواصل مع الإدارة.';
                } else {
                    $stored = $row['password'];
                    $ok = false;
                    if (password_get_info($stored)['algo'] !== 0) {
                        $ok = password_verify($password, $stored);
                    } else {
                        if (md5($password) === $stored) {
                            $ok = true;
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $up = $conn->prepare('UPDATE couriers SET password=? WHERE id=?');
                            if ($up) { $up->bind_param('si', $newHash, $row['id']); $up->execute(); $up->close(); }
                        }
                    }

                    if ($ok) {
                        session_regenerate_id(true);
                        $_SESSION['courier_id'] = (int)$row['id'];
                        $_SESSION['courier_name'] = $row['name'] ?? '';
                        header('Location: courier_dashboard.php');
                        exit;
                    } else {
                        $error = 'رقم الجوال أو كلمة المرور غير صحيحة!';
                    }
                }
            } else {
                $error = 'رقم الجوال أو كلمة المرور غير صحيحة!';
            }
            $stmt->close();
        } else {
            $error = 'حدث خطأ في النظام. يرجى المحاولة لاحقًا.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تسجيل دخول المندوب</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 RTL + FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f3f6fa; min-height:100vh; }
        .login-box { max-width: 370px; margin: 48px auto; background: #fff; border-radius: 22px; box-shadow: 0 4px 18px #e0e6f3; padding: 38px 28px; }
        .logo-img { width: 85px; height: 85px; border-radius: 50%; object-fit:cover; margin-bottom: 18px; box-shadow:0 2px 12px #ddd;}
        .form-control { font-size: 1.11rem; border-radius: 12px; }
        .form-label { font-weight: bold; }
        .input-group-text { background: #e3f2fd; }
        .btn-login { font-size: 1.18rem; padding: 12px 0; border-radius: 16px; font-weight:bold;}
        .alert { font-size: 1.08rem; border-radius: 14px;}
        .show-pass { cursor:pointer; }
        .dark-mode {background: #181a1b;}
        .dark-mode .login-box {background:#23272f; color:#fff;}
        .dark-mode .form-control, .dark-mode .input-group-text {background:#181a1b;color:#fff;}
        .dark-mode .btn-login {background:#1565c0;}
        .dark-mode .alert-danger {background:#a93226;}
    </style>
</head>
<body>
<div class="container">
    <div class="login-box">
        <div class="text-center mb-2">
            <img src="avatar.png" alt="مندوب" class="logo-img">
            <h3 class="fw-bold mb-1 text-primary">مرحبا بعودتك!</h3>
            <div class="text-muted">تسجيل دخول المندوب</div>
        </div>
        <?php if($error): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label"><i class="fa fa-mobile-alt"></i> رقم الجوال</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-phone"></i></span>
                    <input type="tel" name="phone" class="form-control" required placeholder="أدخل رقم جوالك" inputmode="numeric" pattern="[0-9]{8,15}" maxlength="15" value="<?php echo htmlspecialchars($input_phone, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fa fa-lock"></i> كلمة المرور</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-key"></i></span>
                    <input type="password" name="password" id="password" class="form-control" required placeholder="كلمة المرور" autocomplete="current-password">
                    <span class="input-group-text show-pass" onclick="togglePass()"><i class="fa fa-eye"></i></span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-login mt-2" id="loginBtn"><i class="fa fa-sign-in-alt"></i> دخول</button>
        </form>
        <div class="mt-4 text-center">
            <button class="btn btn-dark" onclick="document.body.classList.toggle('dark-mode')"><i class="fa fa-moon"></i> تبديل الوضع الليلي</button>
        </div>
    </div>
</div>
<script>
function togglePass() {
    var pass = document.getElementById('password');
    var icon = document.querySelector('.show-pass i');
    if (pass.type === "password") {
        pass.type = "text";
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pass.type = "password";
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
// تحسين تجربة اللمس ومنع النقر المكرر
document.getElementById('loginBtn').addEventListener('click', function(){
  this.disabled = true;
  setTimeout(()=>{ this.disabled = false; }, 2000);
});
</script>
</body>
</html>