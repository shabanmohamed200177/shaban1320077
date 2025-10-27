<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db_connect.php';

SecurityMiddleware::checkSession();

// إبطال أي تذكر تلقائي
if (isset($_COOKIE['courier_remember'])) {
    $cookie = json_decode(base64_decode($_COOKIE['courier_remember'] ?? ''), true);
    if (is_array($cookie) && isset($cookie['cid'], $cookie['t'])) {
        $token_hash = hash('sha256', $cookie['t'], true);
        $cid_str = (string)((int)$cookie['cid']);
        $stmt = $conn->prepare('UPDATE courier_auth_tokens SET revoked_at = NOW() WHERE courier_id = ? AND token_hash = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ss', $cid_str, $token_hash);
            $stmt->execute();
            $stmt->close();
        }
    }
    setcookie('courier_remember', '', time() - 3600, '/');
}

// مسح الجلسة
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: login.php');
exit;


