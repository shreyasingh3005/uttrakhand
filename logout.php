<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

if (isset($_SESSION['user_id'])) {
    try {
        $logoutTrackStmt = $conn->prepare('UPDATE users SET is_logged_in = 0, last_logout_at = NOW() WHERE id = :id');
        $logoutTrackStmt->execute([':id' => (int) $_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Non-blocking: logout should continue even if tracking update fails.
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
redirect('/index.php');
