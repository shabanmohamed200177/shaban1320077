<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db_connect.php';

SecurityMiddleware::checkSession();
header('Content-Type: application/json; charset=utf-8');

// منع عرض التحذيرات داخل JSON
if (function_exists('ini_set')) {
    @ini_set('display_errors', 0);
    @ini_set('log_errors', 1);
}

// التحقق من CSRF
SecurityMiddleware::checkCSRF();

$identifier = trim($_POST['identifier'] ?? '');
$password = (string)($_POST['password'] ?? '');
$remember = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';

if ($identifier === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'من فضلك أدخل كل الحقول']);
    exit;
}

// هل هو بريد أم هاتف؟
$byEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

try {
    if ($byEmail) {
        $stmt = $conn->prepare('SELECT * FROM couriers WHERE email = ? AND status = 1 LIMIT 1');
    } else {
        $stmt = $conn->prepare('SELECT * FROM couriers WHERE phone = ? AND status = 1 LIMIT 1');
    }
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        echo json_encode(['success' => false, 'message' => 'الحساب غير موجود أو غير نشط']);
        exit;
    }

    $courier = $result->fetch_assoc();
    $stmt->close();

    $login_success = false;
    // password_hash
    if (!empty($courier['password']) && password_get_info($courier['password'])['algo'] !== 0) {
        $login_success = password_verify($password, $courier['password']);
    } else {
        // دعم MD5 قديم
        if (!empty($courier['password']) && md5($password) === $courier['password']) {
            $login_success = true;
            // ترقية الهاش
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $up = $conn->prepare('UPDATE couriers SET password = ? WHERE id = ?');
            $up->bind_param('si', $new_hash, $courier['id']);
            $up->execute();
            $up->close();
        }
    }

    if (!$login_success) {
        // زيادة المحاولات الفاشلة إن كان الحقل موجودًا
        if (array_key_exists('failed_attempts', $courier)) {
            $conn->query('UPDATE couriers SET failed_attempts = failed_attempts + 1 WHERE id = ' . (int)$courier['id']);
        }
        echo json_encode(['success' => false, 'message' => 'بيانات الدخول غير صحيحة']);
        exit;
    }

    // نجاح: إعادة تهيئة الجلسة
    session_regenerate_id(true);
    $_SESSION['courier_id'] = (int)$courier['id'];
    $_SESSION['courier_name'] = $courier['name'] ?? '';

    // تحديث وقت الدخول وتصغير failed_attempts
    if (array_key_exists('failed_attempts', $courier)) {
        $upd = $conn->prepare('UPDATE couriers SET last_login = NOW(), failed_attempts = 0 WHERE id = ?');
    } else {
        $upd = $conn->prepare('UPDATE couriers SET last_login = NOW() WHERE id = ?');
    }
    $upd->bind_param('i', $courier['id']);
    $upd->execute();
    $upd->close();

    // Remember Me
    if ($remember) {
        $raw_token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $raw_token, true); // ثنائي
        $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '', true);
        $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '', true);
        $expires_at = date('Y-m-d H:i:s', time() + 60*60*24*30); // 30 يوم

        $ins = $conn->prepare('INSERT INTO courier_auth_tokens (courier_id, token_hash, user_agent_hash, ip_hash, expires_at) VALUES (?,?,?,?,?)');
        $ins->bind_param('sssss', $cid_str, $token_hash, $ua_hash, $ip_hash, $expires_at);
        // تمرير المعرف كسلسلة لتجنب مشاكل بعض إصدارات MariaDB مع مزج أنواع ثنائية
        $cid_str = (string)$courier['id'];
        $ok = $ins->execute();
        $ins->close();

        if ($ok) {
            // الكوكي يحمل raw token + courier id، السيرفر سيطابق بالهاش
            $cookie_value = base64_encode(json_encode([
                'cid' => (int)$courier['id'],
                't'   => $raw_token
            ], JSON_UNESCAPED_SLASHES));
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('courier_remember', $cookie_value, [
                'expires' => time() + 60*60*24*30,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    // استخدم مسار نسبي داخل مجلد courier لتجنّب 404 على /newwww/dashboard.php
    echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
    exit;
} catch (Throwable $e) {
    handleError('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ غير متوقع، حاول لاحقًا']);
    exit;
}


